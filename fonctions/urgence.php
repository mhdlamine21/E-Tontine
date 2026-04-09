<?php

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/cagnotte.php';
require_once __DIR__ . '/membre.php'; // AJOUTÉ

// Créer une demande d'urgence
function creerDemandeUrgence(int $tontineId, int $demandeurId, string $motif): array {
    $bd = connexionBD();

    // Vérifier qu'il n'y a pas déjà une demande en attente
    $req = $bd->prepare("SELECT id FROM demandes_urgence WHERE tontine_id = ? AND demandeur_id = ? AND statut = 'en_attente'");
    $req->execute([$tontineId, $demandeurId]);
    if ($req->fetch()) {
        return ['succes' => false, 'message' => 'Vous avez déjà une demande d\'urgence en attente.'];
    }

    $bd->prepare('INSERT INTO demandes_urgence (tontine_id, demandeur_id, motif) VALUES (?, ?, ?)')
       ->execute([$tontineId, $demandeurId, $motif]);

    // Notifier l'admin
    $adminId = obtenirAdminTontine($tontineId);
    if ($adminId) {
        creerNotification(
            $adminId,
            $tontineId,
            'urgence_validee',
            'Nouvelle demande d\'urgence',
            'Un membre a soumis une demande d\'urgence. Veuillez l\'examiner.',
            APP_URL . '/pages/urgences/valider.php?tontine=' . $tontineId
        );
    }

    return ['succes' => true, 'message' => 'Votre demande d\'urgence a été soumise.'];
}

// Lister les urgences d'une tontine
function listerUrgences(int $tontineId, string $statut = ''): array {
    $bd  = connexionBD();
    $sql = 'SELECT du.*, u.nom_complet AS nom_demandeur FROM demandes_urgence du JOIN utilisateurs u ON u.id = du.demandeur_id WHERE du.tontine_id = ?';
    $params = [$tontineId];
    if ($statut !== '') { $sql .= ' AND du.statut = ?'; $params[] = $statut; }
    $sql .= ' ORDER BY du.date_demande DESC';
    $req = $bd->prepare($sql);
    $req->execute($params);
    return $req->fetchAll();
}

