<?php
//  NOTIFICATIONS - LISTE COMPLÈTE
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/notification.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$utilisateurId = idUtilisateurConnecte();

// Marquer toutes comme lues si demandé (POST avec CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tout_lire']) && verifierCsrf()) {
    marquerToutesLues($utilisateurId);
    redirigerAvecMessage(APP_URL . '/pages/notifications/liste.php', 'succes', 'Toutes les notifications ont été marquées comme lues.');
}

// Marquer une seule comme lue
if (isset($_GET['lire'])) {
    marquerNotificationLue((int)$_GET['lire'], $utilisateurId);
}

$notifications = notificationsUtilisateur($utilisateurId, 100);
$nbNonLues     = nbNotificationsNonLues($utilisateurId);

$titrePage = 'Notifications';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <h1>Notifications</h1>
        <?php if ($nbNonLues > 0): ?>
        <p class="texte-secondaire"><?= $nbNonLues ?> non lue(s)</p>
        <?php endif; ?>
    </div>
    <?php if ($nbNonLues > 0): ?>
    <form method="POST" action="">
        <?= champCsrf() ?>
        <button type="submit" name="tout_lire" value="1" class="btn btn-secondaire">Tout marquer comme lu</button>
    </form>
    <?php endif; ?>
</div>

<section class="section-tableau">

    <?php if (empty($notifications)): ?>
    <div class="etat-vide">
        <p>Aucune notification pour le moment.</p>
    </div>
    <?php else: ?>
    <ul class="liste-notifications-completes">
        <?php foreach ($notifications as $notif): ?>
        <li class="notif-item-complet <?= $notif['est_lu'] ? '' : 'notif-non-lue' ?>">
            <div class="notif-icone notif-icone-<?= str_replace('_', '-', $notif['type']) ?>">
                <?php
                $icone = match(true) {
                    str_contains($notif['type'], 'paiement')   => '💳',
                    str_contains($notif['type'], 'cagnotte')   => '💰',
                    str_contains($notif['type'], 'urgence')    => '🚨',
                    str_contains($notif['type'], 'vote')       => '🗳️',
                    str_contains($notif['type'], 'admin')      => '👑',
                    str_contains($notif['type'], 'invitation') => '✉️',
                    default                                     => '🔔',
                };
                echo $icone;
                ?>
            </div>
            <div class="notif-contenu">
                <div class="notif-titre-complet">
                    <?= htmlspecialchars($notif['titre']) ?>
                    <?php if ($notif['nom_tontine']): ?>
                    <span class="badge badge-secondaire" style="margin-left:6px"><?= htmlspecialchars($notif['nom_tontine']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="notif-message-complet"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="notif-meta">
                    <span><?= formaterDateHeure($notif['date_creation']) ?></span>
                    <?php if ($notif['lien']): ?>
                    <a href="<?= htmlspecialchars($notif['lien']) ?>"
                       onclick="fetch('?lire=<?= $notif['id'] ?>')"
                       class="lien-secondaire">Voir →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$notif['est_lu']): ?>
            <div class="notif-point-rouge"></div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>