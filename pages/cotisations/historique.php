<?php
//  COTISATIONS - HISTORIQUE (paginé)
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/paiement_pour_autrui.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$estAdmin  = estAdminDeTontine($utilisateurId, $tontineId);

$historiqueTous  = historiquePaiementsMembre($utilisateurId, $tontineId);
$paginationHist  = paginer($historiqueTous, obtenirGetInt('page', 1));
$historique      = $paginationHist['items'];

$payesPourAutrui  = paiementsEffectuesPourAutrui($utilisateurId, $tontineId);
$recusDAutrui     = paiementsRecusDeAutrui($utilisateurId, $tontineId);

$titrePage = 'Historique - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Historique des paiements</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
    <a href="<?= APP_URL ?>/export/historique_membre.php?tontine=<?= $tontineId ?>&membre=<?= $utilisateurId ?>"
       class="btn btn-secondaire">Exporter PDF</a>
</div>

<!-- Mes cotisations -->
<section class="section-tableau">
    <div class="section-entete"><h2>Mes cotisations</h2></div>

    <?php if (empty($historiqueTous)): ?>
    <div class="etat-vide"><p>Aucun paiement enregistré.</p></div>
    <?php else: ?>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr>
                    <th>Cycle</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Mode</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Reçu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historique as $p): ?>
                <tr>
                    <td>Cycle <?= $p['numero_cycle'] ?></td>
                    <td><?= formaterDate($p['date_paiement']) ?></td>
                    <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                    <td><?= libelleModePaiement($p['mode_paiement']) ?></td>
                    <td><?= $p['type_paiement'] === TYPE_POUR_AUTRUI ? '<span class="badge badge-info">Pour autrui</span>' : 'Normal' ?></td>
                    <td><?= badgeStatut($p['statut']) ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/export/recu_paiement.php?paiement=<?= $p['id'] ?>"
                           class="btn btn-secondaire btn-tres-petit">PDF</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= rendreLiensPagination($paginationHist['page'], $paginationHist['total_pages'], APP_URL . '/pages/cotisations/historique.php?tontine=' . $tontineId) ?>
    <?php endif; ?>
</section>

<!-- Paiements effectués pour d'autres -->
<?php if (!empty($payesPourAutrui)): ?>
<section class="section-tableau">
    <div class="section-entete">
        <h2>Paiements effectués pour d'autres membres</h2>
        <span class="texte-secondaire">Trace enregistrée - remboursement entre membres</span>
    </div>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead><tr><th>Date</th><th>Membre aidé</th><th>Montant</th><th>Mode</th><th>Note</th></tr></thead>
            <tbody>
                <?php foreach ($payesPourAutrui as $p): ?>
                <tr>
                    <td><?= formaterDate($p['date_paiement']) ?></td>
                    <td><?= htmlspecialchars($p['nom_beneficiaire']) ?></td>
                    <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                    <td><?= libelleModePaiement($p['mode_paiement']) ?></td>
                    <td><?= htmlspecialchars($p['note'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<!-- Paiements reçus d'autres membres -->
<?php if (!empty($recusDAutrui)): ?>
<section class="section-tableau">
    <div class="section-entete">
        <h2>Paiements reçus d'autres membres</h2>
    </div>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead><tr><th>Date</th><th>Payé par</th><th>Montant</th><th>Note</th></tr></thead>
            <tbody>
                <?php foreach ($recusDAutrui as $p): ?>
                <tr>
                    <td><?= formaterDate($p['date_paiement']) ?></td>
                    <td><?= htmlspecialchars($p['nom_payeur']) ?></td>
                    <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                    <td><?= htmlspecialchars($p['note'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
