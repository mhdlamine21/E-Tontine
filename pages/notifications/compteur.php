<?php
//  NOTIFICATIONS - COMPTEUR AJAX
//  Retourne le nombre de notifications non lues en JSON
//  Appelé par ressources/scripts/notifications.js
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/notification.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!estConnecte()) {
    echo json_encode(['nb' => 0]);
    exit;
}

// Vérifier que c'est bien une requête AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['erreur' => 'Requête invalide']);
    exit;
}

$nb = nbNotificationsNonLues(idUtilisateurConnecte());
echo json_encode(['nb' => $nb]);
