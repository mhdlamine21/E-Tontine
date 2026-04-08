<?php


require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/notification.php';
require_once __DIR__ . '/mail.php';

/**
 * Appliquer une amende à un membre en retard
 */
function appliquerAmende(int $tontineId, int $cycleId, int $membreId, float $montant, string $typeCalcul, int $joursRetard, string $motif = ''): array {
    $bd = connexionBD();
    
    try {
        // Vérifier si une amende existe déjà pour ce membre sur ce cycle
        $req = $bd->prepare('SELECT id FROM amendes WHERE tontine_id = ? AND cycle_id = ? AND membre_id = ?');
        $req->execute([$tontineId, $cycleId, $membreId]);
        if ($req->fetch()) {
            return ['succes' => false, 'message' => 'Une amende a déjà été appliquée à ce membre pour ce cycle.'];
        }
        
        // Vérifier si la colonne motif existe, sinon adapter la requête
        $columns = $bd->query("SHOW COLUMNS FROM amendes")->fetchAll(PDO::FETCH_COLUMN);
        $hasMotif = in_array('motif', $columns);
        
        if ($hasMotif) {
            $stmt = $bd->prepare('INSERT INTO amendes (tontine_id, cycle_id, membre_id, montant, type_calcul, jours_retard, date_creation, motif) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
            $stmt->execute([$tontineId, $cycleId, $membreId, $montant, $typeCalcul, $joursRetard, $motif]);
        } else {
            $stmt = $bd->prepare('INSERT INTO amendes (tontine_id, cycle_id, membre_id, montant, type_calcul, jours_retard, date_creation) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$tontineId, $cycleId, $membreId, $montant, $typeCalcul, $joursRetard]);
        }
        
        $amendeId = (int)$bd->lastInsertId();
        
        // Récupérer les infos du membre
        $req = $bd->prepare('SELECT nom_complet, email FROM utilisateurs WHERE id = ?');
        $req->execute([$membreId]);
        $membre = $req->fetch();
        
        // Récupérer la tontine
        $req = $bd->prepare('SELECT nom FROM tontines WHERE id = ?');
        $req->execute([$tontineId]);
        $tontine = $req->fetch();
        
        // Notification interne (toujours envoyée)
        creerNotification(
            $membreId,
            $tontineId,
            'amende',
            '⚠️ Amende appliquée',
            "Une amende de " . number_format($montant, 0, ',', ' ') . " FCFA vous a été appliquée pour retard de paiement sur le cycle en cours.",
            APP_URL . '/pages/cotisations/payer.php?tontine=' . $tontineId . '&cycle=' . $cycleId
        );
        
        // Email (optionnel, ne pas bloquer si échec)
        if ($membre && $tontine) {
            try {
                envoyerEmail(
                    $membre['email'],
                    $membre['nom_complet'],
                    'Amende sur votre tontine ' . $tontine['nom'],
                    "<h2>Amende appliquée</h2>
                     <p>Bonjour " . htmlspecialchars($membre['nom_complet']) . ",</p>
                     <p>Une amende de <strong>" . number_format($montant, 0, ',', ' ') . " FCFA</strong> vous a été appliquée sur la tontine <strong>" . htmlspecialchars($tontine['nom']) . "</strong>.</p>
                     <p>Vous devez payer votre cotisation + cette amende pour régulariser votre situation.</p>
                     <p><a href='" . APP_URL . "/pages/cotisations/payer.php?tontine=" . $tontineId . "&cycle=" . $cycleId . "'>Payer maintenant →</a></p>"
                );
            } catch (Exception $e) {
                // Ne pas bloquer si l'email échoue
                error_log('Erreur envoi email amende : ' . $e->getMessage());
            }
        }
        
        return ['succes' => true, 'message' => 'Amende appliquée avec succès.', 'amende_id' => $amendeId];
        
    } catch (Exception $e) {
        error_log('Erreur dans appliquerAmende : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur technique : ' . $e->getMessage()];
    }
}

/**
 * Récupérer le montant total des amendes non payées pour un membre sur un cycle
 */
function getAmendesNonPayees(int $tontineId, int $cycleId, int $membreId): float {
    $bd = connexionBD();
    $req = $bd->prepare('SELECT COALESCE(SUM(montant), 0) FROM amendes 
                         WHERE tontine_id = ? AND cycle_id = ? AND membre_id = ? AND est_paye = 0');
    $req->execute([$tontineId, $cycleId, $membreId]);
    return (float)$req->fetchColumn();
}

/**
 * Marquer une amende comme payée
 */
function marquerAmendePayee(int $amendeId): void {
    $bd = connexionBD();
    $bd->prepare('UPDATE amendes SET est_paye = 1, date_paiement = NOW() WHERE id = ?')
       ->execute([$amendeId]);
}

/**
 * Lister les amendes d'une tontine pour un cycle
 */
function listerAmendesCycle(int $tontineId, int $cycleId): array {
    $bd = connexionBD();
    $req = $bd->prepare('
        SELECT a.*, u.nom_complet AS membre_nom 
        FROM amendes a
        JOIN utilisateurs u ON u.id = a.membre_id
        WHERE a.tontine_id = ? AND a.cycle_id = ?
        ORDER BY a.date_creation DESC
    ');
    $req->execute([$tontineId, $cycleId]);
    return $req->fetchAll();
}