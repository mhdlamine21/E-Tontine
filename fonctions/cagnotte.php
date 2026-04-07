<?php
/**
 * ============================================================
 * fonctions/cagnotte.php
 *
 * Logique de gestion et distribution des cagnottes :
 *   - Choix du mode de réception par le bénéficiaire (immédiat / attendre au complet)
 *   - Distribution progressive ou totale des fonds collectés
 *   - Calcul des synthèses financières par cycle et globales
 *   - Reclassement du bénéficiaire en fin de file après encaissement
 *   - Clôture du cycle et déclenchement de la vérification de fin de tour
 *
 * DÉPENDANCES :
 *   configuration/base_de_donnees.php  — connexionBD()
 *   configuration/constantes.php       — DISTRIB_*, STATUT_RETARD
 *   fonctions/cotisation.php           — cycle, cagnotteCollectee
 *   fonctions/membre.php               — deplacerMembreEnFinDeFile
 *   fonctions/notification.php         — creerNotification
 * ============================================================
 */

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/aide.php';
require_once __DIR__ . '/cotisation.php';
require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/membre.php'; 

/**
 * Enregistre le choix du bénéficiaire pour la perception de sa cagnotte.
 *
 * @param int    $tontineId       ID de la tontine
 * @param int    $cycleId         ID du cycle
 * @param int    $beneficiaireId  ID de l'utilisateur bénéficiaire
 * @param string $choix           'recevoir_maintenant' ou 'attendre_complet'
 * @return array                  ['succes' => bool, 'message' => string]
 */
function choisirModeReception(int $tontineId, int $cycleId, int $beneficiaireId, string $choix): array {
    $bd = connexionBD();

    // Vérifier que le membre n'est pas en retard
    $req = $bd->prepare('SELECT statut FROM membres_tontine WHERE tontine_id = ? AND utilisateur_id = ?');
    $req->execute([$tontineId, $beneficiaireId]);
    $membre = $req->fetch();
    if ($membre && $membre['statut'] === STATUT_RETARD) {
        return ['succes' => false, 'message' => 'Vous êtes en retard de cotisation. Payez d\'abord votre cotisation.'];
    }

    // Récupérer ou créer la distribution
    $req = $bd->prepare('SELECT * FROM distributions WHERE tontine_id = ? AND cycle_id = ? AND beneficiaire_id = ?');
    $req->execute([$tontineId, $cycleId, $beneficiaireId]);
    $distrib = $req->fetch();

    $cycle = obtenirCycleSimple($cycleId);
    if (!$cycle) return ['succes' => false, 'message' => 'Cycle introuvable.'];

    $montantPrevu    = (float)$cycle['cagnotte_theorique'];
    $montantCollecte = cagnotteCollectee($cycleId);

    if (!$distrib) {
        $bd->prepare('INSERT INTO distributions (tontine_id, cycle_id, beneficiaire_id, montant_prevu, montant_recu, montant_restant, choix_beneficiaire, statut) VALUES (?, ?, ?, ?, 0, ?, ?, ?)')
           ->execute([$tontineId, $cycleId, $beneficiaireId, $montantPrevu, $montantPrevu, $choix, DISTRIB_EN_ATTENTE]);
        $distribId = (int)$bd->lastInsertId();
    } else {
        $distribId = (int)$distrib['id'];
        $bd->prepare('UPDATE distributions SET choix_beneficiaire = ? WHERE id = ?')
           ->execute([$choix, $distribId]);
    }

    if ($choix === 'recevoir_maintenant') {
        return distribuerCagnotte($tontineId, $cycleId, $beneficiaireId, $distribId, $montantCollecte, $montantPrevu);
    }

    return ['succes' => true, 'message' => 'Vous attendez que la cagnotte soit complète.'];
}

/**
 * Synthèse des montants pour l’affichage (cotisations vs déjà versé au bénéficiaire).
 */
function resumeSoldesCycle(int $cycleId, ?array $distribution): array {
    $cycle     = obtenirCycleSimple($cycleId);
    $theorique = $cycle ? (float)$cycle['cagnotte_theorique'] : 0.0;
    $collectee = cagnotteCollectee($cycleId);
    $dejaRecu  = $distribution ? (float)($distribution['montant_recu'] ?? 0) : 0.0;

    $nonEncoreVerseAuBenef = max(0.0, $collectee - $dejaRecu);
    $resteTheoriqueBenef   = max(0.0, $theorique - $dejaRecu);
    $prochainVersementMax  = min($nonEncoreVerseAuBenef, $resteTheoriqueBenef);

    return [
        'collectee_totale'       => $collectee,
        'deja_verse_beneficiaire'=> $dejaRecu,
        'theorique'              => $theorique,
        'non_encore_verse'       => $nonEncoreVerseAuBenef,
        'reste_du_beneficiaire'  => $resteTheoriqueBenef,
        'disponible_prochain_retrait' => $prochainVersementMax,
        'reste_a_collecter'      => max(0.0, $theorique - $collectee),
    ];
}