// Valider une urgence
function validerUrgence(int $urgenceId, int $adminId, string $modeTraitement, string $commentaire = ''): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT * FROM demandes_urgence WHERE id = ?');
    $req->execute([$urgenceId]);
    $urgence = $req->fetch();
    if (!$urgence) return ['succes' => false, 'message' => 'Demande introuvable.'];

    $bd->beginTransaction();
    try {
        $bd->prepare("UPDATE demandes_urgence SET statut = 'validee', mode_traitement = ?, decision_admin_id = ?, date_decision = NOW(), commentaire_admin = ? WHERE id = ?")
          ->execute([$modeTraitement, $adminId, $commentaire, $urgenceId]);

        if ($modeTraitement === 'immediat') {
            $cycle = cycleEnCoursSimple((int)$urgence['tontine_id']);
            if ($cycle) {
                $ancienBeneficiaireId  = (int)$cycle['beneficiaire_id'];
                $nouveauBeneficiaireId = (int)$urgence['demandeur_id'];

                if ($ancienBeneficiaireId !== $nouveauBeneficiaireId) {
                    // Le demandeur d'urgence devient le bénéficiaire de CE cycle, à la
                    // place du bénéficiaire initialement prévu. Celui-ci n'est pas lésé :
                    // le passage en tête de l'ordre (plus bas) le place juste après le
                    // demandeur, donc il recevra automatiquement le cycle suivant.
                    $bd->prepare('UPDATE cycles_cotisation SET beneficiaire_id = ? WHERE id = ?')
                       ->execute([$nouveauBeneficiaireId, $cycle['id']]);

                    // Supprimer une éventuelle distribution déjà créée pour l'ancien
                    // bénéficiaire sur ce cycle (ex. s'il avait choisi « attendre ») :
                    // sans cela, deux lignes de distribution concurrentes pour le même
                    // cycle pouvaient exister et se disputer la même cagnotte collectée.
                    $bd->prepare('DELETE FROM distributions WHERE tontine_id = ? AND cycle_id = ? AND beneficiaire_id = ?')
                       ->execute([$urgence['tontine_id'], $cycle['id'], $ancienBeneficiaireId]);
                }

                // Réutiliser une distribution existante pour ce bénéficiaire sur ce
                // cycle si elle existe déjà, sinon en créer une - avec le vrai montant
                // théorique du CYCLE (nombre de membres × cotisation), pas une seule
                // cotisation comme le faisait l'ancienne version de ce fichier.
                $montantTheorique = (float)$cycle['cagnotte_theorique'];
                $montantDispo     = cagnotteCollectee((int)$cycle['id']);

                $reqDistrib = $bd->prepare('SELECT id FROM distributions WHERE tontine_id = ? AND cycle_id = ? AND beneficiaire_id = ?');
                $reqDistrib->execute([$urgence['tontine_id'], $cycle['id'], $nouveauBeneficiaireId]);
                $distribExistante = $reqDistrib->fetch();

                if ($distribExistante) {
                    $distribId = (int)$distribExistante['id'];
                } else {
                    $bd->prepare('INSERT INTO distributions (tontine_id, cycle_id, beneficiaire_id, montant_prevu, choix_beneficiaire) VALUES (?, ?, ?, ?, ?)')
                      ->execute([$urgence['tontine_id'], $cycle['id'], $nouveauBeneficiaireId, $montantTheorique, 'recevoir_maintenant']);
                    $distribId = (int)$bd->lastInsertId();
                }

                distribuerCagnotte((int)$urgence['tontine_id'], (int)$cycle['id'], $nouveauBeneficiaireId, $distribId, $montantDispo, $montantTheorique);
            }
        }

        //  Le demandeur passe en premier (quel que soit le mode)
        $tId = (int)$urgence['tontine_id'];
        $uId = (int)$urgence['demandeur_id'];

        $reqOrdre = $bd->prepare('SELECT ordre_tour FROM membres_tontine WHERE tontine_id = ? AND utilisateur_id = ?');
        $reqOrdre->execute([$tId, $uId]);
        $ordreActuel = (int)$reqOrdre->fetchColumn();

        if ($ordreActuel > 1) {
            $bd->prepare('UPDATE membres_tontine SET ordre_tour = ordre_tour + 1 WHERE tontine_id = ? AND ordre_tour < ? AND ordre_tour > 0')
               ->execute([$tId, $ordreActuel]);
            $bd->prepare('UPDATE membres_tontine SET ordre_tour = 1 WHERE tontine_id = ? AND utilisateur_id = ?')
               ->execute([$tId, $uId]);
        }

        $bd->commit();
    } catch (Throwable $e) {
        if ($bd->inTransaction()) $bd->rollBack();
        return ['succes' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }

    $tIdUrg = (int)$urgence['tontine_id'];
    $cycleEncours = cycleEnCoursSimple($tIdUrg);

    //  ENVOI DE L'EMAIL AU DEMANDEUR
    require_once __DIR__ . '/mail.php';
    
    // Récupérer les infos du demandeur
    $reqDemandeur = $bd->prepare('SELECT nom_complet, email FROM utilisateurs WHERE id = ?');
    $reqDemandeur->execute([$urgence['demandeur_id']]);
    $demandeur = $reqDemandeur->fetch();
    
    // Récupérer le nom de la tontine
    $reqTontine = $bd->prepare('SELECT nom FROM tontines WHERE id = ?');
    $reqTontine->execute([$tIdUrg]);
    $tontineNom = $reqTontine->fetchColumn();
    
    if ($demandeur) {
        envoyerEmailUrgenceValidee(
            $demandeur['email'],
            $demandeur['nom_complet'],
            $tontineNom,
            $tIdUrg,
            $cycleEncours ? (int)$cycleEncours['id'] : 0,
            $modeTraitement
        );
    }

    // Notification interne
    if ($modeTraitement === 'immediat') {
        $lien = ($cycleEncours !== null)
            ? APP_URL . '/pages/cagnotte/recevoir.php?tontine=' . $tIdUrg . '&cycle=' . (int)$cycleEncours['id']
            : APP_URL . '/pages/cagnotte/etat.php?tontine=' . $tIdUrg;
        $titre = 'Urgence validée - retrait maintenant';
        $texte = 'Votre demande d\'urgence a été acceptée. Vous pouvez retirer la cagnotte tout de suite.';
    } else {
        $lien = APP_URL . '/pages/tontines/voir.php?id=' . $tIdUrg;
        $titre = 'Urgence validée - prochain tour';
        $texte = 'Votre demande d\'urgence a été acceptée. Vous serez le prochain bénéficiaire.';
    }

    creerNotification((int)$urgence['demandeur_id'], $tIdUrg, 'urgence_validee', $titre, $texte, $lien);

    require_once __DIR__ . '/logs.php';
    ajouterLog('urgence_validee', $adminId, 'Urgence validée (mode : ' . $modeTraitement . ') pour l\'utilisateur #' . $urgence['demandeur_id'], $tIdUrg);

    return ['succes' => true, 'message' => 'Urgence validée.'];
}

// Helpers internes
function obtenirAdminTontine(int $tontineId): ?int {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT utilisateur_id FROM membres_tontine WHERE tontine_id = ? AND role = 'admin' LIMIT 1");
    $req->execute([$tontineId]);
    $ligne = $req->fetch();
    return $ligne ? (int)$ligne['utilisateur_id'] : null;
}

function cycleEnCoursSimple(int $tontineId): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT * FROM cycles_cotisation WHERE tontine_id = ? AND statut = 'en_cours' ORDER BY numero_cycle DESC LIMIT 1");
    $req->execute([$tontineId]);
    return $req->fetch() ?: null;
}

function obtenirTontineInfos(int $id): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT * FROM tontines WHERE id = ?');
    $req->execute([$id]);
    return $req->fetch() ?: null;
}