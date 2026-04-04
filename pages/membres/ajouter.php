<?php
//  MEMBRES - AJOUTER PAR EMAIL
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/invitation.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
$erreurs = [];
$lienInvitation = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_invitation']) && verifierCsrf()) {
    $resInv = creerInvitation($tontineId, $utilisateurId);
    $lienInvitation = $resInv['lien'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf() && !isset($_POST['action_invitation'])) {
    $email = nettoyer($_POST['email'] ?? '');
    if (!estEmailValide($email)) {
        $erreurs[] = 'Email invalide.';
    } else {
        $res = ajouterMembreParEmail($tontineId, $email);
        if ($res['succes']) {
            redirigerAvecMessage(APP_URL . '/pages/membres/liste.php?tontine=' . $tontineId, 'succes', 'Membre ajouté avec succès.');
        } else {
            $erreurs[] = $res['message'];
        }
    }
}

$titrePage = 'Ajouter un membre';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/membres/liste.php?tontine=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Ajouter un membre</h1>
    </div>
</div>

<div class="formulaire-conteneur">
    <?php foreach ($erreurs as $err): ?>
    <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($lienInvitation)): ?>
    <div class="alerte alerte-succes">
        <strong>Lien d'invitation généré :</strong>
        <div class="lien-copiable">
            <input type="text" id="lienInvitAjout" value="<?= htmlspecialchars($lienInvitation) ?>" readonly>
            <button type="button" onclick="copierLienAjout()" class="btn btn-secondaire btn-petit">Copier</button>
        </div>
        <small>Ce lien expire dans <?= INVITATION_DUREE_JOURS ?> jours.</small>
    </div>
    <?php endif; ?>

    <p>Saisissez l'adresse email d'un utilisateur déjà inscrit sur E-Tontine pour l'ajouter à la tontine <strong><?= htmlspecialchars($tontine['nom']) ?></strong>.</p>

    <form method="POST" action="">
        <?= champCsrf() ?>
        <div class="champ">
            <label for="email">Adresse email du membre</label>
            <input type="email" id="email" name="email" placeholder="membre@email.com" required autofocus>
        </div>
        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/membres/liste.php?tontine=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Ajouter le membre</button>
        </div>
    </form>

    <div class="separateur-ou"><span>ou</span></div>

    <h2 class="sous-titre-form">Inviter par lien</h2>
    <p class="texte-secondaire">Pour quelqu’un qui n’a pas encore de compte : générez un lien à envoyer par WhatsApp ou SMS.</p>
    <form method="POST" action="">
        <?= champCsrf() ?>
        <input type="hidden" name="action_invitation" value="1">
        <button type="submit" class="btn btn-secondaire">Générer un lien d’invitation</button>
    </form>
</div>

<script>
function copierLienAjout() {
    var el = document.getElementById('lienInvitAjout');
    var valeur = el ? el.value : '';
    if (!valeur) return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(valeur).then(function() { alert('Lien copié !'); }).catch(function() { alert('Copie impossible.'); });
        return;
    }
    if (el) el.select();
    alert('Sélectionnez le lien et copiez (Ctrl+C).');
}
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
