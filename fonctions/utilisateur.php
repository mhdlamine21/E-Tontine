<?php
// Fonctions liées aux comptes utilisateurs.

require_once __DIR__ . '/../configuration/base_de_donnees.php';
require_once __DIR__ . '/../configuration/constantes.php';

// Inscrire un nouvel utilisateur
function inscrireUtilisateur(string $nom, string $email, string $motDePasse, string $telephone): array {
    $bd = connexionBD();

    // Vérifier si email déjà utilisé
    $req = $bd->prepare('SELECT id FROM utilisateurs WHERE email = ?');
    $req->execute([$email]);
    if ($req->fetch()) {
        return ['succes' => false, 'message' => 'Cette adresse email est déjà utilisée.'];
    }

    $motDePasseHache = password_hash($motDePasse, PASSWORD_DEFAULT);
    $req  = $bd->prepare('INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, telephone) VALUES (?, ?, ?, ?)');
    $req->execute([$nom, $email, $motDePasseHache, $telephone]);

    return ['succes' => true, 'id' => (int)$bd->lastInsertId()];
}

// Connecter un utilisateur (vérifier identifiants)
function authentifierUtilisateur(string $email, string $motDePasse): array {
    $email = mb_strtolower(trim($email));
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT * FROM utilisateurs WHERE LOWER(email) = ?');
    $req->execute([$email]);
    $utilisateur = $req->fetch();

    if (!$utilisateur) {
        return ['succes' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }
    if ($utilisateur['est_bloque']) {
        return ['succes' => false, 'message' => 'Votre compte a été bloqué. Contactez l\'administrateur.'];
    }

    $hachageValide = password_verify($motDePasse, (string)$utilisateur['mot_de_passe']);
    // Compatibilité : anciens comptes créés avant la mise en place du hachage
    // (mot de passe encore stocké en clair) - on migre silencieusement au premier login réussi.
    $ancienMotDePasseEnClair = !$hachageValide && $motDePasse === (string)$utilisateur['mot_de_passe'];

    if (!$hachageValide && !$ancienMotDePasseEnClair) {
        return ['succes' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    if ($ancienMotDePasseEnClair) {
        $bd->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')
           ->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $utilisateur['id']]);
    } elseif (password_needs_rehash((string)$utilisateur['mot_de_passe'], PASSWORD_DEFAULT)) {
        $bd->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')
           ->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $utilisateur['id']]);
    }

    // Mettre à jour la dernière connexion
    $bd->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?')
       ->execute([$utilisateur['id']]);

    // S'assurer que le rôle global est bien défini (si NULL, mettre 'utilisateur')
    if (empty($utilisateur['role_global'])) {
        $utilisateur['role_global'] = ROLE_UTILISATEUR;
    }

    return ['succes' => true, 'utilisateur' => $utilisateur];
}

// Récupérer un utilisateur par son ID
function obtenirUtilisateur(int $id): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT id, nom_complet, email, telephone, role_global, est_bloque, date_inscription, derniere_connexion FROM utilisateurs WHERE id = ?');
    $req->execute([$id]);
    return $req->fetch() ?: null;
}

// Récupérer un utilisateur par email
function obtenirUtilisateurParEmail(string $email): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT id, nom_complet, email, telephone, role_global, est_bloque FROM utilisateurs WHERE email = ?');
    $req->execute([$email]);
    return $req->fetch() ?: null;
}

// Modifier le profil
function modifierProfil(int $id, string $nom, string $telephone): array {
    $bd  = connexionBD();
    $req = $bd->prepare('UPDATE utilisateurs SET nom_complet = ?, telephone = ? WHERE id = ?');
    $req->execute([$nom, $telephone, $id]);
    return ['succes' => true, 'message' => 'Profil mis à jour avec succès.'];
}

// Changer le mot de passe
function changerMotDePasse(int $id, string $ancienMdp, string $nouveauMdp): array {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = ?');
    $req->execute([$id]);
    $ligne = $req->fetch();

    $ancienValide = $ligne && (password_verify($ancienMdp, (string)$ligne['mot_de_passe'])
        || $ancienMdp === (string)$ligne['mot_de_passe']); // compat comptes pré-hachage
    if (!$ancienValide) {
        return ['succes' => false, 'message' => 'L\'ancien mot de passe est incorrect.'];
    }

    $bd->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')
       ->execute([password_hash($nouveauMdp, PASSWORD_DEFAULT), $id]);

    return ['succes' => true, 'message' => 'Mot de passe modifié avec succès.'];
}

