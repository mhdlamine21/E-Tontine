<?php

require_once __DIR__ . '/configuration/session.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/utilisateur.php';
require_once __DIR__ . '/fonctions/aide.php';

if (estConnecte()) {
    header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
    exit;
}

$erreurs   = [];
$donnees   = ['nom' => '', 'email' => '', 'telephone' => ''];

$estRequeteAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide ou jeton expiré. Veuillez rafraîchir la page.';
    } else {
        $donnees['nom']       = nettoyer($_POST['nom']       ?? '');
        $donnees['email']     = nettoyer($_POST['email']     ?? '');
        $donnees['telephone'] = nettoyer($_POST['telephone'] ?? '');
        $motDePasse           = $_POST['mot_de_passe']       ?? '';
        $confirmation         = $_POST['confirmation']       ?? '';

        if (empty($donnees['nom']))       $erreurs[] = 'Le nom complet est obligatoire.';
        if (empty($donnees['email']))     $erreurs[] = 'L\'email est obligatoire.';
        elseif (!estEmailValide($donnees['email'])) $erreurs[] = 'Adresse email invalide.';
        if (empty($donnees['telephone'])) $erreurs[] = 'Le téléphone est obligatoire.';
        elseif (!estTelephoneValide($donnees['telephone'])) $erreurs[] = 'Numéro de téléphone invalide (ex: 77 123 45 67).';
        if (strlen($motDePasse) < 8)      $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if ($motDePasse !== $confirmation) $erreurs[] = 'Les mots de passe ne correspondent pas.';

        if (empty($erreurs)) {
            $resultat = inscrireUtilisateur($donnees['nom'], $donnees['email'], $motDePasse, $donnees['telephone']);
            if ($resultat['succes']) {
                $util = [
                    'id' => $resultat['id'],
                    'nom_complet' => $donnees['nom'],
                    'email' => $donnees['email'],
                    'telephone' => $donnees['telephone'],
                    'role_global' => ROLE_UTILISATEUR
                ];
                connecterUtilisateur($util);
                
                $cible = APP_URL . '/pages/tableau_de_bord/accueil.php';
                // Vérifier s'il y a une invitation en attente
                if (isset($_SESSION['invitation_jeton'])) {
                    $jeton = $_SESSION['invitation_jeton'];
                    unset($_SESSION['invitation_jeton']);
                    $cible = APP_URL . '/pages/tontines/rejoindre.php?jeton=' . urlencode($jeton);
                }

                if ($estRequeteAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'succes' => true,
                        'message' => 'Compte créé avec succès ! Bienvenue.',
                        'redirection' => $cible
                    ]);
                    exit;
                }
                
                redirigerAvecMessage($cible, 'succes', 'Bienvenue sur E-Tontine ! Votre compte a été créé.');
            } else {
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= APP_NOM ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/principal.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/formulaires.css">
</head>
<body class="page-auth">

<div class="auth-conteneur">
    <div class="auth-logo">
        <h1>E-Tontine</h1>
        <p>Créez votre compte et gérez vos tontines</p>
    </div>

    <div class="auth-carte">
        <h2>Créer un compte</h2>

        <?php foreach ($erreurs as $err): ?>
        <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="">
            <?= champCsrf() ?>

            <div class="champ">
                <label for="nom">Nom complet</label>
                <input type="text" id="nom" name="nom"
                       value="<?= htmlspecialchars($donnees['nom']) ?>"
                       placeholder="Ali Sow" required autofocus>
            </div>

            <div class="champ">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($donnees['email']) ?>"
                       placeholder="votre@email.com" required>
            </div>

            <div class="champ">
                <label for="telephone">Numéro de téléphone</label>
                <input type="tel" id="telephone" name="telephone"
                       value="<?= htmlspecialchars($donnees['telephone']) ?>"
                       placeholder="77 123 45 67" required>
            </div>

            <div class="champ">
                <label for="mot_de_passe">Mot de passe</label>
                <div class="champ-avec-icone">
                    <input type="password" id="mot_de_passe" name="mot_de_passe"
                           placeholder="8 caractères minimum" required minlength="8">
                    <button type="button" class="btn-voir-mdp" onclick="toggleMdp('mot_de_passe')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="champ">
                <label for="confirmation">Confirmer le mot de passe</label>
                <input type="password" id="confirmation" name="confirmation"
                       placeholder="Répéter le mot de passe" required>
            </div>

            <button type="submit" class="btn btn-principal btn-bloc">Créer mon compte</button>
        </form>

        <p class="auth-lien">
            Déjà un compte ?
            <a href="<?= APP_URL ?>/connexion.php">Se connecter</a>
        </p>
    </div>
</div>

<script src="<?= APP_URL ?>/ressources/scripts/principal.js"></script>
</body>
</html>