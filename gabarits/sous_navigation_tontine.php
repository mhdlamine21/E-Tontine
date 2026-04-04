<?php
declare(strict_types=1);

if (empty($aSousNavTontine) || empty($ctxMenuTontineId)) {
    return;
}

require_once __DIR__ . '/../fonctions/membre.php';
require_once __DIR__ . '/../fonctions/cotisation.php';
require_once __DIR__ . '/../fonctions/cagnotte.php';

$tid       = $ctxMenuTontineId;
$uid       = idUtilisateurConnecte();
$estAdmin  = estAdminDeTontine($uid, $tid);
$cycleNav  = cycleEnCours($tid);
$qsCycle   = $cycleNav ? '&cycle=' . (int)$cycleNav['id'] : '';

$urlPayer = $cycleNav
    ? APP_URL . '/pages/cotisations/payer.php?tontine=' . $tid . $qsCycle
    : APP_URL . '/pages/tontines/voir.php?id=' . $tid;

$urlPayerAutrui = APP_URL . '/pages/tontines/voir.php?id=' . $tid;
if ($estAdmin && $cycleNav) {
    $urlPayerAutrui = APP_URL . '/pages/cotisations/payer_pour_autrui.php?tontine=' . $tid . $qsCycle;
}

$afficherLienRecevoirTour = false;
$urlRecevoirTour          = '';
if ($cycleNav && (int)$cycleNav['beneficiaire_id'] === $uid) {
    $etatNavR = etatDistribution($tid, (int)$cycleNav['id']);
    $distOk   = $etatNavR && $etatNavR['statut'] === DISTRIB_COMPLET;
    if (!$distOk) {
        $afficherLienRecevoirTour = true;
        $urlRecevoirTour = APP_URL . '/pages/cagnotte/recevoir.php?tontine=' . $tid . '&cycle=' . (int)$cycleNav['id'];
    }
}

$ctxMenuTontineActif = $ctxMenuTontineActif ?? null;
if ($ctxMenuTontineActif === null) {
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    $ctxMenuTontineActif = match (true) {
        str_contains($path, '/tontines/voir.php') => 'voir',
        str_contains($path, '/tontines/demarrer_cycle.php') => 'voir',
        str_contains($path, '/tontines/modifier.php') => 'modifier',
        str_contains($path, '/tontines/fermer.php') => 'fermer',
        str_contains($path, '/membres/liste.php') => 'membres',
        str_contains($path, '/membres/ajouter.php') => 'ajouter',
        str_contains($path, '/membres/ordre_des_tours.php') => 'ordre',
        str_contains($path, '/cotisations/payer.php') => 'payer',
        str_contains($path, '/cotisations/payer_pour_autrui.php') => 'payer_autrui',
        str_contains($path, '/cotisations/historique.php') => 'historique',
        str_contains($path, '/cotisations/retards.php') => 'retards',
        str_contains($path, '/cagnotte/etat.php') => 'cagnotte',
        str_contains($path, '/cagnotte/recevoir.php') => 'recevoir',
        str_contains($path, '/urgences/demander.php') => 'urgence_demander',
        str_contains($path, '/urgences/valider.php') => 'urgence_valider',
        str_contains($path, '/votes/petition.php') || str_contains($path, '/votes/voter.php') || str_contains($path, '/votes/resultat.php') => 'votes',
        default => '',
    };
}

$u = static function (string $path): string {
    return APP_URL . $path;
};

$lk = static function (string $cle) use ($ctxMenuTontineActif): string {
    return $ctxMenuTontineActif === $cle ? ' actif' : '';
};
?>
<div class="sousnav-tontine-container" id="sousnavTontineContainer">
<button type="button"
        class="sousnav-tontine__toggle"
        id="sousnavTontineToggle"
        aria-expanded="false"
        aria-controls="sousnavTontinePanel">
    <span class="sousnav-tontine__toggle-texte">Menu de la tontine</span>
    <span class="sousnav-tontine__toggle-icone" aria-hidden="true">▼</span>
