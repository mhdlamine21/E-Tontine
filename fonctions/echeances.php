<?php


declare(strict_types=1);

require_once __DIR__ . '/../configuration/constantes.php';

/**
 * Premier jour de la semaine (lundi=1 … dimanche=7) tombant le même jour ou après $dateDebutYmd.
 */
function premiereEcheanceHebdomadaire(string $dateDebutYmd, int $jourSemaine1a7): string {
    $jourSemaine1a7 = max(1, min(7, $jourSemaine1a7));
    $debut = new DateTimeImmutable($dateDebutYmd);
    $n0 = (int)$debut->format('N');
    $delta = ($jourSemaine1a7 - $n0 + 7) % 7;
    return $debut->modify('+' . $delta . ' days')->format('Y-m-d');
}

/**
 * Prochaine date limite (jour du mois 1-31) à partir de $dateDebutYmd (inclus).
 */
function premiereEcheanceMensuelle(string $dateDebutYmd, int $jourDuMois1a31): string {
    $jourDuMois1a31 = max(1, min(31, $jourDuMois1a31));
    $debut = new DateTimeImmutable($dateDebutYmd);
    $y = (int)$debut->format('Y');
    $m = (int)$debut->format('m');
    $last = (int)$debut->format('t');
    $d = min($jourDuMois1a31, $last);
    $candidate = sprintf('%04d-%02d-%02d', $y, $m, $d);
    if ($candidate >= $dateDebutYmd) {
        return $candidate;
    }
    $next = $debut->modify('first day of next month');
    $y = (int)$next->format('Y');
    $m = (int)$next->format('m');
    $last = (int)$next->format('t');
    $d = min($jourDuMois1a31, $last);

    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/**
 * Date limite pour fréquence trimestrielle
 */
function premiereEcheanceTrimestrielle(string $dateDebutYmd, int $jourDuMois1a31): string {
    $jourDuMois1a31 = max(1, min(31, $jourDuMois1a31));
    $debut = new DateTimeImmutable($dateDebutYmd);
    $debut = $debut->modify('+3 months');
    $y = (int)$debut->format('Y');
    $m = (int)$debut->format('m');
    $last = (int)$debut->format('t');
    $d = min($jourDuMois1a31, $last);
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/**
 * Date limite de versement pour un cycle qui démarre à $dateDebutYmd.
 */
function calculerDateLimiteCycle(
    string $frequence,
    ?int $echeanceJourSemaine,
    ?int $echeanceJourMois,
    string $dateDebutYmd
): string {
    if ($frequence === FREQUENCE_HEBDO && $echeanceJourSemaine !== null && $echeanceJourSemaine >= 1) {
        return premiereEcheanceHebdomadaire($dateDebutYmd, $echeanceJourSemaine);
    }
    if ($frequence === FREQUENCE_TRIMESTRIELLE && $echeanceJourMois !== null && $echeanceJourMois >= 1) {
        return premiereEcheanceTrimestrielle($dateDebutYmd, $echeanceJourMois);
    }
    if (($frequence === FREQUENCE_MENSUELLE) && $echeanceJourMois !== null && $echeanceJourMois >= 1) {
        return premiereEcheanceMensuelle($dateDebutYmd, $echeanceJourMois);
    }
    // Anciennes tontines sans échéance : fin de période approximative
    $jours = frequenceEnJours($frequence);
    return (new DateTimeImmutable($dateDebutYmd))->modify('+' . max(1, $jours) . ' days')->format('Y-m-d');
}

/** Libellé du jour de la semaine (1-7). */
function libelleJourSemaine(int $n): string {
    $n = max(1, min(7, $n));
    return [
        1 => 'lundi',
        2 => 'mardi',
        3 => 'mercredi',
        4 => 'jeudi',
        5 => 'vendredi',
        6 => 'samedi',
        7 => 'dimanche',
    ][$n] ?? '';
}

/** Texte récap échéance pour affichage fiche tontine. */
function texteRecapEcheanceTontine(array $tontine): string {
    $f = $tontine['frequence'] ?? '';
    if ($f === FREQUENCE_HEBDO && !empty($tontine['echeance_jour_semaine'])) {
        $lib = libelleJourSemaine((int)$tontine['echeance_jour_semaine']);
        return 'Échéance chaque ' . $lib . ' (versements au plus tard ce jour-là).';
    }
    if (($f === FREQUENCE_MENSUELLE || $f === FREQUENCE_TRIMESTRIELLE) && !empty($tontine['echeance_jour_mois'])) {
        return 'Échéance le ' . (int)$tontine['echeance_jour_mois'] . ' de chaque mois (versements au plus tard ce jour).';
    }
    return '';
}