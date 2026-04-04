<?php
/**
 * ============================================================
 * fonctions/tontine.php
 *
 * Toutes les opérations CRUD et logique métier des tontines :
 *   - Créer / lire / modifier / fermer / supprimer une tontine
 *   - Cycle de vie : brouillon → active → suspendue → fermée
 *   - Démarrage (effectif complet requis) + tirage au sort de l'ordre
 *   - Statistiques (membres, retards, cagnotte nette, urgences)
 *   - Gestion des tours : détection fin de tour, nouveau tour
 *
 * DÉPENDANCES :
 *   configuration/base_de_donnees.php  — connexionBD()
 *   configuration/constantes.php       — TONTINE_*, ROLE_*, STATUT_*
 *   fonctions/membre.php               — tirageAuSortOrdre()
 *   fonctions/notification.php         — notifierTousMembres()
 *   fonctions/logs.php                 — ajouterLog()
 * ============================================================
 */

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';

// ─── CRÉATION ────────────────────────────────────────────────────────────────

/**
 * Crée une tontine en statut BROUILLON.
 * Le créateur est automatiquement ajouté comme ADMIN (ordre_tour = 1).
 * L'ordre sera redistribué par tirage au sort lors du démarrage.
 */
function creerTontine(
    int $createurId,
    string $nom,
    string $description,
    float $montant,
    string $frequence,
    int $nbMaxMembres,
    ?int $echeanceJourSemaine,
    ?int $echeanceJourMois
): array {
    $bd = connexionBD();

    $req = $bd->prepare('INSERT INTO tontines (nom, description, montant_cotisation, frequence, echeance_jour_semaine, echeance_jour_mois, nombre_max_membres, createur_id, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $req->execute([$nom, $description, $montant, $frequence, $echeanceJourSemaine, $echeanceJourMois, $nbMaxMembres, $createurId, TONTINE_BROUILLON]);
    $tontineId = (int)$bd->lastInsertId();

    // Le créateur devient automatiquement admin avec ordre_tour = 1
    $bd->prepare('INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour) VALUES (?, ?, ?, 1)')
       ->execute([$tontineId, $createurId, ROLE_ADMIN]);

    return ['succes' => true, 'tontine_id' => $tontineId];
}

// ─── LECTURE ─────────────────────────────────────────────────────────────────

/**
 * Récupère une tontine par son ID (avec nom du créateur via JOIN).
 * Retourne null si introuvable.
 */
function obtenirTontine(int $id): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT t.*, u.nom_complet AS nom_createur FROM tontines t JOIN utilisateurs u ON u.id = t.createur_id WHERE t.id = ?');
    $req->execute([$id]);
    return $req->fetch() ?: null;
}

/**
 * Liste toutes les tontines (vue super-admin).
 * Inclut le nombre de membres et le nom du créateur.
 */
function listerToutesTontines(): array {
    $bd  = connexionBD();
    $req = $bd->query('SELECT t.*, u.nom_complet AS nom_createur, (SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = t.id) AS nb_membres FROM tontines t JOIN utilisateurs u ON u.id = t.createur_id ORDER BY t.date_creation DESC');
    return $req->fetchAll();
}

/**
 * Liste les tontines dont l'utilisateur est ADMIN (hors fermées).
 * Affiche les tontines qu'il gère.
 */
function tontinesAdminDe(int $utilisateurId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT t.*, (SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = t.id) AS nb_membres FROM tontines t JOIN membres_tontine mt ON mt.tontine_id = t.id WHERE mt.utilisateur_id = ? AND mt.role = ? AND t.statut != ? ORDER BY t.date_creation DESC');
    $req->execute([$utilisateurId, ROLE_ADMIN, TONTINE_FERMEE]);
    return $req->fetchAll();
}

/**
 * Liste les tontines dont l'utilisateur est MEMBRE simple (hors fermées).
 */
function tontinesMembreDe(int $utilisateurId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT t.*, (SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = t.id) AS nb_membres FROM tontines t JOIN membres_tontine mt ON mt.tontine_id = t.id WHERE mt.utilisateur_id = ? AND mt.role = ? AND t.statut != ? ORDER BY t.date_creation DESC');
    $req->execute([$utilisateurId, ROLE_MEMBRE, TONTINE_FERMEE]);
    return $req->fetchAll();
}

