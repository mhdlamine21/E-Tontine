<?php
//  VOTE.PHP - CORRIGÉ
//  Le schéma SQL réel (creation_tables.sql) définit :
//    - votes.nombre_oui / votes.nombre_non  (PAS de colonne nombre_votes)
//    - votes_membres.choix ENUM('oui','non') (PAS de colonne candidat_id)
//    - candidatures_admin (id, vote_id, candidat_id, message, date_candidature)
//      → aucune colonne de comptage de voix par candidat.
//  L'ancienne version de ce fichier référençait des colonnes qui
//  n'existent pas (candidat_id dans votes_membres, nombre_votes dans votes
//  et dans candidatures_admin) : chaque appel à voter() provoquait une
//  erreur SQL fatale. Ce fichier réconcilie le code avec le modèle réel :
//  un vote est un vote OUI/NON sur « faut-il changer d'administrateur ? »,
//  et si le OUI l'emporte, le nouvel admin est le candidat déclaré
//  (table candidatures_admin) ayant le meilleur score de fiabilité -
//  à défaut de candidat déclaré, le membre le plus fiable de la tontine.

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/membre.php';
require_once __DIR__ . '/mail.php';

// Créer une pétition
function creerPetition(int $tontineId, int $initiateurId, string $motif): array {
    $bd = connexionBD();

    $req = $bd->prepare("SELECT id FROM petitions WHERE tontine_id = ? AND statut IN ('en_cours','vote_ouvert')");
    $req->execute([$tontineId]);
    if ($req->fetch()) {
        return ['succes' => false, 'message' => 'Une pétition est déjà en cours pour cette tontine.'];
    }

    $req = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $req->execute([$tontineId, STATUT_EXCLU]);
    $nbMembres = (int)$req->fetchColumn();
    $seuil     = (int)ceil($nbMembres * SEUIL_PETITION_TIERS);

    $bd->prepare('INSERT INTO petitions (tontine_id, initiateur_id, motif, seuil_signatures) VALUES (?, ?, ?, ?)')
       ->execute([$tontineId, $initiateurId, $motif, $seuil]);
    $petitionId = (int)$bd->lastInsertId();

    signerPetition($petitionId, $initiateurId);

    return ['succes' => true, 'petition_id' => $petitionId, 'message' => 'Pétition créée. Seuil : ' . $seuil . ' signatures.'];
}

// Signer une pétition
function signerPetition(int $petitionId, int $signaireId): array {
    $bd = connexionBD();

    $req = $bd->prepare('SELECT id FROM signatures_petition WHERE petition_id = ? AND signataire_id = ?');
    $req->execute([$petitionId, $signaireId]);
    if ($req->fetch()) return ['succes' => false, 'message' => 'Vous avez déjà signé cette pétition.'];

    $bd->prepare('INSERT INTO signatures_petition (petition_id, signataire_id) VALUES (?, ?)')
       ->execute([$petitionId, $signaireId]);

    $bd->prepare('UPDATE petitions SET nombre_signatures = nombre_signatures + 1 WHERE id = ?')
       ->execute([$petitionId]);

    $req = $bd->prepare('SELECT * FROM petitions WHERE id = ?');
    $req->execute([$petitionId]);
    $petition = $req->fetch();
    if ($petition && (int)$petition['nombre_signatures'] >= (int)$petition['seuil_signatures']) {
        ouvrirVote((int)$petition['id'], (int)$petition['tontine_id']);
    }

    return ['succes' => true, 'message' => 'Signature enregistrée.'];
}

// Ouvrir un vote OUI/NON
function ouvrirVote(int $petitionId, int $tontineId): void {
    $bd = connexionBD();

    $bd->prepare("UPDATE petitions SET statut = 'vote_ouvert' WHERE id = ?")
       ->execute([$petitionId]);

    $bd->prepare('INSERT INTO votes (tontine_id, petition_id, seuil_validation) VALUES (?, ?, ?)')
       ->execute([$tontineId, $petitionId, SEUIL_VOTE_POURCENT]);

    notifierTousMembres(
        $tontineId,
        'vote_ouvert',
        'Vote ouvert : faut-il changer d’administrateur ?',
        'Le seuil de signatures est atteint. Votez OUI ou NON pour décider si l’administrateur doit être remplacé. Vous pouvez aussi vous porter candidat pour le remplacer.',
        APP_URL . '/pages/votes/voter.php?tontine=' . $tontineId
    );
}

