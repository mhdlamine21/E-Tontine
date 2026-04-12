<?php


require_once __DIR__ . '/../configuration/base_de_donnees.php';

// Créer une notification
function creerNotification(int $destinataireId, ?int $tontineId, string $type, string $titre, string $message, string $lien = ''): void {
    $bd = connexionBD();
    $bd->prepare('INSERT INTO notifications (destinataire_id, tontine_id, type, titre, message, lien) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$destinataireId, $tontineId, $type, $titre, $message, $lien]);
}

// Notifier tous les membres d'une tontine
function notifierTousMembres(int $tontineId, string $type, string $titre, string $message, string $lien = ''): void {
    $bd  = connexionBD();
    $req = $bd->prepare("SELECT utilisateur_id FROM membres_tontine WHERE tontine_id = ? AND statut != 'exclu'");
    $req->execute([$tontineId]);
    foreach ($req->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        creerNotification((int)$uid, $tontineId, $type, $titre, $message, $lien);
    }
}

// Récupérer les notifications d'un utilisateur
function notificationsUtilisateur(int $utilisateurId, int $limite = 20): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT n.*, t.nom AS nom_tontine FROM notifications n LEFT JOIN tontines t ON t.id = n.tontine_id WHERE n.destinataire_id = ? ORDER BY n.date_creation DESC LIMIT ?');
    $req->execute([$utilisateurId, $limite]);
    return $req->fetchAll();
}

// Compter les notifications non lues
function nbNotificationsNonLues(int $utilisateurId): int {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT COUNT(*) FROM notifications WHERE destinataire_id = ? AND est_lu = 0');
    $req->execute([$utilisateurId]);
    return (int)$req->fetchColumn();
}

// Marquer une notification comme lue
function marquerNotificationLue(int $notifId, int $utilisateurId): void {
    $bd = connexionBD();
    $bd->prepare('UPDATE notifications SET est_lu = 1 WHERE id = ? AND destinataire_id = ?')
       ->execute([$notifId, $utilisateurId]);
}

// Marquer toutes les notifications comme lues
function marquerToutesLues(int $utilisateurId): void {
    $bd = connexionBD();
    $bd->prepare('UPDATE notifications SET est_lu = 1 WHERE destinataire_id = ?')
       ->execute([$utilisateurId]);
}
