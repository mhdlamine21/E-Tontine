<?php

require_once __DIR__ . '/../configuration/constantes.php';

// Nettoyer une chaîne de caractères
function nettoyer(string $valeur): string {
    return htmlspecialchars(trim($valeur), ENT_QUOTES, 'UTF-8');
}

// Nettoyer un tableau de données POST
function nettoyerPost(array $champs): array {
    $resultat = [];
    foreach ($champs as $champ) {
        $resultat[$champ] = isset($_POST[$champ]) ? nettoyer($_POST[$champ]) : '';
    }
    return $resultat;
}

// Valider un email
function estEmailValide(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Valider un numéro de téléphone sénégalais
function estTelephoneValide(string $telephone): bool {
    $tel = preg_replace('/\s+/', '', $telephone);
    return (bool)preg_match('/^(\+221)?7[0-9]{8}$/', $tel);
}

// Formater une date en français
function formaterDate(string $dateString): string {
    if (!$dateString) return '-';
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
             'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $ts = strtotime($dateString);
    return date('d', $ts) . ' ' . $mois[(int)date('m', $ts)] . ' ' . date('Y', $ts);
}

// Formater une date et heure
function formaterDateHeure(string $dateString): string {
    if (!$dateString) return '-';
    return formaterDate($dateString) . ' à ' . date('H\hi', strtotime($dateString));
}

// Calculer le temps écoulé
function tempsEcoule(string $dateString): string {
    $diff = time() - strtotime($dateString);
    if ($diff < 60)      return 'à l\'instant';
    if ($diff < 3600)    return (int)($diff / 60) . ' min';
    if ($diff < 86400)   return (int)($diff / 3600) . 'h';
    if ($diff < 2592000) return (int)($diff / 86400) . 'j';
    return formaterDate($dateString);
}

// Générer un jeton aléatoire sécurisé
function genererJeton(int $longueur = 32): string {
    return bin2hex(random_bytes($longueur));
}

// Rediriger avec un message flash
function redirigerAvecMessage(string $url, string $type, string $message): void {
    require_once __DIR__ . '/../configuration/session.php';
    flashMessage($type, $message);
    header('Location: ' . $url);
    exit;
}

// Obtenir la valeur GET entière sécurisée
function obtenirGetInt(string $cle, int $defaut = 0): int {
    return isset($_GET[$cle]) ? (int)$_GET[$cle] : $defaut;
}

function obtenirPostInt(string $cle, int $defaut = 0): int {
    return isset($_POST[$cle]) ? (int)$_POST[$cle] : $defaut;
}

// Obtenir la valeur POST sécurisée
function obtenirPost(string $cle, string $defaut = ''): string {
    return isset($_POST[$cle]) ? nettoyer($_POST[$cle]) : $defaut;
}

// Vérifier le token CSRF
function verifierCsrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Générer un token CSRF
function genererCsrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = genererJeton(16);
    }
    return $_SESSION['csrf_token'];
}

// Champ CSRF caché pour les formulaires
function champCsrf(): string {
    return '<input type="hidden" name="csrf_token" value="' . genererCsrf() . '">';
}

// Badge de statut HTML
function badgeStatut(string $statut): string {
    return match($statut) {
        STATUT_ACTIF   => '<span class="badge badge-succes">Actif</span>',
        STATUT_RETARD  => '<span class="badge badge-alerte">En retard</span>',
        STATUT_EXCLU   => '<span class="badge badge-danger">Exclu</span>',
        PAIEMENT_PAYE  => '<span class="badge badge-succes">Payé</span>',
        'en_attente'   => '<span class="badge badge-info">En attente</span>',
        'validee'      => '<span class="badge badge-succes">Validée</span>',
        'refusee'      => '<span class="badge badge-danger">Refusée</span>',
        default        => '<span class="badge">' . nettoyer($statut) . '</span>',
    };
}

// Tronquer un texte
function tronquer(string $texte, int $longueur = 80): string {
    return mb_strlen($texte) > $longueur ? mb_substr($texte, 0, $longueur) . '…' : $texte;
}

// Formater un montant
function formaterMontant(float $montant): string {
    return number_format($montant, 0, ',', ' ') . ' ' . DEVISE;
}

// Pourcentage sécurisé
function pourcentage(float $valeur, float $total): float {
    if ($total <= 0) return 0.0;
    return round(($valeur / $total) * 100, 1);
}

// Pagination générique d'un tableau déjà chargé en mémoire
// Retourne ['items' => <sous-ensemble de la page>, 'page' => n, 'total_pages' => n, 'total' => n]
function paginer(array $items, int $page = 1, int $parPage = ELEMENTS_PAR_PAGE): array {
    $total = count($items);
    $totalPages = max(1, (int)ceil($total / max(1, $parPage)));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $parPage;
    return [
        'items'       => array_slice($items, $offset, $parPage),
        'page'        => $page,
        'total_pages' => $totalPages,
        'total'       => $total,
    ];
}

// Barre de pagination HTML réutilisable
function rendreLiensPagination(int $page, int $totalPages, string $urlBase): string {
    if ($totalPages <= 1) return '';
    $sep = str_contains($urlBase, '?') ? '&' : '?';
    $html = '<div class="pagination" style="margin-top:16px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $actif = $i === $page ? ' actif' : '';
        $html .= '<a href="' . htmlspecialchars($urlBase . $sep . 'page=' . $i) . '" class="btn btn-secondaire btn-petit' . $actif . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}
