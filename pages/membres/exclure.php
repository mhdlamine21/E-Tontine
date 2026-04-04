<?php
//  MEMBRES - EXCLURE
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$membreId      = obtenirGetInt('membre');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $res = exclureMembre($tontineId, $membreId);
    redirigerAvecMessage(
        APP_URL . '/pages/membres/liste.php?tontine=' . $tontineId,
        $res['succes'] ? 'succes' : 'erreur',
        $res['message']
    );
}

redirigerAvecMessage(APP_URL . '/pages/membres/liste.php?tontine=' . $tontineId, 'erreur', 'Action invalide.');
