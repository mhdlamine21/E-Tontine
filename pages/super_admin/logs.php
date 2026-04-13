<?php
//  SUPER ADMIN - VISUALISATION DES LOGS
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/logs.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerSuperAdmin();

$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$logs = listerLogs($limit, $offset);
$totalLogs = compterLogs();
$totalPages = ceil($totalLogs / $limit);

// Nettoyage des anciens logs si demandé
if (isset($_GET['nettoyer']) && $_GET['nettoyer'] === '1') {
    nettoyerLogs(90);
    redirigerAvecMessage(APP_URL . '/pages/super_admin/logs.php', 'succes', 'Logs de plus de 90 jours supprimés.');
}

$titrePage = 'Logs système';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/super_admin/tableau_de_bord.php" class="lien-retour">← Retour</a>
        <h1>Logs système</h1>
        <p class="texte-secondaire">Historique des actions sensibles</p>
    </div>
    <div class="actions-groupe">
        <a href="?nettoyer=1" class="btn btn-danger btn-petit" onclick="return confirm('Supprimer les logs de plus de 90 jours ?')">🗑️ Nettoyer (90j+)</a>
    </div>
</div>

<section class="section-tableau">
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Détails</th>
                    <th>Tontine</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="centrer">Aucun log enregistré.</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= formaterDateHeure($log['date_creation']) ?></td>
                    <td>
                        <?php if ($log['utilisateur_id']): ?>
                        <a href="<?= APP_URL ?>/pages/super_admin/utilisateurs.php?q=<?= urlencode($log['utilisateur_nom']) ?>">
                            <?= htmlspecialchars($log['utilisateur_nom'] ?? 'ID ' . $log['utilisateur_id']) ?>
                        </a>
                        <?php else: ?>
                        <span class="texte-secondaire">Système</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                    <td>
                        <?php if ($log['tontine_id']): ?>
                        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $log['tontine_id'] ?>">Tontine #<?= $log['tontine_id'] ?></a>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($log['ip_adresse'] ?? '-') ?></code></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px; display:flex; gap:8px; justify-content:center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>" class="btn btn-secondaire btn-petit <?= $i === $page ? 'actif' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>