// Voter OUI ou NON sur le changement d'admin
function voter(int $voteId, int $votantId, string $choix): array {
    if (!in_array($choix, ['oui', 'non'], true)) {
        return ['succes' => false, 'message' => 'Choix de vote invalide.'];
    }

    $bd = connexionBD();

    $req = $bd->prepare("SELECT * FROM votes WHERE id = ? AND statut = 'ouvert'");
    $req->execute([$voteId]);
    $vote = $req->fetch();
    if (!$vote) return ['succes' => false, 'message' => 'Vote introuvable ou déjà clôturé.'];

    $req = $bd->prepare('SELECT id FROM votes_membres WHERE vote_id = ? AND votant_id = ?');
    $req->execute([$voteId, $votantId]);
    if ($req->fetch()) return ['succes' => false, 'message' => 'Vous avez déjà voté.'];

    $tontineId = (int)$vote['tontine_id'];
    if (!estMembreDeTontine($votantId, $tontineId)) {
        return ['succes' => false, 'message' => 'Vous n’êtes pas membre de cette tontine.'];
    }

    $bd->prepare('INSERT INTO votes_membres (vote_id, votant_id, choix) VALUES (?, ?, ?)')
       ->execute([$voteId, $votantId, $choix]);

    $colonne = $choix === 'oui' ? 'nombre_oui' : 'nombre_non';
    $bd->prepare("UPDATE votes SET {$colonne} = {$colonne} + 1 WHERE id = ?")
       ->execute([$voteId]);

    // Clôturer automatiquement si tous les membres actifs ont voté
    $req = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $req->execute([$tontineId, STATUT_EXCLU]);
    $nbMembres = (int)$req->fetchColumn();

    $req = $bd->prepare('SELECT COUNT(*) FROM votes_membres WHERE vote_id = ?');
    $req->execute([$voteId]);
    $totalVotes = (int)$req->fetchColumn();

    if ($totalVotes >= $nbMembres && $nbMembres > 0) {
        cloturerVote($voteId, $tontineId);
    }

    return ['succes' => true, 'message' => 'Vote enregistré.'];
}

