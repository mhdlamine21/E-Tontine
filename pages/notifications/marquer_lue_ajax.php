<?php
declare(strict_types=1);

require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/notification.php';
require_once __DIR__ . '/../../fonctions/aide.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!estConnecte()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifierCsrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}

$uid = idUtilisateurConnecte();
marquerNotificationLue($id, $uid);
echo json_encode([
    'ok' => true,
    'nb' => nbNotificationsNonLues($uid),
]);
