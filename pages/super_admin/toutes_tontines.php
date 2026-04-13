<?php
//  SUPER ADMIN - TOUTES LES TONTINES
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $tontineIdAction = (int)($_POST['tontine_id'] ?? 0);
    if ($tontineIdAction) {
        supprimerTontine($tontineIdAction);
        redirigerAvecMessage(APP_URL . '/pages/super_admin/toutes_tontines.php', 'succes', 'Tontine supprimée.');
    }
}

$recherche = nettoyer($_GET['q'] ?? '');
$filtre    = nettoyer($_GET['statut'] ?? '');
$tontines  = listerToutesTontines();

if ($recherche) {
    $tontines = array_filter($tontines, fn($t) =>
        stripos($t['nom'], $recherche) !== false ||
        stripos($t['nom_createur'], $recherche) !== false
    );
}
if ($filtre) {
    $tontines = array_filter($tontines, fn($t) => $t['statut'] === $filtre);
}

$titrePage = 'Toutes les tontines';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/super_admin/tableau_de_bord.php" class="lien-retour">← Retour</a>
        <h1>Toutes les tontines</h1>
        <p class="texte-secondaire"><?= count($tontines) ?> tontine(s)</p>
    </div>
</div>

<!-- Filtres -->
<div class="barre-recherche">
    <form method="GET" action="" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="search" name="q" value="<?= htmlspecialchars($recherche) ?>"
               placeholder="Rechercher..." style="width:240px">
        <select name="statut">
            <option value="">Tous les statuts</option>
            <option value="active"    <?= $filtre === 'active'    ? 'selected':'' ?>>Active</option>
            <option value="fermee"    <?= $filtre === 'fermee'    ? 'selected':'' ?>>Fermée</option>
            <option value="suspendue" <?= $filtre === 'suspendue' ? 'selected':'' ?>>Suspendue</option>
        </select>
        <button type="submit" class="btn btn-secondaire">Filtrer</button>
        <?php if ($recherche || $filtre): ?>
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
                    <th>Créateur</th>
                    <th>Membres</th>
                    <th>Cotisation</th>
                    <th>Fréquence</th>
                    <th>Statut</th>
                    <th>Créée le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tontines as $t): ?>
                <tr>
                    <td>
                        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['nom']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($t['nom_createur']) ?></td>
                    <td><?= $t['nb_membres'] ?> / <?= $t['nombre_max_membres'] ?></td>
                    <td><?= formaterMontant((float)$t['montant_cotisation']) ?></td>
                    <td><?= libelleFrequence($t['frequence']) ?></td>
                    <td><?= badgeStatut($t['statut'] === 'active' ? 'actif' : ($t['statut'] === 'fermee' ? 'exclu' : 'retard')) ?></td>
                    <td><?= formaterDate($t['date_creation']) ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline">
                            <?= champCsrf() ?>
                            <input type="hidden" name="tontine_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-tres-petit"
                                    onclick="return confirm('Supprimer définitivement la tontine &quot;<?= addslashes($t['nom']) ?>&quot; ? Cette action est irréversible.')">
                                Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
