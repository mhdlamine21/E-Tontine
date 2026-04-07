<?php

declare(strict_types=1);

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/cotisation.php';
require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/aide.php';

/** Réf. interne pour dédoublonner dans le message */
function _refMessageCycleLimite(int $cycleId): string {
    return ' ref:limite:cycle:' . $cycleId . ': ';
}

function notificationJourLimiteDejaEnvoyeeAujourdhui(int $beneficiaireId, int $tontineId, int $cycleId): bool {
    $bd  = connexionBD();
    $ref = '%' . str_replace(['%', '_'], ['\\%', '\\_'], _refMessageCycleLimite($cycleId)) . '%';
    // Déduplication par référence dans le message (le type en base est souvent un ENUM limité)
    $req = $bd->prepare('SELECT 1 FROM notifications WHERE destinataire_id = ? AND tontine_id = ? AND message LIKE ? AND DATE(date_creation) = CURDATE() LIMIT 1');
    $req->execute([$beneficiaireId, $tontineId, $ref]);

    return (bool)$req->fetch();
}

/**
 * Le jour de la date limite : informer le bénéficiaire (une fois / jour).
 * Si cagnotte pleine : message retrait complet.
 * Sinon : retirer le disponible ou attendre la suite.
 */
function notifierBeneficiaireJourLimiteSiApplicable(int $tontineId, int $cycleId): void {
    $cycle = obtenirCycleSimple($cycleId);
    if (!$cycle || (int)$cycle['tontine_id'] !== $tontineId) {
        return;
    }
    if (($cycle['statut'] ?? '') !== CYCLE_EN_COURS) {
        return;
    }
    $aujourdhui = date('Y-m-d');
    if (($cycle['date_limite'] ?? '') !== $aujourdhui) {
        return;
    }
    $benefId = (int)($cycle['beneficiaire_id'] ?? 0);
    if ($benefId <= 0) {
        return;
    }
    if (notificationJourLimiteDejaEnvoyeeAujourdhui($benefId, $tontineId, $cycleId)) {
        return;
    }

    $collectee = cagnotteCollectee($cycleId);
    $theorique = (float)$cycle['cagnotte_theorique'];
    $complete  = cagnotteEstComplete($tontineId, $cycleId);
    $url       = APP_URL . '/pages/cagnotte/recevoir.php?tontine=' . $tontineId . '&cycle=' . $cycleId;
    $ref       = _refMessageCycleLimite($cycleId);

    if ($complete) {
        $titre = 'Date limite - cagnotte complète';
        $msg   = 'Aujourd’hui est la date limite des cotisations : la cagnotte est complète. Vous pouvez retirer la totalité prévue.' . $ref;
    } elseif ($collectee <= 0) {
        $titre = 'Date limite du cycle';
        $msg   = 'Aujourd’hui est la date limite des cotisations : aucun versement n’est encore enregistré pour ce cycle. Vous pourrez retirer dès qu’il y aura des cotisations.' . $ref;
    } else {
        $titre = 'Date limite - cagnotte partielle';
        $msg   = 'Aujourd’hui est la date limite : la cagnotte n’est pas encore complète (' . formaterMontant($collectee) . ' sur ' . formaterMontant($theorique) . '). Vous pouvez retirer immédiatement ce qui est déjà encaissé, ou attendre que d’autres cotisations arrivent ; le solde sera géré dans la continuité du cycle.' . $ref;
    }

    // Type = valeur déjà prévue par le schéma (ex. ENUM) - même famille que les alertes cagnotte
    creerNotification($benefId, $tontineId, 'cagnotte_disponible', $titre, $msg, $url);
}
