<?php
//  RÉINITIALISER LE MOT DE PASSE (via jeton reçu par email)
require_once __DIR__ . '/configuration/session.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/utilisateur.php';
require_once __DIR__ . '/fonctions/aide.php';

$jeton = nettoyer($_GET['jeton'] ?? $_POST['jeton'] ?? '');
$demande = $jeton !== '' ? validerJetonReinitialisation($jeton) : null;

$erreurs = [];
$succes  = false;

if (!$demande) {
    $erreurs[] = 'Ce lien de réinitialisation est invalide ou a expiré. Refaites une demande.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide. Veuillez réessayer.';
    } else {
        $nouveauMdp   = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        if (strlen($nouveauMdp) < 8) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($nouveauMdp !== $confirmation) {
            $erreurs[] = 'Les mots de passe ne correspondent pas.';
        } else {
            $res = reinitialiserMotDePasse($jeton, $nouveauMdp);
            if ($res['succes']) {
                $succes = true;
            } else {
                $erreurs[] = $res['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - <?= APP_NOM ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= APP_LOGO_URL ?>" type="image/png">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/principal.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/formulaires.css">
</head>
<body class="page-auth">

<div class="auth-conteneur">
    <div class="auth-logo">
        <img src="<?= APP_LOGO_URL ?>" alt="<?= htmlspecialchars(APP_NOM) ?>" class="auth-logo-img" width="176" height="176" decoding="async">
        <h1 class="auth-logo-titre"><?= APP_NOM ?></h1>
    </div>

    <div class="auth-carte">
        <h2>Nouveau mot de passe</h2>

        <?php foreach ($erreurs as $err): ?>
        <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <?php if ($succes): ?>
        <div class="alerte alerte-succes">Votre mot de passe a été mis à jour avec succès.</div>
        <p class="auth-lien"><a href="<?= APP_URL ?>/connexion.php">Se connecter →</a></p>
        <?php elseif ($demande): ?>
        <p style="margin-bottom:16px;color:#8c7a65;font-size:14px">Bonjour <?= htmlspecialchars($demande['nom_complet']) ?>, choisissez votre nouveau mot de passe.</p>
        <form method="POST" action="">
            <?= champCsrf() ?>
            <input type="hidden" name="jeton" value="<?= htmlspecialchars($jeton) ?>">
            <div class="champ">
                <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8" placeholder="8 caractères minimum">
            </div>
            <div class="champ">
                <label for="confirmation">Confirmer le nouveau mot de passe</label>
                <input type="password" id="confirmation" name="confirmation" required minlength="8">
            </div>
            <button type="submit" class="btn btn-principal btn-bloc">Réinitialiser mon mot de passe</button>
        </form>
        <?php else: ?>
        <p class="auth-lien"><a href="<?= APP_URL ?>/mot_de_passe_oublie.php">Faire une nouvelle demande →</a></p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
