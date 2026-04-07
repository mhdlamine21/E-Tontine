<?php
//  CAGNOTTE - ÉTAT EN TEMPS RÉEL
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/cagnotte.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$cycleActuel = cycleEnCours($tontineId);
$collectee   = $cycleActuel ? cagnotteCollectee($cycleActuel['id']) : 0;
$theorique   = $cycleActuel ? (float)$cycleActuel['cagnotte_theorique'] : 0;
$pct         = $theorique > 0 ? min(100, round(($collectee / $theorique) * 100)) : 0;
$etatDistrib = $cycleActuel ? etatDistribution($tontineId, $cycleActuel['id']) : null;
$estBenef    = $cycleActuel && (int)$cycleActuel['beneficiaire_id'] === $utilisateurId;

$titrePage = 'État de la cagnotte - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>État de la cagnotte</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<?php if (!$cycleActuel): ?>
<div class="etat-vide"><p>Aucun cycle en cours.</p></div>
<?php else: ?>

<div class="grille-stats">
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= formaterMontant($collectee) ?></div>
        <div class="carte-stat-label">Collecté</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= formaterMontant($theorique) ?></div>
        <div class="carte-stat-label">Objectif total</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= $pct ?>%</div>
        <div class="carte-stat-label">Progression</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= formaterMontant(max(0, $theorique - $collectee)) ?></div>
        <div class="carte-stat-label">Reste à collecter</div>
    </div>
</div>

<section class="section-tableau">
    <div class="barre-progression-conteneur">
        <div class="barre-progression">
            <div class="barre-progression-remplie" style="width:<?= $pct ?>%"></div>
        </div>
    </div>

    <?php if ($etatDistrib): ?>
    <div class="info-distribution">
        <strong>Distribution :</strong>
        Montant reçu : <?= formaterMontant((float)$etatDistrib['montant_recu']) ?> -
        Reste : <?= formaterMontant((float)$etatDistrib['montant_restant']) ?>
        <?= badgeStatut($etatDistrib['statut']) ?>
    </div>
    <?php endif; ?>

    <?php if ($estBenef && (!$etatDistrib || $etatDistrib['statut'] !== DISTRIB_COMPLET)): ?>
    <div class="alerte alerte-succes">
        <strong>C'est votre tour de recevoir !</strong>
        <a href="<?= APP_URL ?>/pages/cagnotte/recevoir.php?tontine=<?= $tontineId ?>&cycle=<?= $cycleActuel['id'] ?>" class="btn btn-succes" style="margin-left:12px">
            Choisir comment recevoir →
        </a>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
