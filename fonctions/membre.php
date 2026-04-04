<?php
// Fonctions liées aux membres des tontines.

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';


function obtenirTontineSimple(int $id): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT * FROM tontines WHERE id = ?');
    $req->execute([$id]);
    return $req->fetch() ?: null;
}

// Retourne les membres d'une tontine, hors membres exclus.
function listerMembres(int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('
        SELECT mt.*, u.nom_complet, u.email, u.telephone
        FROM membres_tontine mt
        JOIN utilisateurs u ON u.id = mt.utilisateur_id
        WHERE mt.tontine_id = ? AND mt.statut != ?
        ORDER BY mt.ordre_tour ASC
    ');
    $req->execute([$tontineId, STATUT_EXCLU]);
    return $req->fetchAll();
}

// Ajouter un membre par email
function ajouterMembreParEmail(int $tontineId, string $email): array {
    $bd = connexionBD();

    // Geler l'effectif pendant un cycle actif : la cagnotte théorique du cycle
    // (nombre de membres × cotisation) est figée à son démarrage. Ajouter un
    // membre en cours de route rendrait ce montant théorique incohérent avec
    // ce qui a réellement été annoncé aux membres qui cotisent déjà.
    require_once __DIR__ . '/cotisation.php';
    if (cycleEnCours($tontineId)) {
        return ['succes' => false, 'message' => 'Impossible d\'ajouter un membre pendant qu\'un cycle de cotisation est en cours. Attendez la fin du cycle actuel (ou sa distribution complète).'];
    }

    // Vérifier que l'utilisateur existe
    $req = $bd->prepare('SELECT id FROM utilisateurs WHERE email = ? AND est_bloque = 0');
    $req->execute([$email]);
    $utilisateur = $req->fetch();
    if (!$utilisateur) {
        return ['succes' => false, 'message' => 'Aucun compte actif trouvé avec cet email.'];
    }

    // Vérifier qu'il n'est pas déjà membre
    $req = $bd->prepare('SELECT id FROM membres_tontine WHERE tontine_id = ? AND utilisateur_id = ?');
    $req->execute([$tontineId, $utilisateur['id']]);
    if ($req->fetch()) {
        return ['succes' => false, 'message' => 'Cet utilisateur est déjà membre de cette tontine.'];
    }

    // Vérifier la limite de membres
    $tontine = obtenirTontineSimple($tontineId);
    $req     = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $req->execute([$tontineId, STATUT_EXCLU]);
    $nbActuels = (int)$req->fetchColumn();
    if ($nbActuels >= $tontine['nombre_max_membres']) {
        return ['succes' => false, 'message' => 'La tontine a atteint son nombre maximum de membres.'];
    }

    // Déterminer l'ordre du tour
    $req = $bd->prepare('SELECT COALESCE(MAX(ordre_tour), 0) + 1 FROM membres_tontine WHERE tontine_id = ?');
    $req->execute([$tontineId]);
    $prochainOrdre = (int)$req->fetchColumn();

    $bd->prepare('INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour) VALUES (?, ?, ?, ?)')
       ->execute([$tontineId, $utilisateur['id'], ROLE_MEMBRE, $prochainOrdre]);

    return ['succes' => true, 'message' => 'Membre ajouté avec succès.', 'utilisateur_id' => $utilisateur['id']];
}

// Exclut un membre.
function exclureMembre(int $tontineId, int $utilisateurId): array {
    $bd = connexionBD();

    // Garde-fou : ne pas exclure le bénéficiaire du cycle en cours tant que sa
    // cagnotte n'est pas entièrement distribuée (sinon plus personne ne peut la recevoir).
    $req = $bd->prepare("SELECT id FROM cycles_cotisation WHERE tontine_id = ? AND beneficiaire_id = ? AND statut = 'en_cours'");
    $req->execute([$tontineId, $utilisateurId]);
    $cycleActuel = $req->fetch();
    if ($cycleActuel) {
        $req = $bd->prepare("SELECT statut FROM distributions WHERE tontine_id = ? AND cycle_id = ? AND beneficiaire_id = ?");
        $req->execute([$tontineId, $cycleActuel['id'], $utilisateurId]);
        $distrib = $req->fetch();
        if (!$distrib || $distrib['statut'] !== DISTRIB_COMPLET) {
            return ['succes' => false, 'message' => 'Impossible d\'exclure ce membre : il est bénéficiaire du cycle en cours et sa cagnotte n\'est pas encore entièrement distribuée.'];
        }
    }

    // Vérifier que ce n'est pas l'admin
    $req = $bd->prepare('SELECT role FROM membres_tontine WHERE tontine_id = ? AND utilisateur_id = ?');
    $req->execute([$tontineId, $utilisateurId]);
    $ligne = $req->fetch();
    if (!$ligne) return ['succes' => false, 'message' => 'Membre introuvable.'];
    if ($ligne['role'] === ROLE_ADMIN) return ['succes' => false, 'message' => 'Impossible d\'exclure l\'administrateur.'];

    $bd->prepare('UPDATE membres_tontine SET statut = ? WHERE tontine_id = ? AND utilisateur_id = ?')
       ->execute([STATUT_EXCLU, $tontineId, $utilisateurId]);

    require_once __DIR__ . '/logs.php';
    ajouterLog('exclusion_membre', $utilisateurId, 'Membre exclu de la tontine', $tontineId);

    return ['succes' => true, 'message' => 'Le membre a été retiré de la tontine.'];
}

// Mettre à jour l'ordre des tours
function mettreAJourOrdre(int $tontineId, array $ordreIds): void {
    $bd = connexionBD();
    foreach ($ordreIds as $position => $utilisateurId) {
        $bd->prepare('UPDATE membres_tontine SET ordre_tour = ? WHERE tontine_id = ? AND utilisateur_id = ?')
           ->execute([$position + 1, $tontineId, $utilisateurId]);
    }
}

// Tirage au sort de l'ordre
function tirageAuSortOrdre(int $tontineId): void {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT utilisateur_id FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $req->execute([$tontineId, STATUT_EXCLU]);
    $membres = $req->fetchAll(PDO::FETCH_COLUMN);
    shuffle($membres);
    mettreAJourOrdre($tontineId, $membres);
}

// Changer le statut d'un membre
function changerStatutMembre(int $tontineId, int $utilisateurId, string $statut): void {
    $bd = connexionBD();
    $bd->prepare('UPDATE membres_tontine SET statut = ? WHERE tontine_id = ? AND utilisateur_id = ?')
       ->execute([$statut, $tontineId, $utilisateurId]);
}

// Marquer les membres en retard (appelé par un cron ou à l'affichage)
function marquerMembresEnRetard(int $tontineId, int $cycleId): void {
    $bd = connexionBD();

    // Récupérer tous les membres actifs sans paiement pour ce cycle
    $req = $bd->prepare('
        SELECT mt.utilisateur_id
        FROM membres_tontine mt
        WHERE mt.tontine_id = ?
          AND mt.statut = ?
          AND mt.utilisateur_id NOT IN (
              SELECT payeur_id FROM paiements WHERE tontine_id = ? AND cycle_id = ?
          )
    ');
    $req->execute([$tontineId, STATUT_ACTIF, $tontineId, $cycleId]);
    $enRetard = $req->fetchAll(PDO::FETCH_COLUMN);

    foreach ($enRetard as $uid) {
        changerStatutMembre($tontineId, (int)$uid, STATUT_RETARD);
    }
}

// Obtenir le membre bénéficiaire du cycle en cours
function beneficiaireActuel(int $tontineId): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT mt.utilisateur_id, u.nom_complet, mt.ordre_tour FROM membres_tontine mt JOIN utilisateurs u ON u.id = mt.utilisateur_id WHERE mt.tontine_id = ? AND mt.statut = ? ORDER BY mt.ordre_tour ASC LIMIT 1');
    $req->execute([$tontineId, STATUT_ACTIF]);
    return $req->fetch() ?: null;
}

//  NOUVELLE FONCTION : Mettre un membre en dernier dans l'ordre
function mettreMembreEnDernierOrdre(int $tontineId, int $membreId): void {
    $bd = connexionBD();
    
    // Récupérer l'ordre actuel du membre
    $req = $bd->prepare('SELECT ordre_tour FROM membres_tontine WHERE tontine_id = ? AND utilisateur_id = ?');
    $req->execute([$tontineId, $membreId]);
    $ordreActuel = (int)$req->fetchColumn();
    
    if ($ordreActuel <= 0) return;
    
    // Récupérer le nombre total de membres actifs
    $req = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $req->execute([$tontineId, STATUT_EXCLU]);
    $nbMembres = (int)$req->fetchColumn();
    
    if ($ordreActuel == $nbMembres) return; // Déjà dernier
    
    // Décaler tous les membres qui sont après lui vers le haut
    $bd->prepare('UPDATE membres_tontine SET ordre_tour = ordre_tour - 1 WHERE tontine_id = ? AND ordre_tour > ? AND ordre_tour > 0')
       ->execute([$tontineId, $ordreActuel]);
    
    // Mettre le membre en dernier
    $bd->prepare('UPDATE membres_tontine SET ordre_tour = ? WHERE tontine_id = ? AND utilisateur_id = ?')
       ->execute([$nbMembres, $tontineId, $membreId]);
}