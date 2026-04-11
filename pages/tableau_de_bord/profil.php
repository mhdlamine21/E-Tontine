<?php
//  PROFIL - MODIFIER SES INFORMATIONS
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/utilisateur.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$utilisateurId = idUtilisateurConnecte();
$utilisateur   = obtenirUtilisateur($utilisateurId);
$erreurs       = [];

// Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_profil'])) {
    if (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide.';
    } else {
        $nom       = nettoyer($_POST['nom']       ?? '');
        $telephone = nettoyer($_POST['telephone'] ?? '');

        if (empty($nom))       $erreurs[] = 'Le nom est obligatoire.';
        if (empty($telephone)) $erreurs[] = 'Le téléphone est obligatoire.';
        elseif (!estTelephoneValide($telephone)) $erreurs[] = 'Numéro de téléphone invalide.';

        if (empty($erreurs)) {
            modifierProfil($utilisateurId, $nom, $telephone);
            $_SESSION['nom_complet'] = $nom;
            $_SESSION['telephone']   = $telephone;
            $utilisateur = obtenirUtilisateur($utilisateurId);
            redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/profil.php', 'succes', 'Profil mis à jour.');
        }
    }
}

// Changement de mot de passe
$erreursMdp = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_mdp'])) {
    if (!verifierCsrf()) {
        $erreursMdp[] = 'Requête invalide.';
    } else {
        $ancienMdp = $_POST['ancien_mot_de_passe'] ?? '';
        $nouveauMdp = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        if (empty($ancienMdp) || empty($nouveauMdp)) $erreursMdp[] = 'Tous les champs sont requis.';
        elseif (strlen($nouveauMdp) < 8) $erreursMdp[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        elseif ($nouveauMdp !== $confirmation) $erreursMdp[] = 'Les mots de passe ne correspondent pas.';

        if (empty($erreursMdp)) {
            $res = changerMotDePasse($utilisateurId, $ancienMdp, $nouveauMdp);
            if ($res['succes']) {
                redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/profil.php', 'succes', 'Mot de passe modifié avec succès.');
            } else {
                $erreursMdp[] = $res['message'];
            }
        }
    }
}

$titrePage = 'Mon profil';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <h1>Mon profil</h1>
</div>

<div class="grille-deux-colonnes">

    <!-- Modifier le profil -->
    <section class="section-tableau">
        <div class="section-entete"><h2>Informations personnelles</h2></div>

        <?php foreach ($erreurs as $err): ?>
        <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="">
            <?= champCsrf() ?>
            <input type="hidden" name="action_profil" value="1">

            <div class="champ">
                <label>Nom complet</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($utilisateur['nom_complet']) ?>" required>
            </div>

            <div class="champ">
                <label>Adresse email</label>
                <input type="email" value="<?= htmlspecialchars($utilisateur['email']) ?>" disabled>
                <small class="texte-secondaire">L'email ne peut pas être modifié.</small>
            </div>

            <div class="champ">
                <label>Téléphone</label>
                <input type="tel" name="telephone" value="<?= htmlspecialchars($utilisateur['telephone']) ?>" required>
            </div>

            <div class="champ">
                <label>Rôle</label>
                <input type="text" value="<?= $utilisateur['role_global'] === ROLE_SUPER_ADMIN ? 'Super Administrateur' : 'Utilisateur' ?>" disabled>
            </div>

            <div class="champ">
                <label>Membre depuis</label>
                <input type="text" value="<?= formaterDate($utilisateur['date_inscription']) ?>" disabled>
            </div>

            <button type="submit" class="btn btn-principal">Enregistrer les modifications</button>
        </form>
    </section>

    <!-- Changer le mot de passe -->
    <section class="section-tableau">
        <div class="section-entete"><h2>Changer le mot de passe</h2></div>

        <?php foreach ($erreursMdp as $err): ?>
        <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="">
            <?= champCsrf() ?>
            <input type="hidden" name="action_mdp" value="1">

            <div class="champ">
                <label>Ancien mot de passe</label>
                <input type="password" name="ancien_mot_de_passe" required>
            </div>

            <div class="champ">
                <label>Nouveau mot de passe</label>
                <input type="password" name="nouveau_mot_de_passe" required minlength="8">
                <small class="texte-secondaire">Minimum 8 caractères.</small>
            </div>

            <div class="champ">
                <label>Confirmer le nouveau mot de passe</label>
                <input type="password" name="confirmation" required>
            </div>

            <button type="submit" class="btn btn-secondaire">Changer le mot de passe</button>
        </form>
    </section>

</div>

<!--  AJOUT : Score de fiabilité par tontine                       -->
<section class="section-tableau">
    <div class="section-entete"><h2>📊 Mon score de fiabilité par tontine</h2></div>
    
    <?php
    $bd = connexionBD();
    $req = $bd->prepare('
        SELECT t.nom AS tontine_nom, mt.score_fiabilite, mt.role
        FROM membres_tontine mt
        JOIN tontines t ON t.id = mt.tontine_id
        WHERE mt.utilisateur_id = ? AND mt.statut != ?
        ORDER BY mt.score_fiabilite DESC
    ');
    $req->execute([$utilisateurId, STATUT_EXCLU]);
    $scores = $req->fetchAll();
    ?>
    
    <?php if (empty($scores)): ?>
    <div class="etat-vide"><p>Vous n'êtes membre d'aucune tontine pour le moment.</p></div>
    <?php else: ?>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr><th>Tontine</th><th>Rôle</th><th>Score de fiabilité</th><th>Niveau</th></tr>
            </thead>
            <tbody>
                <?php foreach ($scores as $s): 
                    $score = (float)$s['score_fiabilite'];
                    if ($score >= 80) {
                        $niveau = 'Excellent';
                        $couleur = '#1D9E75';
                        $badge = 'badge-succes';
                    } elseif ($score >= 60) {
                        $niveau = 'Bon';
                        $couleur = '#e0a63e';
                        $badge = 'badge-info';
                    } elseif ($score >= 40) {
                        $niveau = 'Moyen';
                        $couleur = '#BA7517';
                        $badge = 'badge-alerte';
                    } else {
                        $niveau = 'À améliorer';
                        $couleur = '#E24B4A';
                        $badge = 'badge-danger';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($s['tontine_nom']) ?></td>
                    <td><?= $s['role'] === ROLE_ADMIN ? '<span class="badge badge-info">Admin</span>' : '<span class="badge badge-secondaire">Membre</span>' ?></td>
                    <td><strong><?= number_format($score, 1) ?>%</strong></td>
                    <td><span class="<?= $badge ?>"><?= $niveau ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="info-encadre" style="margin-top:16px">
        <strong>💡 Comment est calculé votre score ?</strong><br>
        Le score de fiabilité est basé sur vos paiements à temps. Plus vous payez dans les délais, plus votre score est élevé.
        Un score élevé vous donne plus de crédibilité lors des votes et des candidatures.
    </div>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>