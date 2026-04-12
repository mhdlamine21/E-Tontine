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
    echo json_encode(['items' => [], 'nb' => 0]);
    exit;
}

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['erreur' => 'Requête invalide']);
    exit;
}

$uid = idUtilisateurConnecte();
$lim = min(30, max(1, (int)($_GET['limite'] ?? 15)));
$rows = notificationsUtilisateur($uid, $lim);
$items = [];
foreach ($rows as $n) {
    $items[] = [
        'id'      => (int)$n['id'],
        'titre'   => $n['titre'],
        'message' => tronquer((string)$n['message'], 140),
        'lien'    => (string)($n['lien'] ?? ''),
        'lu'      => (bool)(int)$n['est_lu'],
        'date'    => tempsEcoule($n['date_creation']),
        'tontine' => (string)($n['nom_tontine'] ?? ''),
    ];
}

echo json_encode([
    'items' => $items,
    'nb'    => nbNotificationsNonLues($uid),
], JSON_UNESCAPED_UNICODE);
