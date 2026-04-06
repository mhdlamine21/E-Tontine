<?php


require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/aide.php';
require_once __DIR__ . '/cotisation.php';
require_once __DIR__ . '/cagnotte.php';
require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/notifications_cagnotte.php';
require_once __DIR__ . '/amende.php';  // ← AJOUT POUR LES AMENDES

// Enregistrer un paiement de cotisation normale
function enregistrerPaiement(
    int    $tontineId,
    int    $cycleId,
    int    $payeurId,
    int    $beneficiaireId,
    float  $montant,
    string $mode,
    string $telephone,
    string $type = TYPE_NORMAL
): array {
    $bd = connexionBD();

    // Verrou + transaction : empêche un double paiement simultané (double-clic,
    //     double onglet) sur le même cycle. Le SELECT ... FOR UPDATE verrouille la
    //     ligne du cycle le temps de vérifier puis d'insérer le paiement.
    $bd->beginTransaction();
    try {
        $verrou = $bd->prepare('SELECT id FROM cycles_cotisation WHERE id = ? FOR UPDATE');
        $verrou->execute([$cycleId]);

        // Vérifier que le membre n'a pas déjà payé (revérifié sous verrou)
        if ($type === TYPE_NORMAL && aDejaPayePourCycle($tontineId, $cycleId, $payeurId)) {
            $bd->rollBack();
            return ['succes' => false, 'message' => 'Vous avez déjà payé pour ce cycle.'];
        }

        // Récupérer les amendes non payées du membre
        $amendesNonPayees = getAmendesNonPayees($tontineId, $cycleId, $payeurId);
        $montantTotal = $montant + $amendesNonPayees;

        // Simuler l'appel à l'API mobile (délai artificiel simulé)
        $simulation = simulerPaiementMobile($montantTotal, $mode, $telephone);
        if (!$simulation['succes']) {
            $bd->rollBack();
            return ['succes' => false, 'message' => 'Échec du paiement mobile. Réessayez.'];
        }

        // État cagnotte avant ce paiement (pour une seule notif au passage à « complet »)
        $cycleAvantPaiement           = obtenirCycleSimple($cycleId);
        $cagnotteEtaitDejaComplete    = false;
        if ($cycleAvantPaiement) {
            $th = (float)$cycleAvantPaiement['cagnotte_theorique'];
            $col = (float)$cycleAvantPaiement['cagnotte_collectee'];
            $cagnotteEtaitDejaComplete = $th > 0 && $col >= $th;
        }

        // Enregistrer le paiement
        $reference = genererReference();
        $req       = $bd->prepare('INSERT INTO paiements (tontine_id, cycle_id, payeur_id, beneficiaire_id, montant, type_paiement, mode_paiement, numero_telephone, statut, reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $req->execute([$tontineId, $cycleId, $payeurId, $beneficiaireId, $montantTotal, $type, $mode, $telephone, PAIEMENT_PAYE, $reference]);
        $paiementId = (int)$bd->lastInsertId();

        // Marquer les amendes comme payées
        if ($amendesNonPayees > 0) {
            $bd->prepare('UPDATE amendes SET est_paye = 1, date_paiement = NOW() WHERE tontine_id = ? AND cycle_id = ? AND membre_id = ? AND est_paye = 0')
               ->execute([$tontineId, $cycleId, $payeurId]);
        }

        // Mettre à jour la cagnotte du cycle
        majCagnotteCollectee($cycleId);

        // Journaliser
        journaliserPaiement($paiementId, $payeurId, $montantTotal, $mode, $telephone, 'succes', $simulation['reference']);

        // Mettre à jour le statut du membre
        $bd->prepare("UPDATE membres_tontine SET statut = 'actif' WHERE tontine_id = ? AND utilisateur_id = ?")
           ->execute([$tontineId, $payeurId]);

        $bd->commit();
    } catch (Throwable $e) {
        if ($bd->inTransaction()) $bd->rollBack();
        error_log('Erreur enregistrerPaiement : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Une erreur est survenue lors de l\'enregistrement du paiement. Réessayez.'];
    }

    // À partir d'ici, le paiement est bien enregistré (transaction validée).
    // Le reste (score de fiabilité, notifications, journal d'audit) peut s'exécuter
    // hors transaction sans risque pour l'intégrité du paiement lui-même.
    if ($amendesNonPayees > 0) {
        creerNotification(
            $payeurId,
            $tontineId,
            'amende',
            '⚠️ Amende incluse dans votre paiement',
            'Vous avez payé une amende de ' . number_format($amendesNonPayees, 0, ',', ' ') . ' FCFA en plus de votre cotisation. Montant total : ' . number_format($montantTotal, 0, ',', ' ') . ' FCFA.',
            APP_URL . '/pages/cotisations/historique.php?tontine=' . $tontineId
        );
    }

    verifierDistributionAutomatique($tontineId, $cycleId);

    require_once __DIR__ . '/logs.php';
    ajouterLog('paiement_cotisation', $payeurId, 'Paiement ' . formaterMontant($montantTotal) . ' (réf. ' . $reference . ')', $tontineId);

    // Mettre à jour le score de fiabilité
    require_once __DIR__ . '/utilisateur.php';
    majScoreFiabilite($payeurId, $tontineId);

    // Notification de confirmation
    $messageConfirmation = 'Votre paiement de ' . formaterMontant($montantTotal) . ' a bien été enregistré.';
    if ($amendesNonPayees > 0) {
        $messageConfirmation .= ' (dont ' . formaterMontant($amendesNonPayees) . ' d\'amende)';
    }
    creerNotification(
        $payeurId,
        $tontineId,
        'paiement_confirme',
        'Paiement confirmé',
        $messageConfirmation,
        APP_URL . '/pages/cotisations/historique.php?tontine=' . $tontineId
    );

    // Cagnotte qui vient d'être complétée → notifier (sauf le jour de la date limite : message unifié « jour limite »)
    if (!$cagnotteEtaitDejaComplete && cagnotteEstComplete($tontineId, $cycleId)) {
        $cycle = obtenirCycleSimple($cycleId);
        if ($cycle && $cycle['beneficiaire_id']) {
            $estJourLimite = (($cycle['date_limite'] ?? '') === date('Y-m-d'));
            if (!$estJourLimite) {
                $benId = (int)$cycle['beneficiaire_id'];
                $urlRecevoir = APP_URL . '/pages/cagnotte/recevoir.php?tontine=' . $tontineId . '&cycle=' . $cycleId;
                creerNotification(
                    $benId,
                    $tontineId,
                    'cagnotte_complete',
                    'Cagnotte complète - à vous de retirer',
                    'La cagnotte du cycle en cours est complète et c\'est votre tour. Vous pouvez retirer la cagnotte.',
                    $urlRecevoir
                );
            }
        }
    }

    notifierBeneficiaireJourLimiteSiApplicable($tontineId, $cycleId);

    return ['succes' => true, 'paiement_id' => $paiementId, 'reference' => $reference, 'amende_payee' => $amendesNonPayees];
}

// Simulation de l'appel API Wave / Orange Money
function simulerPaiementMobile(float $montant, string $mode, string $telephone): array {
    // Validation basique du numéro
    if (!preg_match('/^7[0-9]{8}$/', preg_replace('/\s+/', '', $telephone))) {
        return ['succes' => false, 'message' => 'Numéro de téléphone invalide.'];
    }

    // Référence simulée
    $ref = strtoupper($mode === MODE_WAVE ? 'WV' : 'OM') . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));

    return [
        'succes'    => true,
        'reference' => $ref,
        'message'   => 'Paiement ' . libelleModePaiement($mode) . ' simulé avec succès.',
    ];
}

// Journaliser une tentative de paiement
function journaliserPaiement(int $paiementId, int $utilisateurId, float $montant, string $mode, string $telephone, string $statut, string $reference = ''): void {
    $bd = connexionBD();
    $bd->prepare('INSERT INTO journaux_paiement (paiement_id, utilisateur_id, montant, mode, numero_telephone, statut_simulation, reference_externe) VALUES (?, ?, ?, ?, ?, ?, ?)')
       ->execute([$paiementId, $utilisateurId, $montant, $mode, $telephone, $statut, $reference]);
}

// Générer une référence unique
function genererReference(): string {
    return 'ET-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

// Obtenir un paiement par son ID
function obtenirPaiement(int $id): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT p.*, u.nom_complet AS nom_payeur, ub.nom_complet AS nom_beneficiaire, cc.numero_cycle FROM paiements p JOIN utilisateurs u ON u.id = p.payeur_id JOIN utilisateurs ub ON ub.id = p.beneficiaire_id JOIN cycles_cotisation cc ON cc.id = p.cycle_id WHERE p.id = ?');
    $req->execute([$id]);
    return $req->fetch() ?: null;
}