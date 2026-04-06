<?php


require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/paiement.php';
require_once __DIR__ . '/notification.php';

// A paie la cotisation de B
function payerPourAutrui(
    int    $tontineId,
    int    $cycleId,
    int    $payeurId,       // A
    int    $beneficiaireId, // B
    float  $montant,
    string $mode,
    string $telephone,
    string $note = ''
): array {
    $bd = connexionBD();

    // B ne doit pas avoir déjà payé
    if (aDejaPayePourCycle($tontineId, $cycleId, $beneficiaireId)) {
        return ['succes' => false, 'message' => 'Ce membre a déjà payé sa cotisation pour ce cycle.'];
    }

    // Enregistrer le paiement (c'est B qui est payeur dans la table paiements, car c'est sa cotisation)
    $resultat = enregistrerPaiement($tontineId, $cycleId, $beneficiaireId, $beneficiaireId, $montant, $mode, $telephone, TYPE_POUR_AUTRUI);
    if (!$resultat['succes']) return $resultat;

    // Enregistrer la trace "A a payé pour B"
    $bd->prepare('INSERT INTO paiements_pour_autrui (paiement_id, payeur_id, beneficiaire_id, tontine_id, montant, note) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$resultat['paiement_id'], $payeurId, $beneficiaireId, $tontineId, $montant, $note]);

    // Notifier B que A a payé pour lui
    $nomPayeur = obtenirNomUtilisateur($payeurId);
    creerNotification(
        $beneficiaireId,
        $tontineId,
        'paiement_pour_autrui',
        'Cotisation payée par ' . $nomPayeur,
        $nomPayeur . ' a payé votre cotisation de ' . number_format($montant, 0, ',', ' ') . ' ' . DEVISE . '. Cette information est visible dans votre historique.',
        APP_URL . '/pages/cotisations/historique.php?tontine=' . $tontineId
    );

    return [
        'succes'      => true,
        'paiement_id' => $resultat['paiement_id'],
        'reference'   => $resultat['reference'],
        'message'     => 'Paiement pour ' . obtenirNomUtilisateur($beneficiaireId) . ' enregistré avec succès.',
    ];
}

// Historique des paiements effectués par A pour d'autres membres
function paiementsEffectuesPourAutrui(int $payeurId, int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT ppa.*, p.date_paiement, p.reference, p.mode_paiement, ub.nom_complet AS nom_beneficiaire FROM paiements_pour_autrui ppa JOIN paiements p ON p.id = ppa.paiement_id JOIN utilisateurs ub ON ub.id = ppa.beneficiaire_id WHERE ppa.payeur_id = ? AND ppa.tontine_id = ? ORDER BY p.date_paiement DESC');
    $req->execute([$payeurId, $tontineId]);
    return $req->fetchAll();
}

// Historique des paiements reçus par B de la part d'autres membres
function paiementsRecusDeAutrui(int $beneficiaireId, int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT ppa.*, p.date_paiement, p.reference, p.mode_paiement, ua.nom_complet AS nom_payeur FROM paiements_pour_autrui ppa JOIN paiements p ON p.id = ppa.paiement_id JOIN utilisateurs ua ON ua.id = ppa.payeur_id WHERE ppa.beneficiaire_id = ? AND ppa.tontine_id = ? ORDER BY p.date_paiement DESC');
    $req->execute([$beneficiaireId, $tontineId]);
    return $req->fetchAll();
}

// Récupérer toutes les traces d'une tontine (admin)
function toutesTracesPourAutrui(int $tontineId): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT ppa.*, p.date_paiement, p.reference, p.mode_paiement, ua.nom_complet AS nom_payeur, ub.nom_complet AS nom_beneficiaire FROM paiements_pour_autrui ppa JOIN paiements p ON p.id = ppa.paiement_id JOIN utilisateurs ua ON ua.id = ppa.payeur_id JOIN utilisateurs ub ON ub.id = ppa.beneficiaire_id WHERE ppa.tontine_id = ? ORDER BY p.date_paiement DESC');
    $req->execute([$tontineId]);
    return $req->fetchAll();
}

// Helper interne
function obtenirNomUtilisateur(int $id): string {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT nom_complet FROM utilisateurs WHERE id = ?');
    $req->execute([$id]);
    return $req->fetchColumn() ?: 'Utilisateur inconnu';
}
