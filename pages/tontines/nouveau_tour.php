<?php
//  TONTINES - TOUR COMPLET : NOUVEAU TOUR OU FERMETURE
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('id');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
if (!$tontine) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Tontine introuvable.');
}

if (empty($tontine['tour_complet_en_attente'])) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'info', 'Aucune décision en attente pour cette tontine.');
}

$membres = listerMembres($tontineId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf() && isset($_POST['nouveau_tour'])) {
    $res = lancerNouveauTour($tontineId, $utilisateurId);
    redirigerAvecMessage(
        APP_URL . '/pages/tontines/demarrer_cycle.php?id=' . $tontineId,
        $res['succes'] ? 'succes' : 'erreur',
        $res['message']
    );
}

$titrePage = 'Tour complet - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>🎉 Tour n°<?= (int)$tontine['numero_tour_actuel'] - 1 ?> terminé</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<div class="formulaire-conteneur">
    <div class="alerte alerte-succes">
        Tous les membres actifs (<?= count($membres) ?>) ont reçu la cagnotte une fois sur ce tour. En tant qu’administrateur, choisissez la suite :
    </div>

    <section class="section-tableau">
        <div class="section-entete"><h2>Option 1 - Lancer un nouveau tour</h2></div>
        <p class="texte-secondaire">
            Un nouveau tirage au sort de l’ordre des tours sera effectué (pour garantir l’équité entre les membres),
            puis vous pourrez démarrer un nouveau cycle de cotisation comme d’habitude.
        </p>
        <form method="POST" action="">
            <?= champCsrf() ?>
            <button type="submit" name="nouveau_tour" value="1" class="btn btn-principal"
                    onclick="return confirm('Lancer un nouveau tour ? L’ordre des tours sera retiré au sort.')">
                Lancer un nouveau tour
            </button>
        </form>
    </section>

    <div class="separateur-ou"><span>ou</span></div>

    <section class="section-tableau">
        <div class="section-entete"><h2>Option 2 - Fermer la tontine</h2></div>
        <p class="texte-secondaire">
            Si l’objectif de la tontine est atteint, vous pouvez la fermer définitivement. L’historique des
            cotisations et des distributions reste consultable, mais aucune nouvelle opération ne sera possible.
        </p>
        <a href="<?= APP_URL ?>/pages/tontines/fermer.php?id=<?= $tontineId ?>" class="btn btn-danger">
            Fermer la tontine
        </a>
    </section>
</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