// Récupérer les candidats déclarés (sans comptage de voix : le schéma ne le permet pas)
function getCandidatsEtVotes(int $voteId): array {
    $bd = connexionBD();
    $req = $bd->prepare('
        SELECT ca.*, u.nom_complet, u.email, mt.score_fiabilite
        FROM candidatures_admin ca
        JOIN utilisateurs u ON u.id = ca.candidat_id
        JOIN membres_tontine mt ON mt.utilisateur_id = ca.candidat_id
             AND mt.tontine_id = (SELECT tontine_id FROM votes WHERE id = ?)
        WHERE ca.vote_id = ?
        ORDER BY mt.score_fiabilite DESC
    ');
    $req->execute([$voteId, $voteId]);
    return $req->fetchAll();
}

// Clôturer le vote et appliquer le résultat
function cloturerVote(int $voteId, int $tontineId): void {
    $bd = connexionBD();

    $req = $bd->prepare('SELECT * FROM votes WHERE id = ?');
    $req->execute([$voteId]);
    $vote = $req->fetch();
    if (!$vote) return;

    $oui   = (int)$vote['nombre_oui'];
    $non   = (int)$vote['nombre_non'];
    $total = $oui + $non;
    $pctOui = $total > 0 ? ($oui / $total) * 100 : 0;
    $seuil  = (float)$vote['seuil_validation'];

    if ($pctOui >= $seuil && $oui > 0) {
        $candidats = getCandidatsEtVotes($voteId);
        $nouvelAdminId = null;

        if (!empty($candidats)) {
            $nouvelAdminId = (int)$candidats[0]['candidat_id'];
        } else {
            $req = $bd->prepare('
                SELECT utilisateur_id FROM membres_tontine
                WHERE tontine_id = ? AND role != ? AND statut != ?
                ORDER BY score_fiabilite DESC LIMIT 1
            ');
            $req->execute([$tontineId, ROLE_ADMIN, STATUT_EXCLU]);
            $candidatId = $req->fetchColumn();
            if ($candidatId) $nouvelAdminId = (int)$candidatId;
        }

        if ($nouvelAdminId) {
            $bd->prepare('UPDATE membres_tontine SET role = ? WHERE tontine_id = ? AND role = ?')
               ->execute([ROLE_MEMBRE, $tontineId, ROLE_ADMIN]);
            $bd->prepare('UPDATE membres_tontine SET role = ? WHERE tontine_id = ? AND utilisateur_id = ?')
               ->execute([ROLE_ADMIN, $tontineId, $nouvelAdminId]);
            $bd->prepare('UPDATE tontines SET createur_id = ? WHERE id = ?')
               ->execute([$nouvelAdminId, $tontineId]);

            $bd->prepare('UPDATE votes SET statut = ?, resultat = ?, nouvel_admin_id = ?, date_cloture = NOW() WHERE id = ?')
               ->execute([VOTE_CLOS, 'admin_change', $nouvelAdminId, $voteId]);

            $req = $bd->prepare('SELECT nom_complet, email FROM utilisateurs WHERE id = ?');
            $req->execute([$nouvelAdminId]);
            $nouvelAdmin = $req->fetch();

            $message = 'Le vote est terminé (' . $oui . ' OUI / ' . $non . ' NON). '
                . ($nouvelAdmin ? htmlspecialchars($nouvelAdmin['nom_complet']) . ' devient le nouvel administrateur.' : 'Un nouvel administrateur a été désigné.');

            if ($nouvelAdmin) {
                envoyerEmailNouvelAdmin($nouvelAdmin['email'], $nouvelAdmin['nom_complet'], $tontineId);
            }
        } else {
            $bd->prepare('UPDATE votes SET statut = ?, resultat = ?, date_cloture = NOW() WHERE id = ?')
               ->execute([VOTE_CLOS, 'admin_maintenu', $voteId]);
            $message = 'Le OUI l’emporte (' . $oui . ' OUI / ' . $non . ' NON) mais aucun candidat ne s’est déclaré : l’administrateur actuel est maintenu.';
        }
    } else {
        $bd->prepare('UPDATE votes SET statut = ?, resultat = ?, date_cloture = NOW() WHERE id = ?')
           ->execute([VOTE_CLOS, 'admin_maintenu', $voteId]);
        $message = 'Le vote est terminé (' . $oui . ' OUI / ' . $non . ' NON). Le seuil de ' . $seuil . '% de OUI n’est pas atteint : l’administrateur actuel est maintenu.';
    }

    notifierTousMembres($tontineId, 'admin_change', 'Résultat du vote', $message, APP_URL . '/pages/tontines/voir.php?id=' . $tontineId);

    $req = $bd->prepare('SELECT resultat FROM votes WHERE id = ?');
    $req->execute([$voteId]);
    $resultat = $req->fetchColumn();
    $bd->prepare("UPDATE petitions SET statut = IF(? = 'admin_change', 'acceptee', 'rejetee'), date_cloture = NOW() WHERE id = (SELECT petition_id FROM votes WHERE id = ?)")
       ->execute([$resultat, $voteId]);
}

// Se porter candidat
function sePorterCandidat(int $voteId, int $candidatId, string $message = ''): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT id FROM candidatures_admin WHERE vote_id = ? AND candidat_id = ?');
    $req->execute([$voteId, $candidatId]);
    if ($req->fetch()) return ['succes' => false, 'message' => 'Vous êtes déjà candidat.'];

    $bd->prepare('INSERT INTO candidatures_admin (vote_id, candidat_id, message) VALUES (?, ?, ?)')
       ->execute([$voteId, $candidatId, $message]);

    return ['succes' => true, 'message' => 'Candidature enregistrée.'];
}

// Lister les votes d'une tontine
function listerVotes(int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('
        SELECT v.*, p.motif AS motif_petition,
               (SELECT COUNT(*) FROM votes_membres WHERE vote_id = v.id) as total_votes
        FROM votes v
        JOIN petitions p ON p.id = v.petition_id
        WHERE v.tontine_id = ?
        ORDER BY v.date_ouverture DESC
    ');
    $req->execute([$tontineId]);
    return $req->fetchAll();
}

// Lister les pétitions d'une tontine
function listerPetitions(int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT p.*, u.nom_complet AS nom_initiateur FROM petitions p JOIN utilisateurs u ON u.id = p.initiateur_id WHERE p.tontine_id = ? ORDER BY p.date_creation DESC');
    $req->execute([$tontineId]);
    return $req->fetchAll();
}

// Vérifier si un membre a déjà voté
function aDejaVote(int $voteId, int $membreId): bool {
    $bd = connexionBD();
    $req = $bd->prepare('SELECT id FROM votes_membres WHERE vote_id = ? AND votant_id = ?');
    $req->execute([$voteId, $membreId]);
    return (bool)$req->fetch();
}

// Récupérer le vote d'un membre
function getVoteMembre(int $voteId, int $membreId): ?array {
    $bd = connexionBD();
    $req = $bd->prepare('SELECT * FROM votes_membres WHERE vote_id = ? AND votant_id = ?');
    $req->execute([$voteId, $membreId]);
    return $req->fetch() ?: null;
}
