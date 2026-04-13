<?php
//  EXPORT CSV - SUPER ADMIN
require_once __DIR__ . '/../configuration/session.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../fonctions/aide.php';

exigerSuperAdmin();

$type = $_GET['type'] ?? '';

if (!in_array($type, ['tontines', 'utilisateurs', 'paiements'])) {
    die('Type d\'export invalide.');
}

$bd = connexionBD();

// Définir le nom du fichier
$filename = 'export_' . $type . '_' . date('Y-m-d') . '.csv';

// En-têtes HTTP pour forcer le téléchargement CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Créer le fichier en sortie
$output = fopen('php://output', 'w');

// Ajouter BOM pour UTF-8 (compatibilité Excel)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($type) {
    case 'tontines':
        fputcsv($output, ['ID', 'Nom', 'Description', 'Montant cotisation', 'Fréquence', 'Nombre max membres', 'Statut', 'Date création', 'Créateur ID', 'Créateur nom']);
        $req = $bd->query('SELECT t.*, u.nom_complet AS createur_nom FROM tontines t LEFT JOIN utilisateurs u ON u.id = t.createur_id ORDER BY t.date_creation DESC');
        while ($row = $req->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['nom'],
                $row['description'],
                $row['montant_cotisation'],
                $row['frequence'],
                $row['nombre_max_membres'],
                $row['statut'],
                $row['date_creation'],
                $row['createur_id'],
                $row['createur_nom']
            ]);
        }
        break;

    case 'utilisateurs':
        fputcsv($output, ['ID', 'Nom complet', 'Email', 'Téléphone', 'Rôle global', 'Bloqué', 'Date inscription', 'Dernière connexion']);
        $req = $bd->query('SELECT id, nom_complet, email, telephone, role_global, est_bloque, date_inscription, derniere_connexion FROM utilisateurs ORDER BY date_inscription DESC');
        while ($row = $req->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['nom_complet'],
                $row['email'],
                $row['telephone'],
                $row['role_global'],
                $row['est_bloque'] ? 'Oui' : 'Non',
                $row['date_inscription'],
                $row['derniere_connexion']
            ]);
        }
        break;

    case 'paiements':
        fputcsv($output, ['ID', 'Tontine ID', 'Cycle ID', 'Payeur ID', 'Payeur nom', 'Bénéficiaire ID', 'Bénéficiaire nom', 'Montant', 'Type', 'Mode', 'Statut', 'Référence', 'Date paiement']);
        $req = $bd->query('
            SELECT p.*, 
                   u1.nom_complet AS payeur_nom, 
                   u2.nom_complet AS beneficiaire_nom 
            FROM paiements p 
            LEFT JOIN utilisateurs u1 ON u1.id = p.payeur_id 
            LEFT JOIN utilisateurs u2 ON u2.id = p.beneficiaire_id 
            ORDER BY p.date_paiement DESC
        ');
        while ($row = $req->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['tontine_id'],
                $row['cycle_id'],
                $row['payeur_id'],
                $row['payeur_nom'],
                $row['beneficiaire_id'],
                $row['beneficiaire_nom'],
                $row['montant'],
                $row['type_paiement'],
                $row['mode_paiement'],
                $row['statut'],
                $row['reference'],
                $row['date_paiement']
            ]);
        }
        break;
}

fclose($output);
exit;