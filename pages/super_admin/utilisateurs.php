<?php
//  SUPER ADMIN - UTILISATEURS
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/utilisateur.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $action = nettoyer($_POST['action'] ?? '');
    $cibleId = (int)($_POST['utilisateur_id'] ?? 0);
    if ($cibleId && $cibleId !== idUtilisateurConnecte()) {
        if ($action === 'bloquer')   bloquerUtilisateur($cibleId, true);
        if ($action === 'debloquer') bloquerUtilisateur($cibleId, false);
        redirigerAvecMessage(APP_URL . '/pages/super_admin/utilisateurs.php', 'succes',
            'Utilisateur ' . ($action === 'bloquer' ? 'bloqué' : 'débloqué') . '.');
    }
}

$recherche    = nettoyer($_GET['q'] ?? '');
$utilisateurs = listerTousUtilisateurs();

if ($recherche) {
    $utilisateurs = array_filter($utilisateurs, fn($u) =>
        stripos($u['nom_complet'], $recherche) !== false ||
        stripos($u['email'],       $recherche) !== false
    );
}

$titrePage = 'Gestion des utilisateurs';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/super_admin/tableau_de_bord.php" class="lien-retour">← Retour</a>
        <h1>Gestion des utilisateurs</h1>
        <p class="texte-secondaire"><?= count($utilisateurs) ?> utilisateur(s)</p>
    </div>
</div>

<!-- Recherche -->
<div class="barre-recherche">
    <form method="GET" action="">
        <input type="search" name="q" value="<?= htmlspecialchars($recherche) ?>"
               placeholder="Rechercher par nom ou email..." style="width:320px">
        <button type="submit" class="btn btn-secondaire">Rechercher</button>
        <?php if ($recherche): ?>
        <a href="?" class="btn btn-secondaire">Réinitialiser</a>
        <?php endif; ?>
    </form>
</div>

<section class="section-tableau">
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Rôle</th>
                    <th>Inscription</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                <tr class="<?= $u['est_bloque'] ? 'ligne-bloquee' : '' ?>">
                    <td><?= htmlspecialchars($u['nom_complet']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['telephone']) ?></td>
                    <td>
                        <?php if ($u['role_global'] === ROLE_SUPER_ADMIN): ?>
                        <span class="badge badge-info">Super Admin</span>
                        <?php else: ?>
                        Utilisateur
                        <?php endif; ?>
                    </td>
                    <td><?= formaterDate($u['date_inscription']) ?></td>
                    <td>
                        <?= $u['est_bloque']
                            ? '<span class="badge badge-danger">Bloqué</span>'
                            : '<span class="badge badge-succes">Actif</span>' ?>
                    </td>
                    <td>
                        <?php if ($u['id'] != idUtilisateurConnecte() && $u['role_global'] !== ROLE_SUPER_ADMIN): ?>
                        <form method="POST" action="" style="display:inline">
                            <?= champCsrf() ?>
                            <input type="hidden" name="utilisateur_id" value="<?= $u['id'] ?>">
                            <?php if ($u['est_bloque']): ?>
                            <button type="submit" name="action" value="debloquer"
                                    class="btn btn-succes btn-tres-petit"
                                    onclick="return confirm('Débloquer cet utilisateur ?')">
                                Débloquer
                            </button>
                            <?php else: ?>
                            <button type="submit" name="action" value="bloquer"
                                    class="btn btn-danger btn-tres-petit"
                                    onclick="return confirm('Bloquer cet utilisateur ?')">
                                Bloquer
                            </button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?>
                        <span class="texte-secondaire">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