// Distribuer la cagnotte au bénéficiaire (retraits partiels cumulables)
function distribuerCagnotte(int $tontineId, int $cycleId, int $beneficiaireId, int $distribId, float $montantCollecteeActuelle, float $montantPrevuParam): array {
    $bd = connexionBD();

    // PDO ne supporte pas les transactions imbriquées : si l'appelant (ex. la
    // validation d'une urgence en mode « immédiat ») a déjà ouvert une
    // transaction, on ne doit ni en ouvrir une seconde, ni la valider/annuler
    // nous-mêmes - c'est à l'appelant de le faire.
    $transactionDemarreeIci = !$bd->inTransaction();
    if ($transactionDemarreeIci) {
        $bd->beginTransaction();
    }
    try {
        // Verrouille la ligne de distribution le temps de lire puis d'écrire le montant reçu :
        // empêche un double-clic ou une distribution automatique concurrente de verser deux fois.
        $req = $bd->prepare('SELECT montant_recu, montant_prevu FROM distributions WHERE id = ? AND tontine_id = ? AND cycle_id = ? FOR UPDATE');
        $req->execute([$distribId, $tontineId, $cycleId]);
        $row = $req->fetch();
        if (!$row) {
            if ($transactionDemarreeIci) $bd->rollBack();
            return ['succes' => false, 'message' => 'Distribution introuvable.'];
        }

        $dejaRecu = (float)($row['montant_recu'] ?? 0);
        $prevu    = (float)($row['montant_prevu'] ?? $montantPrevuParam);

        $collectee = cagnotteCollectee($cycleId);

        $plafondTheoriqueRestant     = max(0.0, $prevu - $dejaRecu);
        $encaissePasEncoreAttribue   = max(0.0, $collectee - $dejaRecu);
        $versementCetteOperation      = min($encaissePasEncoreAttribue, $plafondTheoriqueRestant);

        if ($versementCetteOperation <= 0.009) {
            if ($transactionDemarreeIci) $bd->rollBack();
            return ['succes' => false, 'message' => 'Aucun nouveau montant à verser pour l’instant (cotisations ou plafond du cycle).'];
        }

        $nouveauRecu    = $dejaRecu + $versementCetteOperation;
        $nouveauRestant = max(0.0, $prevu - $nouveauRecu);
        $statut         = $nouveauRestant <= 0.01 ? DISTRIB_COMPLET : DISTRIB_PARTIEL;

        $bd->prepare('UPDATE distributions SET montant_recu = ?, montant_restant = ?, statut = ?, date_distribution = NOW() WHERE id = ?')
           ->execute([$nouveauRecu, $nouveauRestant, $statut, $distribId]);

        if ($statut === DISTRIB_COMPLET) {
            marquerCycleDistribue($cycleId);
        }

        mettreMembreEnDernierOrdre($tontineId, $beneficiaireId);

        if ($transactionDemarreeIci) $bd->commit();
    } catch (Throwable $e) {
        if ($transactionDemarreeIci && $bd->inTransaction()) $bd->rollBack();
        error_log('Erreur distribuerCagnotte : ' . $e->getMessage());
        if (!$transactionDemarreeIci) {
            // On laisse l'appelant décider du sort de SA transaction : on la
            // relance pour qu'il puisse faire son propre rollBack().
            throw $e;
        }
        return ['succes' => false, 'message' => 'Une erreur est survenue lors de la distribution. Réessayez.'];
    }

    require_once __DIR__ . '/logs.php';
    ajouterLog('distribution_cagnotte', $beneficiaireId, formaterMontant($versementCetteOperation) . ' distribués (cycle #' . $cycleId . ')', $tontineId);

    if ($statut === DISTRIB_COMPLET) {
        require_once __DIR__ . '/tontine.php';
        verifierFinDeTour($tontineId);
    }

    $msg = 'Vous avez reçu ' . formaterMontant($versementCetteOperation);
    if ($nouveauRestant > 0.01) {
        $msg .= '. Déjà reçu au total : ' . formaterMontant($nouveauRecu) . '. Il reste jusqu’à ' . formaterMontant($nouveauRestant) . ' selon le plafond du cycle ; les nouvelles cotisations s’ajoutent au pot affiché.';
    } else {
        $msg .= ' (montant prévu pour ce cycle atteint).';
    }

    creerNotification(
        $beneficiaireId,
        $tontineId,
        'cagnotte_disponible',
        'Cagnotte reçue',
        $msg,
        APP_URL . '/pages/cagnotte/etat.php?tontine=' . $tontineId
    );

    return [
        'succes'           => true,
        'message'          => $msg,
        'montant_recu'     => $nouveauRecu,
        'montant_restant'  => $nouveauRestant,
        'versement_lot'    => $versementCetteOperation,
    ];
}

// Distribution automatique si cagnotte complète et bénéficiaire en attente
function verifierDistributionAutomatique(int $tontineId, int $cycleId): void {
    $bd = connexionBD();

    // Chercher distribution en attente avec choix = attendre
    $req = $bd->prepare("SELECT * FROM distributions WHERE tontine_id = ? AND cycle_id = ? AND choix_beneficiaire = 'attendre' AND statut = 'en_attente'");
    $req->execute([$tontineId, $cycleId]);
    $distrib = $req->fetch();
    if (!$distrib) return;

    // Vérifier si cagnotte complète
    if (cagnotteEstComplete($tontineId, $cycleId)) {
        $montant = cagnotteCollectee($cycleId);
        distribuerCagnotte($tontineId, $cycleId, (int)$distrib['beneficiaire_id'], (int)$distrib['id'], $montant, (float)$distrib['montant_prevu']);
    }
}

/** Total des montants déjà versés aux bénéficiaires (toutes distributions, toutes cycles). */
function montantTotalDistribueTontine(int $tontineId): float {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT COALESCE(SUM(montant_recu), 0) FROM distributions WHERE tontine_id = ?');
    $req->execute([$tontineId]);

    return (float)$req->fetchColumn();
}

// Obtenir l'état de la distribution pour un cycle
function etatDistribution(int $tontineId, int $cycleId): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT d.*, u.nom_complet FROM distributions d JOIN utilisateurs u ON u.id = d.beneficiaire_id WHERE d.tontine_id = ? AND d.cycle_id = ?');
    $req->execute([$tontineId, $cycleId]);
    return $req->fetch() ?: null;
}