<?php
// Fonctions d'audit et de journalisation des événements système

require_once __DIR__ . '/../configuration/base_de_donnees.php';

/**
 * Enregistre une action dans les logs d'audit.
 */
function ajouterLog(string $action, int $utilisateurId = 0, string $details = '', ?int $tontineId = null): bool {
    try {
        $bd = connexionBD();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userId = $utilisateurId > 0 ? $utilisateurId : null;

        $stmt = $bd->prepare('
            INSERT INTO logs (action, utilisateur_id, details, tontine_id, ip_adresse, date_creation)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        return $stmt->execute([$action, $userId, $details, $tontineId, $ip]);
    } catch (Exception $e) {
        error_log('Erreur journalisation log: ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste paginée des logs avec le nom de l'utilisateur associé.
 */
function listerLogs(int $limit = 50, int $offset = 0): array {
    $bd = connexionBD();
    $stmt = $bd->prepare('
        SELECT l.*, u.nom_complet AS utilisateur_nom
        FROM logs l
        LEFT JOIN utilisateurs u ON u.id = l.utilisateur_id
        ORDER BY l.date_creation DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Compte le nombre total de logs enregistrés.
 */
function compterLogs(): int {
    $bd = connexionBD();
    $stmt = $bd->query('SELECT COUNT(*) FROM logs');
    return (int)$stmt->fetchColumn();
}

/**
 * Nettoie les anciens logs antérieurs à un certain nombre de jours.
 */
function nettoyerLogs(int $jours = 90): int {
    $bd = connexionBD();
    $stmt = $bd->prepare('DELETE FROM logs WHERE date_creation < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->bindValue(1, $jours, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
}