// ─── MODIFICATION / SUPPRESSION ──────────────────────────────────────────────

/**
 * Met à jour les informations d'une tontine.
 * Idéalement à faire avant démarrage ou entre deux cycles.
 */
function modifierTontine(
    int $id,
    string $nom,
    string $description,
    float $montant,
    string $frequence,
    int $nbMaxMembres,
    ?int $echeanceJourSemaine,
    ?int $echeanceJourMois
): array {
    $bd  = connexionBD();
    $req = $bd->prepare('UPDATE tontines SET nom = ?, description = ?, montant_cotisation = ?, frequence = ?, echeance_jour_semaine = ?, echeance_jour_mois = ?, nombre_max_membres = ? WHERE id = ?');
    $req->execute([$nom, $description, $montant, $frequence, $echeanceJourSemaine, $echeanceJourMois, $nbMaxMembres, $id]);
    return ['succes' => true, 'message' => 'Tontine mise à jour.'];
}

/**
 * Ferme définitivement une tontine (statut → 'fermee').
 * Enregistre la date de fermeture et un log d'audit.
 */
function fermerTontine(int $id): void {
    $bd = connexionBD();
    $bd->prepare('UPDATE tontines SET statut = ?, date_fermeture = NOW() WHERE id = ?')
       ->execute([TONTINE_FERMEE, $id]);

    require_once __DIR__ . '/../configuration/session.php';
    require_once __DIR__ . '/logs.php';
    ajouterLog('fermeture_tontine', idUtilisateurConnecte(), 'Tontine fermée', $id);
}

/**
 * Supprime physiquement une tontine (super-admin).
 * Cascade sur toutes les tables liées.
 */
function supprimerTontine(int $id): void {
    $bd = connexionBD();
    $bd->prepare('DELETE FROM tontines WHERE id = ?')->execute([$id]);
}

// ─── VÉRIFICATIONS D'ACCÈS ───────────────────────────────────────────────────

/**
 * Retourne true si l'utilisateur est ADMIN de la tontine.
 * Guard utilisé avant les actions sensibles (démarrage, lancement cycle…).
 */
function estAdminDeTontine(int $utilisateurId, int $tontineId): bool {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT id FROM membres_tontine WHERE utilisateur_id = ? AND tontine_id = ? AND role = ?');
    $req->execute([$utilisateurId, $tontineId, ROLE_ADMIN]);
    return (bool)$req->fetch();
}

/**
 * Retourne true si l'utilisateur est membre actif de la tontine (hors exclus).
 * Guard utilisé avant un paiement, une urgence ou un vote.
 */
function estMembreDeTontine(int $utilisateurId, int $tontineId): bool {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT id FROM membres_tontine WHERE utilisateur_id = ? AND tontine_id = ? AND statut != ?');
    $req->execute([$utilisateurId, $tontineId, STATUT_EXCLU]);
    return (bool)$req->fetch();
}

/**
 * Redirige vers l'accueil si l'utilisateur n'est pas admin.
 * À placer en tête des pages admin.
 */
function exigerAdminTontine(int $utilisateurId, int $tontineId): void {
    if (!estAdminDeTontine($utilisateurId, $tontineId)) {
        header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
        exit;
    }
}

/**
 * Retourne le rôle de l'utilisateur dans la tontine ('admin' | 'membre' | null).
 */
function rolesDansTontine(int $utilisateurId, int $tontineId): ?string {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT role FROM membres_tontine WHERE utilisateur_id = ? AND tontine_id = ?');
    $req->execute([$utilisateurId, $tontineId]);
    $ligne = $req->fetch();
    return $ligne ? $ligne['role'] : null;
}

// ─── STATISTIQUES ────────────────────────────────────────────────────────────

/**
 * Calcule les statistiques clés d'une tontine (tableau de bord admin).
 *
 * Calcul du solde net de la cagnotte :
 *   solde_net = SUM(paiements confirmés) − SUM(distributions reçues)
 *   Représente ce qui reste réellement dans la caisse commune.
 *
 * @return array {nb_membres, nb_retards, cagnotte_totale, cagnotte_brut,
 *               cagnotte_deja_distribue, urgences_attente}
 */
