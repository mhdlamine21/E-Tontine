<?php


require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/aide.php';

// Créer un lien d'invitation
function creerInvitation(int $tontineId, int $inviteurId, string $emailInvite = ''): array {
    $bd    = connexionBD();
    $jeton = genererJeton(32);
    $expiration = date('Y-m-d H:i:s', strtotime('+' . INVITATION_DUREE_JOURS . ' days'));

    $bd->prepare('INSERT INTO invitations (tontine_id, invite_par_id, email_invite, jeton, date_expiration) VALUES (?, ?, ?, ?, ?)')
       ->execute([$tontineId, $inviteurId, $emailInvite ?: null, $jeton, $expiration]);

    $lien = APP_URL . '/pages/tontines/rejoindre.php?jeton=' . $jeton;
    return ['succes' => true, 'lien' => $lien, 'jeton' => $jeton];
}

// Valider un jeton d'invitation
function validerJetonInvitation(string $jeton): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT i.*, t.nom AS nom_tontine FROM invitations i JOIN tontines t ON t.id = i.tontine_id WHERE i.jeton = ? AND i.statut = 'en_attente' AND i.date_expiration > NOW()");
    $req->execute([$jeton]);
    return $req->fetch() ?: null;
}

// Accepter une invitation
function accepterInvitation(string $jeton, int $utilisateurId): array {
    $invitation = validerJetonInvitation($jeton);
    if (!$invitation) {
        return ['succes' => false, 'message' => 'Ce lien d\'invitation est invalide ou expiré.'];
    }

    require_once __DIR__ . '/membre.php';
    $bd = connexionBD();

    // Ajouter le membre
    $req = $bd->prepare('SELECT email FROM utilisateurs WHERE id = ?');
    $req->execute([$utilisateurId]);
    $ligne = $req->fetch();
    $resultat = ajouterMembreParEmail((int)$invitation['tontine_id'], $ligne['email']);

    if ($resultat['succes']) {
        $bd->prepare("UPDATE invitations SET statut = 'acceptee' WHERE jeton = ?")
           ->execute([$jeton]);
    }

    return $resultat;
}
