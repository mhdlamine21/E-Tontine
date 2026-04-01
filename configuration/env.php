<?php
//  Chargement simple de variables d'environnement (.env)
//  - Optionnel: si le fichier .env n'existe pas, ne fait rien.
//  - Ne surcharge pas les variables déjà définies dans l'environnement.

function chargerEnvDepuisFichier(string $chemin): void {
    if (!is_file($chemin) || !is_readable($chemin)) return;

    $lignes = file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lignes) return;

    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#')) continue;

        $pos = strpos($ligne, '=');
        if ($pos === false) continue;

        $cle = trim(substr($ligne, 0, $pos));
        $val = trim(substr($ligne, $pos + 1));

        if ($cle === '') continue;
        if (getenv($cle) !== false) continue;

        // Retirer guillemets simples/doubles si présents
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }

        putenv($cle . '=' . $val);
        $_ENV[$cle] = $val;
        $_SERVER[$cle] = $val;
    }
}