function statistiquesTontine(int $tontineId): array {
    $bd = connexionBD();

    $membres = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $membres->execute([$tontineId, STATUT_EXCLU]);

    $retards = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut = ?');
    $retards->execute([$tontineId, STATUT_RETARD]);

    $cagnotte = $bd->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE tontine_id = ? AND statut = 'paye'");
    $cagnotte->execute([$tontineId]);
    $brutCotisations = (float)$cagnotte->fetchColumn();

    $urgences = $bd->prepare("SELECT COUNT(*) FROM demandes_urgence WHERE tontine_id = ? AND statut = 'en_attente'");
    $urgences->execute([$tontineId]);

    require_once __DIR__ . '/cagnotte.php';
    $dejaDistribue = montantTotalDistribueTontine($tontineId);
    $soldeNet      = max(0.0, $brutCotisations - $dejaDistribue);

    return [
        'nb_membres'              => (int)$membres->fetchColumn(),
        'nb_retards'              => (int)$retards->fetchColumn(),
        'cagnotte_totale'         => $soldeNet,
        'cagnotte_brut'           => $brutCotisations,
        'cagnotte_deja_distribue' => $dejaDistribue,
        'urgences_attente'        => (int)$urgences->fetchColumn(),
    ];
}

/**
 * Nombre de membres actifs (hors exclus).
 * Utilisé pour calculer la cagnotte théorique d'un cycle.
 */
function nombreMembresActifs(int $tontineId): int {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = ? AND statut != ?');
    $req->execute([$tontineId, STATUT_EXCLU]);
    return (int)$req->fetchColumn();
}

/**
 * Retourne true si l'effectif atteint le maximum prévu.
 * Condition pour démarrer l'activité de la tontine.
 */
function tontineEffectifComplet(array $tontine): bool {
    return nombreMembresActifs((int)$tontine['id']) >= (int)$tontine['nombre_max_membres'];
}

/**
 * Retourne true si la tontine est en statut 'active'.
 * Guard avant d'autoriser paiement, cycle, etc.
 */
function tontineAccepteOperations(array $tontine): bool {
    return ($tontine['statut'] ?? '') === TONTINE_ACTIVE;
}

// ─── CYCLE DE VIE ────────────────────────────────────────────────────────────

/**
 * Démarre la tontine (brouillon → active).
 *
 * Conditions requises :
 *   1. L'appelant est admin
 *   2. Statut = BROUILLON
 *   3. Effectif complet
 *
 * Effets :
 *   - Statut → 'active'
 *   - Tirage au sort de l'ordre des tours (garantit l'équité)
 *   - Notification à tous les membres
 *   - Log d'audit
 */
function demarrerActiviteTontine(int $tontineId, int $adminId): array {
    if (!estAdminDeTontine($adminId, $tontineId)) {
        return ['succes' => false, 'message' => "Action r\u00e9serv\u00e9e \u00e0 l'administrateur."];
    }
    $t = obtenirTontine($tontineId);
    if (!$t) {
        return ['succes' => false, 'message' => 'Tontine introuvable.'];
    }
    if (($t['statut'] ?? '') !== TONTINE_BROUILLON) {
        return ['succes' => false, 'message' => "Cette tontine n'est pas en pr\u00e9paration."];
    }
    if (!tontineEffectifComplet($t)) {
        return ['succes' => false, 'message' => "L'effectif doit \u00eatre au complet (nombre max de membres atteint) avant de d\u00e9marrer."];
    }
    $bd = connexionBD();
    $bd->prepare('UPDATE tontines SET statut = ? WHERE id = ?')->execute([TONTINE_ACTIVE, $tontineId]);

    // Tirage au sort : l'ordre d'arrivée ne doit pas déterminer qui reçoit en
    // premier → on tire au sort dès que le groupe est complet.
    require_once __DIR__ . '/membre.php';
    tirageAuSortOrdre($tontineId);

    require_once __DIR__ . '/notification.php';
    notifierTousMembres(
        $tontineId,
        'tontine_demarree',
        'La tontine démarre !',
        "L'effectif est complet. L'ordre des tours a \u00e9t\u00e9 tir\u00e9 au sort pour garantir l'\u00e9quit\u00e9 entre tous les membres.",
        APP_URL . '/pages/membres/ordre_des_tours.php?tontine=' . $tontineId
    );

    require_once __DIR__ . '/logs.php';
    ajouterLog('demarrage_tontine', $adminId, 'Activité démarrée, ordre tiré au sort', $tontineId);

    return ['succes' => true, 'message' => "La tontine est maintenant en cours. L'ordre des tours a \u00e9t\u00e9 tir\u00e9 au sort. Vous pouvez lancer un cycle de cotisation."];
}

