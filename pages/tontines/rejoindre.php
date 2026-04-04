<?php
//  TONTINES - REJOINDRE VIA INVITATION
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/invitation.php';
require_once __DIR__ . '/../../fonctions/aide.php';

// Si pas connecté, stocker le jeton et rediriger vers inscription
$jeton = nettoyer($_GET['jeton'] ?? '');
if (empty($jeton)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Lien d\'invitation invalide.');
}

$invitation = validerJetonInvitation($jeton);
if (!$invitation) {
    $titrePage = 'Invitation invalide';
    require_once __DIR__ . '/../../gabarits/entete.php';
    echo '<div class="formulaire-conteneur"><div class="alerte alerte-danger">Ce lien d\'invitation est invalide ou expiré.</div><a href="' . APP_URL . '/pages/tableau_de_bord/accueil.php" class="btn btn-principal">Retour à l\'accueil</a></div>';
    require_once __DIR__ . '/../../gabarits/pied_de_page.php';
    exit;
}

if (!estConnecte()) {
    $_SESSION['invitation_jeton'] = $jeton;
    header('Location: ' . APP_URL . '/inscription.php');
    exit;
}

// Accepter l'invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $res = accepterInvitation($jeton, idUtilisateurConnecte());
    if ($res['succes']) {
        redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $invitation['tontine_id'], 'succes', 'Vous avez rejoint la tontine "' . $invitation['nom_tontine'] . '" avec succès !');
    } else {
        redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', $res['message']);
    }
}

$titrePage = 'Rejoindre - ' . $invitation['nom_tontine'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <h1>Invitation à rejoindre une tontine</h1>
</div>

<div class="formulaire-conteneur">

    <div class="banniere-guide-page">
        <div class="banniere-guide-page__icone">🤝</div>
        <div class="banniere-guide-page__contenu">
            <h2 class="banniere-guide-page__titre">Guide de participation à la tontine</h2>
            <p class="banniere-guide-page__texte">Rejoindre une tontine est un acte de solidarité et d'engagement mutuel :</p>
            <ul class="banniere-guide-page__etapes">
                <li><strong>Cotisation régulière :</strong> Vous vous engagez à verser votre cotisation à chaque échéance fixée par le groupe.</li>
                <li><strong>Tour de cagnotte :</strong> Votre tour d'encaissement sera déterminé selon les règles du groupe (tirage ou ordre d'adhésion).</li>
                <li><strong>Score de confiance :</strong> Votre ponctualité renforce votre réputation sur l'ensemble de la plateforme.</li>
            </ul>
            <button type="button" class="banniere-guide-page__lien" onclick="ouvrirGuide('rejoindre')">
                En savoir plus sur les règles d'adhésion &rarr;
            </button>
        </div>
    </div>

    <div class="carte-confirmation">
        <h3>Vous avez été invité !</h3>
        <p>Vous avez reçu une invitation pour rejoindre la tontine :</p>
        <div class="info-tontine-invitation">
            <strong><?= htmlspecialchars($invitation['nom_tontine']) ?></strong>
        </div>
        <p class="texte-secondaire">Cette invitation expire le <?= formaterDate($invitation['date_expiration']) ?>.</p>

        <form method="POST" action="">
            <?= champCsrf() ?>
            <div class="actions-formulaire">
                <a href="<?= APP_URL ?>/pages/tableau_de_bord/accueil.php" class="btn btn-secondaire">Refuser</a>
                <button type="submit" class="btn btn-principal">Rejoindre la tontine</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
