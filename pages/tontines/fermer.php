<?php
//  TONTINES - FERMER
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('id');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    fermerTontine($tontineId);
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'succes', 'La tontine "' . $tontine['nom'] . '" a été fermée.');
}

$titrePage = 'Fermer - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Fermer la tontine</h1>
    </div>
</div>

<div class="formulaire-conteneur">
    <div class="alerte alerte-danger">
        <strong>Attention !</strong> Cette action est irréversible. La tontine sera désactivée et les membres ne pourront plus y accéder.
    </div>

    <div class="carte-confirmation">
        <h3>Confirmer la fermeture</h3>
        <p>Vous êtes sur le point de fermer la tontine <strong><?= htmlspecialchars($tontine['nom']) ?></strong>.</p>
        <ul>
            <li>Tous les membres perdront l'accès aux fonctionnalités</li>
            <li>Les historiques de paiements resteront visibles</li>
            <li>Cette action ne peut pas être annulée</li>
        </ul>
        <form method="POST" action="">
            <?= champCsrf() ?>
            <div class="actions-formulaire">
                <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
                <button type="submit" class="btn btn-danger">Confirmer la fermeture</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