/**
 * Suspend temporairement une tontine (active → suspendue).
 * Bloque cotisations et nouveaux cycles jusqu'à réactivation.
 */
function arreterActiviteTontine(int $tontineId, int $adminId): array {
    if (!estAdminDeTontine($adminId, $tontineId)) {
        return ['succes' => false, 'message' => "Action r\u00e9serv\u00e9e \u00e0 l'administrateur."];
    }
    $t = obtenirTontine($tontineId);
    if (!$t) {
        return ['succes' => false, 'message' => 'Tontine introuvable.'];
    }
    if (($t['statut'] ?? '') !== TONTINE_ACTIVE) {
        return ['succes' => false, 'message' => 'Seule une tontine en cours peut être arrêtée temporairement.'];
    }
    $bd = connexionBD();
    $bd->prepare('UPDATE tontines SET statut = ? WHERE id = ?')->execute([TONTINE_SUSPENDUE, $tontineId]);

    return ['succes' => true, 'message' => 'La tontine est arrêtée. Les cotisations et nouveaux cycles sont suspendus.'];
}

/**
 * Réactive une tontine suspendue (suspendue → active).
 */
function reactiverActiviteTontine(int $tontineId, int $adminId): array {
    if (!estAdminDeTontine($adminId, $tontineId)) {
        return ['succes' => false, 'message' => "Action r\u00e9serv\u00e9e \u00e0 l'administrateur."];
    }
    $t = obtenirTontine($tontineId);
    if (!$t) {
        return ['succes' => false, 'message' => 'Tontine introuvable.'];
    }
    if (($t['statut'] ?? '') !== TONTINE_SUSPENDUE) {
        return ['succes' => false, 'message' => 'Seule une tontine arrêtée peut être réactivée.'];
    }
    $bd = connexionBD();
    $bd->prepare('UPDATE tontines SET statut = ? WHERE id = ?')->execute([TONTINE_ACTIVE, $tontineId]);

    return ['succes' => true, 'message' => 'La tontine est de nouveau en cours.'];
}

// ─── GESTION DES TOURS COMPLETS ───────────────────────────────────────────────
//
// DÉFINITION :
//   Un tour = chaque membre actif reçoit la cagnotte exactement une fois.
//   Exemple : 4 membres → 4 cycles distribués = 1 tour complet.
//
// MÉCANIQUE :
//   Après chaque distribution complète, verifierFinDeTour() est appelée.
//   Elle incrémente le compteur cycles_completes_ce_tour.
//   Quand il atteint nb_membres_actifs :
//     → compteur remis à 0
//     → numero_tour_actuel + 1
//     → tour_complet_en_attente = 1 (bloque le prochain cycle)
//     → notification à l'admin
//   L'admin choisit ensuite : nouveau tour ou fermeture de la tontine.

/**
 * Vérifie si le tour est terminé après qu'un cycle a été distribué.
 * À appeler immédiatement après marquerCycleDistribue() dans distribuerCagnotte().
 */
