<?php
// Arrêter temporairement (active → suspendue)
declare(strict_types=1);

require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$tontineId     = obtenirGetInt('id');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
if (!$tontine) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Tontine introuvable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $res = arreterActiviteTontine($tontineId, $utilisateurId);
    redirigerAvecMessage(
        APP_URL . '/pages/tontines/voir.php?id=' . $tontineId,
        $res['succes'] ? 'succes' : 'erreur',
        $res['message']
    );
}

$titrePage = 'Arrêter la tontine';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Arrêter temporairement</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<div class="formulaire-conteneur">
    <p>La tontine ne sera plus considérée comme « en cours » : plus de cotisations ni de nouveaux cycles jusqu’à réactivation.</p>
    <p class="texte-secondaire">Les membres conservent l’accès en lecture à l’historique.</p>

    <form method="POST" action="">
        <?= champCsrf() ?>
        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-danger">Confirmer l’arrêt</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
