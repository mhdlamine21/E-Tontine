<?php
// POST uniquement - démarrer la tontine (brouillon → active)
declare(strict_types=1);

require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifierCsrf()) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Requête invalide.');
}

$tontineId     = obtenirPostInt('tontine_id');
$utilisateurId = idUtilisateurConnecte();

$res = demarrerActiviteTontine($tontineId, $utilisateurId);
redirigerAvecMessage(
    APP_URL . '/pages/tontines/voir.php?id=' . $tontineId,
    $res['succes'] ? 'succes' : 'erreur',
    $res['message']
);
