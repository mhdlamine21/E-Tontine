<?php

require_once __DIR__ . '/configuration/env.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/mail.php';

echo "=== Test PHPMailer ===\n";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USER: " . (SMTP_USER !== '' ? SMTP_USER : '(non configuré)') . "\n";
echo "SMTP_PASS: " . (SMTP_PASS !== '' ? '********' : '(non configuré)') . "\n";

if (empty(SMTP_USER) || empty(SMTP_PASS)) {
    echo "\n[INFO] SMTP_USER ou SMTP_PASS n'est pas encore défini dans le fichier .env.\n";
    echo "Renseignez SMTP_USER (votre adresse Gmail) et SMTP_PASS (mot de passe d'application Google de 16 caractères).\n";
    exit(1);
}

$destinataire = $argv[1] ?? SMTP_USER;
echo "\nEnvoi d'un email de test vers: $destinataire ...\n";

$sujet = "Test PHPMailer - E-Tontine";
$corps = "<h1>Succès !</h1><p>Le test d'envoi d'email depuis <strong>E-Tontine</strong> a réussi avec succès via PHPMailer.</p>";

$succes = envoyerEmail($destinataire, "Utilisateur Test", $sujet, $corps);

if ($succes) {
    echo "[SUCCÈS] L'email de test a été envoyé avec succès !\n";
} else {
    echo "[ERREUR] Échec de l'envoi de l'email. Vérifiez les logs ou les identifiants SMTP.\n";
}