</button>
<aside class="sousnav-tontine" id="sousnavTontinePanel" aria-label="Actions pour cette tontine">
    <div class="sousnav-tontine__titre">Cette tontine</div>
    <ul class="sousnav-tontine__liste">
        <li><a class="sousnav-tontine__lien<?= $lk('voir') ?>" href="<?= $u('/pages/tontines/voir.php?id=' . $tid) ?>">Vue d’ensemble</a></li>
        <li><a class="sousnav-tontine__lien<?= $lk('membres') ?>" href="<?= $u('/pages/membres/liste.php?tontine=' . $tid) ?>">Membres</a></li>
        <?php if ($estAdmin): ?>
        <li><a class="sousnav-tontine__lien<?= $lk('ajouter') ?>" href="<?= $u('/pages/membres/ajouter.php?tontine=' . $tid) ?>">Ajouter un membre</a></li>
        <?php endif; ?>
    </ul>

    <div class="sousnav-tontine__titre">Cotisations</div>
    <ul class="sousnav-tontine__liste">
        <li><a class="sousnav-tontine__lien<?= $lk('payer') ?>" href="<?= htmlspecialchars($urlPayer) ?>"><?= $cycleNav ? 'Payer ma cotisation' : 'Payer (voir la tontine)' ?></a></li>
        <li><a class="sousnav-tontine__lien<?= $lk('historique') ?>" href="<?= $u('/pages/cotisations/historique.php?tontine=' . $tid) ?>">Historique</a></li>
        <li><a class="sousnav-tontine__lien<?= $lk('retards') ?>" href="<?= $u('/pages/cotisations/retards.php?tontine=' . $tid) ?>">Retards</a></li>
        <?php if ($estAdmin): ?>
        <li><a class="sousnav-tontine__lien<?= $lk('payer_autrui') ?>" href="<?= htmlspecialchars($urlPayerAutrui) ?>"><?= $cycleNav ? 'Payer pour autrui' : 'Payer pour autrui (démarrer un cycle)' ?></a></li>
        <?php endif; ?>
    </ul>

    <div class="sousnav-tontine__titre">Cagnotte</div>
    <ul class="sousnav-tontine__liste">
        <li><a class="sousnav-tontine__lien<?= $lk('cagnotte') ?>" href="<?= $u('/pages/cagnotte/etat.php?tontine=' . $tid) ?>">État</a></li>
        <?php if ($afficherLienRecevoirTour): ?>
        <li><a class="sousnav-tontine__lien<?= $lk('recevoir') ?>" href="<?= htmlspecialchars($urlRecevoirTour) ?>">Recevoir ma cagnotte</a></li>
        <?php endif; ?>
    </ul>

    <div class="sousnav-tontine__titre">Urgences</div>
    <ul class="sousnav-tontine__liste">
        <li><a class="sousnav-tontine__lien<?= $lk('urgence_demander') ?>" href="<?= $u('/pages/urgences/demander.php?tontine=' . $tid) ?>">Demander</a></li>
        <?php if ($estAdmin): ?>
        <li><a class="sousnav-tontine__lien<?= $lk('urgence_valider') ?>" href="<?= $u('/pages/urgences/valider.php?tontine=' . $tid) ?>">Traiter les demandes</a></li>
        <?php endif; ?>
    </ul>

    <div class="sousnav-tontine__titre">Votes</div>
    <ul class="sousnav-tontine__liste">
        <li><a class="sousnav-tontine__lien<?= $lk('votes') ?>" href="<?= $u('/pages/votes/petition.php?tontine=' . $tid) ?>">Pétitions &amp; votes</a></li>
    </ul>

    <?php if ($estAdmin): ?>
    <div class="sousnav-tontine__titre">Administration</div>
    <ul class="sousnav-tontine__liste">
        <li><a class="sousnav-tontine__lien<?= $lk('modifier') ?>" href="<?= $u('/pages/tontines/modifier.php?id=' . $tid) ?>">Modifier la tontine</a></li>
        <li><a class="sousnav-tontine__lien<?= $lk('ordre') ?>" href="<?= $u('/pages/membres/ordre_des_tours.php?tontine=' . $tid) ?>">Ordre des tours</a></li>
        <li><a class="sousnav-tontine__lien<?= $lk('fermer') ?>" href="<?= $u('/pages/tontines/fermer.php?id=' . $tid) ?>">Fermer la tontine</a></li>
    </ul>
    <?php endif; ?>
</aside>
</div>
