<?php
//  GABARIT - EN-TÊTE HTML
//  Usage : require_once CHEMIN . '/gabarits/entete.php';
//  Variables attendues : $titrePage (string)

require_once __DIR__ . '/../configuration/session.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../fonctions/aide.php';
require_once __DIR__ . '/../fonctions/notification.php';
require_once __DIR__ . '/composants.php';

$titrePage = $titrePage ?? APP_NOM;
$nbNotifs  = estConnecte() ? nbNotificationsNonLues(idUtilisateurConnecte()) : 0;

$ctxMenuTontineId = isset($ctxMenuTontineId) ? (int)$ctxMenuTontineId : 0;
$scriptName       = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_contains($scriptName, '/super_admin/')) {
    $ctxMenuTontineId = 0;
} elseif ($ctxMenuTontineId <= 0 && estConnecte()) {
    if (preg_match('#/pages/tontines/(voir|modifier|fermer)\.php#', $scriptName) && isset($_GET['id'])) {
        $ctxMenuTontineId = (int)$_GET['id'];
    } elseif (!empty($_GET['tontine'])) {
        $ctxMenuTontineId = (int)$_GET['tontine'];
    }
}

$aSousNavTontine = false;
$navSectionPrincipale = $navSectionPrincipale ?? null;
$tourCompletBanniere = null;
if (estConnecte() && $ctxMenuTontineId > 0 && !str_contains($scriptName, '/super_admin/')) {
    require_once __DIR__ . '/../fonctions/tontine.php';
    require_once __DIR__ . '/../fonctions/membre.php';
    $tontineCtx = obtenirTontine($ctxMenuTontineId);
    $aSousNavTontine = (bool)($tontineCtx && estMembreDeTontine(idUtilisateurConnecte(), $ctxMenuTontineId));
    if ($aSousNavTontine && $navSectionPrincipale === null) {
        $navSectionPrincipale = estAdminDeTontine(idUtilisateurConnecte(), $ctxMenuTontineId) ? 'mes_tontines' : 'mes_adhesions';
    }
    if ($tontineCtx && !empty($tontineCtx['tour_complet_en_attente'])
        && estAdminDeTontine(idUtilisateurConnecte(), $ctxMenuTontineId)
        && !str_contains($scriptName, '/nouveau_tour.php')) {
        $tourCompletBanniere = $tontineCtx;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titrePage) ?> - <?= APP_NOM ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= APP_LOGO_URL ?>" type="image/png">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/principal.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/formulaires.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/tableau_de_bord.css">
    <script>
    window.APP_URL = '<?= APP_URL ?>';
    <?php if (estConnecte()): ?>
    window.CSRF_TOKEN = '<?= htmlspecialchars(genererCsrf(), ENT_QUOTES, 'UTF-8') ?>';
    <?php endif; ?>
    </script>
</head>
<body>

<?php require_once __DIR__ . '/barre_navigation.php'; ?>

<!-- Messages flash -->
<?php
$flash = obtenirFlash();
foreach ($flash as $type => $messages):
    foreach ($messages as $msg):
?>
<div class="alerte alerte-<?= $type === 'succes' ? 'succes' : ($type === 'erreur' ? 'danger' : 'info') ?>" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button class="alerte-fermer" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php
    endforeach;
endforeach;
?>

<?php if ($tourCompletBanniere): ?>
<div class="alerte alerte-succes" role="alert" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <span>🎉 <strong>Tour complet</strong> sur « <?= htmlspecialchars($tourCompletBanniere['nom']) ?> » : tous les membres ont reçu la cagnotte une fois.</span>
    <a href="<?= APP_URL ?>/pages/tontines/nouveau_tour.php?id=<?= (int)$tourCompletBanniere['id'] ?>" class="btn btn-principal btn-petit">Décider de la suite →</a>
</div>
<?php endif; ?>

<main class="contenu-principal<?= !empty($aSousNavTontine) ? ' contenu-principal--avec-sousnav' : '' ?>">
<?php if (!empty($aSousNavTontine)): ?>
<div class="layout-page-avec-sousnav">
<?php require_once __DIR__ . '/sous_navigation_tontine.php'; ?>
<div class="layout-page-avec-sousnav__main">
<?php endif; ?>
