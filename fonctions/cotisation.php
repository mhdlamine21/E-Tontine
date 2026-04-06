<?php
/**
 * ============================================================
 * fonctions/cotisation.php
 *
 * Gestion des cycles de cotisation de la plateforme E-Tontine :
 *   - Création et cycle de vie des cycles (créé → en_cours → distribué / clos)
 *   - Détection des cotisations payées / impayées
 *   - Calcul et synchronisation de la cagnotte collectée
 *   - Historique et requêtes de suivi pour les membres et admins
 *
 * DÉPENDANCES :
 *   configuration/base_de_donnees.php  — connexionBD()
 *   configuration/constantes.php       — CYCLE_EN_COURS, STATUT_EXCLU, etc.
 * ============================================================
 */

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';

/**
 * Crée un nouveau cycle de cotisation pour une tontine.
 *
 * @param int    $tontineId          ID de la tontine
 * @param int    $numeroCycle        Numéro séquentiel du cycle (1, 2, 3...)
 * @param string $dateDebut          Date de démarrage du cycle (AAAA-MM-JJ)
 * @param string $dateLimite         Date limite d'échéance de paiement
 * @param int    $beneficiaireId     ID du membre bénéficiaire désigné
 * @param float  $cagnotteTheorique  Montant cible attendu (cotisation × nb membres)
 * @return int                       ID du cycle inséré
 */
function creerCycle(int $tontineId, int $numeroCycle, string $dateDebut, string $dateLimite, int $beneficiaireId, float $cagnotteTheorique): int {
    $bd  = connexionBD();
    $req = $bd->prepare('INSERT INTO cycles_cotisation (tontine_id, numero_cycle, date_debut, date_limite, beneficiaire_id, cagnotte_theorique, statut) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $req->execute([$tontineId, $numeroCycle, $dateDebut, $dateLimite, $beneficiaireId, $cagnotteTheorique, CYCLE_EN_COURS]);
    return (int)$bd->lastInsertId();
}

/**
 * Récupère le cycle actuellement actif (statut 'en_cours') d'une tontine.
 * Trie par numéro de cycle décroissant pour cibler le cycle le plus récent.
 *
 * @param int $tontineId ID de la tontine
 * @return array|null    Données du cycle ou null si aucun cycle actif
 */
function cycleEnCours(int $tontineId): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT * FROM cycles_cotisation WHERE tontine_id = ? AND statut = 'en_cours' ORDER BY numero_cycle DESC LIMIT 1");
    $req->execute([$tontineId]);
    return $req->fetch() ?: null;
}

// Lister tous les cycles d'une tontine
function listerCycles(int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT cc.*, u.nom_complet AS nom_beneficiaire FROM cycles_cotisation cc LEFT JOIN utilisateurs u ON u.id = cc.beneficiaire_id WHERE cc.tontine_id = ? ORDER BY cc.numero_cycle DESC');
    $req->execute([$tontineId]);
    return $req->fetchAll();
}

// Vérifier si un membre a déjà payé pour un cycle
function aDejaPayePourCycle(int $tontineId, int $cycleId, int $utilisateurId): bool {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT id FROM paiements WHERE tontine_id = ? AND cycle_id = ? AND payeur_id = ? AND statut = 'paye'");
    $req->execute([$tontineId, $cycleId, $utilisateurId]);
    return (bool)$req->fetch();
}

// Obtenir les paiements d'un cycle
function paiementsDuCycle(int $tontineId, int $cycleId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT p.*, u.nom_complet AS nom_payeur, ub.nom_complet AS nom_beneficiaire FROM paiements p JOIN utilisateurs u ON u.id = p.payeur_id JOIN utilisateurs ub ON ub.id = p.beneficiaire_id WHERE p.tontine_id = ? AND p.cycle_id = ? ORDER BY p.date_paiement DESC');
    $req->execute([$tontineId, $cycleId]);
    return $req->fetchAll();
}

// Mettre à jour la cagnotte collectée d'un cycle
function majCagnotteCollectee(int $cycleId): void {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE cycle_id = ? AND statut = 'paye'");
    $req->execute([$cycleId]);
    $montant = (float)$req->fetchColumn();
    $bd->prepare('UPDATE cycles_cotisation SET cagnotte_collectee = ? WHERE id = ?')
       ->execute([$montant, $cycleId]);
}

// Calculer le montant collecté d'un cycle
function cagnotteCollectee(int $cycleId): float {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE cycle_id = ? AND statut = 'paye'");
    $req->execute([$cycleId]);
    return (float)$req->fetchColumn();
}

// Calculer le prochain numéro de cycle
function prochainNumeroCycle(int $tontineId): int {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT COALESCE(MAX(numero_cycle), 0) + 1 FROM cycles_cotisation WHERE tontine_id = ?');
    $req->execute([$tontineId]);
    return (int)$req->fetchColumn();
}

// Clore un cycle
function cloreCycle(int $cycleId): void {
    $bd = connexionBD();
    $bd->prepare("UPDATE cycles_cotisation SET statut = 'clos' WHERE id = ?")
       ->execute([$cycleId]);
}

// Marquer cycle distribué
function marquerCycleDistribue(int $cycleId): void {
    $bd = connexionBD();
    $bd->prepare("UPDATE cycles_cotisation SET statut = 'distribue', date_distribution = CURDATE() WHERE id = ?")
       ->execute([$cycleId]);
}

// Historique des paiements d'un membre
function historiquePaiementsMembre(int $utilisateurId, int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT p.*, cc.numero_cycle, cc.date_limite, ub.nom_complet AS nom_beneficiaire FROM paiements p JOIN cycles_cotisation cc ON cc.id = p.cycle_id JOIN utilisateurs ub ON ub.id = p.beneficiaire_id WHERE p.payeur_id = ? AND p.tontine_id = ? ORDER BY p.date_paiement DESC');
    $req->execute([$utilisateurId, $tontineId]);
    return $req->fetchAll();
}

// Membres n'ayant pas encore payé le cycle en cours
function membresNonPayes(int $tontineId, int $cycleId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT mt.utilisateur_id, u.nom_complet, u.telephone, mt.statut FROM membres_tontine mt JOIN utilisateurs u ON u.id = mt.utilisateur_id WHERE mt.tontine_id = ? AND mt.statut != ? AND mt.utilisateur_id NOT IN (SELECT payeur_id FROM paiements WHERE tontine_id = ? AND cycle_id = ?)');
    $req->execute([$tontineId, STATUT_EXCLU, $tontineId, $cycleId]);
    return $req->fetchAll();
}

// Vérifier si la cagnotte est complète
function cagnotteEstComplete(int $tontineId, int $cycleId): bool {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT cagnotte_theorique, cagnotte_collectee FROM cycles_cotisation WHERE id = ?');
    $req->execute([$cycleId]);
    $cycle = $req->fetch();
    if (!$cycle) return false;
    return (float)$cycle['cagnotte_collectee'] >= (float)$cycle['cagnotte_theorique'];
}

// Récupérer un cycle par son ID
function obtenirCycleSimple(int $id): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT * FROM cycles_cotisation WHERE id = ?');
    $req->execute([$id]);
    return $req->fetch() ?: null;
}
