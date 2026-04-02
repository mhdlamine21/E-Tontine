<?php
//  MOT DE PASSE OUBLIÉ
require_once __DIR__ . '/configuration/session.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/utilisateur.php';
require_once __DIR__ . '/fonctions/aide.php';

if (estConnecte()) {
    header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
    exit;
}

$erreurs = [];
$envoye  = false;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide. Veuillez réessayer.';
    } else {
        $email = nettoyer($_POST['email'] ?? '');
        if (empty($email) || !estEmailValide($email)) {
            $erreurs[] = 'Veuillez saisir une adresse email valide.';
        } else {
            // Ne révèle jamais si l'email existe ou non - message identique dans tous les cas.
            creerDemandeReinitialisation($email);
            $envoye = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - <?= APP_NOM ?></title>
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
        <h2>Mot de passe oublié</h2>

        <?php if ($envoye): ?>
        <div class="alerte alerte-succes">
            Si un compte existe avec cette adresse, un email de réinitialisation vient d'être envoyé. Vérifiez votre boîte de réception (et vos spams).
        </div>
        <?php else: ?>
        <p style="margin-bottom:16px;color:#8c7a65;font-size:14px">Saisissez votre adresse email : un lien de réinitialisation valable 1 heure vous sera envoyé.</p>

        <?php foreach ($erreurs as $err): ?>
        <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="">
            <?= champCsrf() ?>
            <div class="champ">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="votre@email.com" required autofocus>
            </div>
            <button type="submit" class="btn btn-principal btn-bloc">Envoyer le lien de réinitialisation</button>
        </form>
        <?php endif; ?>

        <p class="auth-lien">
            <a href="<?= APP_URL ?>/connexion.php">← Retour à la connexion</a>
        </p>
    </div>
</div>

</body>
</html>
