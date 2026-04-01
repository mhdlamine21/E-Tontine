<?php
//  CONSTANTES GLOBALES - E-TONTINE

require_once __DIR__ . '/env.php';
chargerEnvDepuisFichier(__DIR__ . '/../.env');

// Application
define('APP_NOM',         'E-Tontine');
define('APP_VERSION',     '1.0');
define('APP_URL',         getenv('APP_URL') !== false ? rtrim(getenv('APP_URL'), '/') : 'http://localhost/etontine');
/** Logos et emblèmes de l'application (formats SVG vectoriels adaptatifs et PNG haute définition) */
define('APP_LOGO_URL',        APP_URL . '/ressources/images/logo.svg');
define('APP_LOGO_PNG_URL',    APP_URL . '/ressources/images/logo.png');
define('APP_EMBLEM_URL',      APP_URL . '/ressources/images/logo_emblem.svg');
define('APP_EMBLEM_PNG_URL',  APP_URL . '/ressources/images/logo_emblem.png');

// Rôles globaux
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_UTILISATEUR',  'utilisateur');

// Rôles dans une tontine
define('ROLE_ADMIN',   'admin');
define('ROLE_MEMBRE',  'membre');

// Statuts membre
define('STATUT_ACTIF',   'actif');
define('STATUT_RETARD',  'retard');
define('STATUT_EXCLU',   'exclu');

// Statuts paiement
define('PAIEMENT_PAYE',       'paye');
define('PAIEMENT_EN_ATTENTE', 'en_attente');
define('PAIEMENT_RETARD',     'retard');

// Types de paiement
define('TYPE_NORMAL',      'normal');
define('TYPE_POUR_AUTRUI', 'pour_autrui');

// Modes de paiement
define('MODE_WAVE',         'wave');
define('MODE_ORANGE_MONEY', 'orange_money');
define('MODE_ESPECES',      'especes');

// Fréquences
define('FREQUENCE_HEBDO',      'hebdomadaire');
define('FREQUENCE_MENSUELLE',  'mensuelle');
define('FREQUENCE_TRIMESTRIELLE', 'trimestrielle');

// Statuts tontine
define('TONTINE_BROUILLON', 'brouillon'); // créée, pas encore « démarrée » (pas de cotisations)
define('TONTINE_ACTIVE',    'active');
define('TONTINE_FERMEE',    'fermee');
define('TONTINE_SUSPENDUE', 'suspendue'); // arrêtée temporairement par l'admin

// Statuts cycle
define('CYCLE_EN_COURS',  'en_cours');
define('CYCLE_CLOS',      'clos');
define('CYCLE_DISTRIBUE', 'distribue');

// Statuts urgence
define('URGENCE_EN_ATTENTE', 'en_attente');
define('URGENCE_VALIDEE',    'validee');
define('URGENCE_REFUSEE',    'refusee');

// Statuts distribution
define('DISTRIB_EN_ATTENTE', 'en_attente');
define('DISTRIB_PARTIEL',    'partiel');
define('DISTRIB_COMPLET',    'complet');

// Statuts vote
define('VOTE_OUVERT', 'ouvert');
define('VOTE_CLOS',   'clos');

// Seuils de gouvernance
define('SEUIL_PETITION_TIERS',  1/3);   // 1/3 des membres pour déclencher vote
define('SEUIL_VOTE_POURCENT',   75.0);  // 75% de OUI pour changer l'admin

// Pagination
define('ELEMENTS_PAR_PAGE', 15);

// Invitations
define('INVITATION_DUREE_JOURS', 7);

// Durée session (secondes)
define('SESSION_DUREE', 3600 * 8); // 8 heures

// Devise
define('DEVISE', 'FCFA');

// Correspondance fréquence → jours
function frequenceEnJours(string $frequence): int {
    return match($frequence) {
        FREQUENCE_HEBDO          => 7,
        FREQUENCE_MENSUELLE      => 30,
        FREQUENCE_TRIMESTRIELLE  => 90,
        default                  => 30,
    };
}

// Libellés lisibles
function libelleFrequence(string $frequence): string {
    return match($frequence) {
        FREQUENCE_HEBDO          => 'Hebdomadaire',
        FREQUENCE_MENSUELLE      => 'Mensuelle',
        FREQUENCE_TRIMESTRIELLE  => 'Trimestrielle',
        default                  => $frequence,
    };
}

function libelleStatutTontine(string $statut): string {
    return match ($statut) {
        TONTINE_BROUILLON => 'En préparation',
        TONTINE_ACTIVE    => 'En cours',
        TONTINE_SUSPENDUE => 'Arrêtée',
        TONTINE_FERMEE    => 'Fermée',
        default           => $statut,
    };
}

function libelleStatutMembre(string $statut): string {
    return match($statut) {
        STATUT_ACTIF   => 'Actif',
        STATUT_RETARD  => 'En retard',
        STATUT_EXCLU   => 'Exclu',
        default        => $statut,
    };
}

function libelleModePaiement(string $mode): string {
    return match($mode) {
        MODE_WAVE         => 'Wave Money',
        MODE_ORANGE_MONEY => 'Orange Money',
        MODE_ESPECES      => 'Espèces',
        default           => $mode,
    };
}
