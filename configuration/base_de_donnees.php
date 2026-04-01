<?php
//  CONNEXION À LA BASE DE DONNÉES - PDO

require_once __DIR__ . '/env.php';

// Charger un éventuel fichier .env à la racine du projet
chargerEnvDepuisFichier(__DIR__ . '/../.env');

define('DB_HOTE',       getenv('DB_HOST')    !== false ? getenv('DB_HOST')    : 'localhost');
define('DB_NOM',        getenv('DB_NAME')    !== false ? getenv('DB_NAME')    : 'etontine');
define('DB_UTILISATEUR',getenv('DB_USER')    !== false ? getenv('DB_USER')    : 'root');
define('DB_MOT_DE_PASSE',getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
define('DB_CHARSET',    getenv('DB_CHARSET') !== false ? getenv('DB_CHARSET') : 'utf8mb4');

function connexionBD(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOTE
             . ';dbname=' . DB_NOM
             . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_UTILISATEUR, DB_MOT_DE_PASSE, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Erreur BDD : ' . $e->getMessage());
            throw new Exception('Connexion impossible à la base de données.');
        }
    }
    return $pdo;
}