// Lister tous les utilisateurs (super admin)
function listerTousUtilisateurs(): array {
    $bd  = connexionBD();
    $req = $bd->query('SELECT id, nom_complet, email, telephone, role_global, est_bloque, date_inscription FROM utilisateurs ORDER BY date_inscription DESC');
    return $req->fetchAll();
}

// Bloquer / débloquer un utilisateur
function bloquerUtilisateur(int $id, bool $bloquer): void {
    $bd = connexionBD();
    $bd->prepare('UPDATE utilisateurs SET est_bloque = ? WHERE id = ?')
       ->execute([$bloquer ? 1 : 0, $id]);
}

// Calculer le score de fiabilité d'un membre dans une tontine
function calculerScoreFiabilite(int $utilisateurId, int $tontineId): float {
    $bd  = connexionBD();
    $req = $bd->prepare('
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN statut = ? THEN 1 ELSE 0 END) AS a_temps
        FROM paiements
        WHERE payeur_id = ? AND tontine_id = ?
    ');
    $req->execute([PAIEMENT_PAYE, $utilisateurId, $tontineId]);
    $ligne = $req->fetch();

    if (!$ligne || (int)$ligne['total'] === 0) return 100.0;
    return round(((int)$ligne['a_temps'] / (int)$ligne['total']) * 100, 2);
}

// Mettre à jour le score de fiabilité en base
function majScoreFiabilite(int $utilisateurId, int $tontineId): void {
    $score = calculerScoreFiabilite($utilisateurId, $tontineId);
    $bd    = connexionBD();
    $bd->prepare('UPDATE membres_tontine SET score_fiabilite = ? WHERE utilisateur_id = ? AND tontine_id = ?')
       ->execute([$score, $utilisateurId, $tontineId]);
}

//  MOT DE PASSE OUBLIÉ

// Créer une demande de réinitialisation et envoyer l'email
// Répond toujours succès=true côté appelant (même si l'email n'existe pas) pour
// ne jamais révéler si une adresse email est inscrite ou non.
function creerDemandeReinitialisation(string $email): void {
    $utilisateur = obtenirUtilisateurParEmail(mb_strtolower(trim($email)));
    if (!$utilisateur) {
        return;
    }

    $bd    = connexionBD();
    $jeton = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $bd->prepare('INSERT INTO reinitialisations_mdp (utilisateur_id, jeton, date_expiration) VALUES (?, ?, ?)')
       ->execute([$utilisateur['id'], $jeton, $expiration]);

    $lien = APP_URL . '/reinitialiser_mot_de_passe.php?jeton=' . $jeton;
    require_once __DIR__ . '/mail.php';
    envoyerEmailReinitialisation($utilisateur['email'], $utilisateur['nom_complet'], $lien);
}

// Valider un jeton de réinitialisation (non expiré, non utilisé)
function validerJetonReinitialisation(string $jeton): ?array {
    $bd  = connexionBD();
    $req = $bd->prepare('
        SELECT r.*, u.nom_complet, u.email
        FROM reinitialisations_mdp r
        JOIN utilisateurs u ON u.id = r.utilisateur_id
        WHERE r.jeton = ? AND r.utilise = 0 AND r.date_expiration > NOW()
    ');
    $req->execute([$jeton]);
    return $req->fetch() ?: null;
}

// Appliquer le nouveau mot de passe et invalider le jeton
function reinitialiserMotDePasse(string $jeton, string $nouveauMdp): array {
    $demande = validerJetonReinitialisation($jeton);
    if (!$demande) {
        return ['succes' => false, 'message' => 'Ce lien de réinitialisation est invalide ou a expiré.'];
    }

    $bd = connexionBD();
    $bd->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')
       ->execute([password_hash($nouveauMdp, PASSWORD_DEFAULT), $demande['utilisateur_id']]);
    $bd->prepare('UPDATE reinitialisations_mdp SET utilise = 1 WHERE id = ?')
       ->execute([$demande['id']]);

    return ['succes' => true, 'message' => 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.'];
}