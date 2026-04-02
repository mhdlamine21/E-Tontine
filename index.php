<?php
declare(strict_types=1);

require_once __DIR__ . '/configuration/session.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/aide.php';

if (estConnecte()) {
    // Redirection selon le rôle
    if (estSuperAdmin()) {
        header('Location: ' . APP_URL . '/pages/super_admin/tableau_de_bord.php');
        exit;
    }
    header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
    exit;
}
// Visiteur non connecté : landing page publique avec accès modale rapide
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NOM ?> - Gérez vos tontines en toute simplicité</title>
    <meta name="description" content="E-Tontine digitalise la gestion de vos tontines : cotisations, tours, cagnotte, urgences et votes, en toute transparence.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= APP_EMBLEM_URL ?>" type="image/svg+xml">
    <link rel="alternate icon" href="<?= APP_EMBLEM_PNG_URL ?>" type="image/png">
    <link rel="stylesheet" href="<?= APP_URL ?>/ressources/styles/principal.css">
</head>
<body class="page-landing">

<header class="landing-nav">
    <div class="landing-nav__contenu">
        <a href="<?= APP_URL ?>/" class="nav-logo" title="<?= htmlspecialchars(APP_NOM) ?> - Accueil">
            <img src="<?= APP_EMBLEM_URL ?>" alt="<?= htmlspecialchars(APP_NOM) ?> Logo" class="nav-logo-img" width="44" height="44" decoding="async">
            <span class="nav-logo-texte"><?= APP_NOM ?></span>
        </a>
        <div class="landing-nav__actions">
            <a href="<?= APP_URL ?>/connexion.php" class="btn btn-secondaire" data-auth-mode="connexion">Se connecter</a>
            <a href="<?= APP_URL ?>/inscription.php" class="btn btn-principal" data-auth-mode="inscription">S'inscrire</a>
        </div>
    </div>
</header>

<main class="landing-hero">
    <div class="landing-hero__contenu">
        <h1>Gérez vos tontines<br>en toute simplicité</h1>
        <p class="landing-hero__soustitre">
            Cotisations, tours, cagnotte, urgences et votes : digitalisez votre tontine et gardez une confiance totale entre membres, où que vous soyez.
        </p>
        <div class="landing-hero__cta">
            <a href="<?= APP_URL ?>/inscription.php" class="btn btn-principal btn-grand" data-auth-mode="inscription">Créer mon compte gratuitement</a>
            <a href="<?= APP_URL ?>/connexion.php" class="btn btn-secondaire btn-grand" data-auth-mode="connexion">J'ai déjà un compte</a>
        </div>
    </div>
</main>

<section class="landing-etapes">
    <h2 class="landing-etapes__titre">Comment ça marche</h2>
    <div class="landing-etapes__grille">
        <div class="landing-etape">
            <div class="landing-etape__numero">1</div>
            <h3>Créez votre tontine</h3>
            <p>Fixez le montant, la fréquence et le nombre de membres. Vous devenez automatiquement administrateur.</p>
        </div>
        <div class="landing-etape">
            <div class="landing-etape__numero">2</div>
            <h3>Invitez vos membres</h3>
            <p>Par email ou par lien d'invitation partageable sur WhatsApp - jusqu'à ce que l'effectif soit complet.</p>
        </div>
        <div class="landing-etape">
            <div class="landing-etape__numero">3</div>
            <h3>Cotisez en toute transparence</h3>
            <p>Suivez la cagnotte en temps réel, recevez votre tour, gérez les urgences et votez ensemble en cas de besoin.</p>
        </div>
    </div>
</section>

<footer class="landing-pied">
    <span><?= APP_NOM ?> &copy; <?= date('Y') ?></span>
</footer>

<!-- MODALE D'AUTHENTIFICATION POPUP -->
<?php require_once __DIR__ . '/gabarits/modal_auth.php'; ?>

<!-- SCRIPT DE GESTION DE LA MODALE -->
<script src="<?= APP_URL ?>/ressources/scripts/auth_modal.js" defer></script>

</body>
</html>
