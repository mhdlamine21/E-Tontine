<?php
//  GESTION DES SESSIONS

require_once __DIR__ . '/constantes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_DUREE,
        'path'     => '/',
        'secure'   => false,      // mettre true en production HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Vérifier si l'utilisateur est connecté
function estConnecte(): bool {
    return isset($_SESSION['utilisateur_id']) && !empty($_SESSION['utilisateur_id']);
}

// Exiger une connexion (sinon rediriger)
function exigerConnexion(): void {
    if (!estConnecte()) {
        header('Location: ' . APP_URL . '/connexion.php');
        exit;
    }
}

// Exiger le rôle super admin
function exigerSuperAdmin(): void {
    exigerConnexion();
    if ($_SESSION['role_global'] !== ROLE_SUPER_ADMIN) {
        header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
        exit;
    }
}

// Connecter un utilisateur (créer la session)
function connecterUtilisateur(array $utilisateur): void {
    session_regenerate_id(true);
    $_SESSION['utilisateur_id']  = $utilisateur['id'];
    $_SESSION['nom_complet']     = $utilisateur['nom_complet'];
    $_SESSION['email']           = $utilisateur['email'];
    $_SESSION['telephone']       = $utilisateur['telephone'];
    $_SESSION['role_global']     = $utilisateur['role_global'];
    $_SESSION['connecte_le']     = time();
}

// Déconnecter l'utilisateur
function deconnecterUtilisateur(): void {
    $_SESSION = [];
    session_destroy();
}

// Récupérer l'ID de l'utilisateur connecté
function idUtilisateurConnecte(): int {
    return (int)($_SESSION['utilisateur_id'] ?? 0);
}

// Récupérer le nom de l'utilisateur connecté
function nomUtilisateurConnecte(): string {
    return $_SESSION['nom_complet'] ?? '';
}

// Vérifier si super admin
function estSuperAdmin(): bool {
    return isset($_SESSION['role_global']) && $_SESSION['role_global'] === ROLE_SUPER_ADMIN;
}

// Stocker un message flash (succès ou erreur)
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'][$type][] = $message;
}

// Récupérer et effacer les messages flash
function obtenirFlash(): array {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

// Vérifier expiration de session
function verifierExpirationSession(): void {
    if (estConnecte()) {
        $dureeInactivite = time() - ($_SESSION['connecte_le'] ?? 0);
        if ($dureeInactivite > SESSION_DUREE) {
            deconnecterUtilisateur();
            header('Location: ' . APP_URL . '/connexion.php?expiration=1');
            exit;
        }
        $_SESSION['connecte_le'] = time();
    }
}