function verifierFinDeTour(int $tontineId): void {
    $bd = connexionBD();

    $req = $bd->prepare('SELECT cycles_completes_ce_tour, numero_tour_actuel FROM tontines WHERE id = ?');
    $req->execute([$tontineId]);
    $t = $req->fetch();
    if (!$t) return;

    $nbMembresActifs = nombreMembresActifs($tontineId);
    if ($nbMembresActifs <= 0) return;

    $compteur = (int)$t['cycles_completes_ce_tour'] + 1;

    if ($compteur >= $nbMembresActifs) {
        // Tour complet : réinitialiser le compteur, incrémenter le numéro de
        // tour, bloquer le démarrage du prochain cycle.
        $bd->prepare('UPDATE tontines SET cycles_completes_ce_tour = 0, numero_tour_actuel = numero_tour_actuel + 1, tour_complet_en_attente = 1 WHERE id = ?')
           ->execute([$tontineId]);

        require_once __DIR__ . '/notification.php';
        $req = $bd->prepare("SELECT utilisateur_id FROM membres_tontine WHERE tontine_id = ? AND role = 'admin' LIMIT 1");
        $req->execute([$tontineId]);
        $adminId = $req->fetchColumn();

        // Notifier l'admin pour qu'il prenne une décision (nouveau tour / fermeture)
        if ($adminId) {
            creerNotification(
                (int)$adminId,
                $tontineId,
                'tour_complet',
                '🎉 Tour complet - décision attendue',
                "Tous les membres ont re\u00e7u la cagnotte une fois. Lancez un nouveau tour (avec un nouveau tirage au sort de l'ordre) ou fermez la tontine.",
                APP_URL . '/pages/tontines/nouveau_tour.php?id=' . $tontineId
            );
        }
        // Notifier tous les membres
        notifierTousMembres(
            $tontineId,
            'tour_complet',
            'Tour complet !',
            "Tous les membres ont re\u00e7u la cagnotte une fois sur ce tour. L'administrateur va d\u00e9cider de la suite (nouveau tour ou fermeture).",
            APP_URL . '/pages/tontines/voir.php?id=' . $tontineId
        );

        require_once __DIR__ . '/logs.php';
        ajouterLog('tour_complet', (int)($adminId ?: 0), 'Tour n°' . (int)$t['numero_tour_actuel'] . ' terminé', $tontineId);
    } else {
        // Tour pas encore complet : incrémenter le compteur seulement
        $bd->prepare('UPDATE tontines SET cycles_completes_ce_tour = ? WHERE id = ?')
           ->execute([$compteur, $tontineId]);
    }
}

/**
 * Lance un nouveau tour après la fin du tour précédent.
 *
 * Condition : tour_complet_en_attente = 1
 *
 * Effets :
 *   - Nouveau tirage au sort de l'ordre des tours
 *   - tour_complet_en_attente → 0 (déverrouille le prochain cycle)
 *   - Notification à tous les membres
 *   - Log d'audit
 */
function lancerNouveauTour(int $tontineId, int $adminId): array {
    if (!estAdminDeTontine($adminId, $tontineId)) {
        return ['succes' => false, 'message' => "Action r\u00e9serv\u00e9e \u00e0 l'administrateur."];
    }
    $t = obtenirTontine($tontineId);
    if (!$t) {
        return ['succes' => false, 'message' => 'Tontine introuvable.'];
    }
    if (empty($t['tour_complet_en_attente'])) {
        return ['succes' => false, 'message' => 'Aucun tour complet en attente pour cette tontine.'];
    }

    // Nouveau tirage au sort pour garantir l'équité entre les tours
    require_once __DIR__ . '/membre.php';
    tirageAuSortOrdre($tontineId);

    $bd = connexionBD();
    $bd->prepare('UPDATE tontines SET tour_complet_en_attente = 0 WHERE id = ?')->execute([$tontineId]);

    require_once __DIR__ . '/notification.php';
    notifierTousMembres(
        $tontineId,
        'tontine_demarree',
        'Nouveau tour lancé',
        "Un nouveau tour commence. Un nouvel ordre a \u00e9t\u00e9 tir\u00e9 au sort pour garantir l'\u00e9quit\u00e9.",
        APP_URL . '/pages/membres/ordre_des_tours.php?tontine=' . $tontineId
    );

    require_once __DIR__ . '/logs.php';
    ajouterLog('nouveau_tour', $adminId, 'Nouveau tour lancé, ordre retiré au sort', $tontineId);

    return ['succes' => true, 'message' => "Nouveau tour lanc\u00e9 : l'ordre a \u00e9t\u00e9 retir\u00e9 au sort. Vous pouvez d\u00e9marrer un nouveau cycle."];
}
