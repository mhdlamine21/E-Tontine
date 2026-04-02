<?php
//  CONNEXION
require_once __DIR__ . '/configuration/session.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/utilisateur.php';
require_once __DIR__ . '/fonctions/aide.php';

if (estConnecte()) {
    // Redirection selon le rôle
    if (estSuperAdmin()) {
        header('Location: ' . APP_URL . '/pages/super_admin/tableau_de_bord.php');
    } else {
        header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
    }
    exit;
}

$erreurs = [];
$email   = '';

// Rate limiting basique contre le bruteforce (protection minimale, par session)
const CONNEXION_MAX_TENTATIVES = 5;
const CONNEXION_BLOCAGE_SECONDES = 60;
$_SESSION['tentatives_connexion'] = $_SESSION['tentatives_connexion'] ?? ['nb' => 0, 'depuis' => time()];
if (time() - $_SESSION['tentatives_connexion']['depuis'] > CONNEXION_BLOCAGE_SECONDES) {
    $_SESSION['tentatives_connexion'] = ['nb' => 0, 'depuis' => time()];
}
$bloque = $_SESSION['tentatives_connexion']['nb'] >= CONNEXION_MAX_TENTATIVES;

$estRequeteAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($bloque) {
        $erreurs[] = 'Trop de tentatives. Réessayez dans une minute.';
    } elseif (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide ou jeton expiré. Veuillez rafraîchir la page.';
    } else {
        $email    = nettoyer($_POST['email']    ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (empty($email) || empty($motDePasse)) {
            $erreurs[] = 'Veuillez remplir tous les champs.';
        } elseif (!estEmailValide($email)) {
            $erreurs[] = 'Adresse email invalide.';
        } else {
            $resultat = authentifierUtilisateur($email, $motDePasse);
            require_once __DIR__ . '/fonctions/logs.php';
            if ($resultat['succes']) {
                unset($_SESSION['tentatives_connexion']);
                ajouterLog('connexion_reussie', (int)$resultat['utilisateur']['id'], 'Connexion réussie');
                connecterUtilisateur($resultat['utilisateur']);
                $cible = estSuperAdmin() ? (APP_URL . '/pages/super_admin/tableau_de_bord.php') : (APP_URL . '/pages/tableau_de_bord/accueil.php');
                if ($estRequeteAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'succes' => true,
                        'message' => 'Connexion réussie',
                        'redirection' => $cible
                    ]);
                    exit;
                }
                header('Location: ' . $cible);
                exit;
            } else {
                $_SESSION['tentatives_connexion']['nb']++;
                ajouterLog('connexion_echouee', 0, 'Échec de connexion pour ' . $email);
                $erreurs[] = $resultat['message'];
            }
        }
    }

    if ($estRequeteAjax && !empty($erreurs)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'succes' => false,
            'erreurs' => $erreurs
        ]);
        exit;
    }
}

$expiration = isset($_GET['expiration']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NOM ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
        <p>Gérez vos tontines en toute simplicité</p>
    </div>

    <div class="auth-carte">
        <h2>Se connecter</h2>

        <?php if ($expiration): ?>
        <div class="alerte alerte-alerte">Votre session a expiré. Veuillez vous reconnecter.</div>
        <?php endif; ?>

        <?php foreach ($erreurs as $err): ?>
        <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="">
            <?= champCsrf() ?>

            <div class="champ">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="votre@email.com" required autofocus>
            </div>

            <div class="champ">
                <label for="mot_de_passe">Mot de passe</label>
                <div class="champ-avec-icone">
                    <input type="password" id="mot_de_passe" name="mot_de_passe"
                           placeholder="Votre mot de passe" required>
                    <button type="button" class="btn-voir-mdp" onclick="toggleMdp('mot_de_passe')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <small style="text-align:right;display:block;margin-top:6px">
                    <a href="<?= APP_URL ?>/mot_de_passe_oublie.php">Mot de passe oublié ?</a>
                </small>
            </div>

            <button type="submit" class="btn btn-principal btn-bloc">Se connecter</button>
        </form>

        <p class="auth-lien">
            Pas encore de compte ?
            <a href="<?= APP_URL ?>/inscription.php">S'inscrire gratuitement</a>
        </p>
    </div>
</div>

<script src="<?= APP_URL ?>/ressources/scripts/principal.js"></script>
</body>
</html>