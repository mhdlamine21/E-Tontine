<?php
// Liste des membres d'une tontine et gestion des invitations.
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/invitation.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$estAdmin = estAdminDeTontine($utilisateurId, $tontineId);
$membresTous = listerMembres($tontineId);
$lienInvitation = '';

if ($estAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_invitation']) && verifierCsrf()) {
    $res = creerInvitation($tontineId, $utilisateurId);
    $lienInvitation = $res['lien'];
}

// Pagination (utile dès qu'une tontine a beaucoup de membres)
$pagePagination = obtenirGetInt('page', 1);
$pagination = paginer($membresTous, $pagePagination);
$membres    = $pagination['items'];

$titrePage = 'Membres - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Membres de "<?= htmlspecialchars($tontine['nom']) ?>"</h1>
        <p class="texte-secondaire"><?= count($membresTous) ?> / <?= $tontine['nombre_max_membres'] ?> membres</p>
    </div>
    <?php if ($estAdmin): ?>
    <div class="actions-groupe">
        <a href="<?= APP_URL ?>/pages/membres/ajouter.php?tontine=<?= $tontineId ?>" class="btn btn-principal">+ Ajouter par email</a>
    </div>
    <?php endif; ?>
</div>

<!-- Lien d'invitation généré -->
<?php if (!empty($lienInvitation)): ?>
<div class="alerte alerte-succes">
    <strong>Lien d'invitation généré :</strong>
    <div class="lien-copiable">
        <input type="text" id="lienInvit" value="<?= htmlspecialchars($lienInvitation) ?>" readonly>
        <button onclick="copierLien()" class="btn btn-secondaire btn-petit">Copier</button>
    </div>
    <small>Ce lien expire dans <?= INVITATION_DUREE_JOURS ?> jours.</small>
</div>
<?php endif; ?>

<?php if ($estAdmin): ?>
<section class="section-tableau">
    <div class="section-entete"><h2>Inviter par lien</h2></div>
    <p class="texte-secondaire">Partagez ce lien par WhatsApp ou SMS pour inviter quelqu'un à rejoindre la tontine.</p>
    <form method="POST" action="">
        <?= champCsrf() ?>
        <input type="hidden" name="action_invitation" value="1">
        <button type="submit" class="btn btn-secondaire">Générer un lien d'invitation</button>
    </form>
</section>
<?php endif; ?>

<section class="section-tableau">
    <div class="section-entete"><h2>Liste des membres</h2></div>

    <?php if (empty($membresTous)): ?>
    <div class="etat-vide"><p>Aucun membre dans cette tontine.</p></div>
    <?php else: ?>

    <?php if ($estAdmin): ?>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Rôle</th>
                    <th>Tour</th>
                    <th>Score fiabilité</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($membres as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['nom_complet']) ?></strong></td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td><?= htmlspecialchars($m['telephone']) ?></td>
                    <td><?= $m['role'] === ROLE_ADMIN ? '<span class="badge badge-info">Admin</span>' : '<span class="badge badge-secondaire">Membre</span>' ?></td>
                    <td><?= $m['ordre_tour'] ?></td>
                    <td>
                        <?php
                        $score = (float)($m['score_fiabilite'] ?? 100);
                        if ($score >= 80) {
                            echo '<span class="badge badge-succes">' . number_format($score, 1) . '%</span>';
                        } elseif ($score >= 50) {
                            echo '<span class="badge badge-alerte">' . number_format($score, 1) . '%</span>';
                        } else {
                            echo '<span class="badge badge-danger">' . number_format($score, 1) . '%</span>';
                        }
                        ?>
                    </td>
                    <td><?= badgeStatut($m['statut']) ?></td>
                    <td>
                        <?php if ($estAdmin && $m['role'] !== ROLE_ADMIN): ?>
                        <form method="POST" action="<?= APP_URL ?>/pages/membres/exclure.php?tontine=<?= $tontineId ?>&membre=<?= (int)$m['utilisateur_id'] ?>" style="display:inline">
                            <?= champCsrf() ?>
                            <button type="submit" class="btn btn-danger btn-tres-petit" onclick="return confirm('Confirmer l\'exclusion ?')">Exclure</button>
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
    <?php else: ?>
    <div class="liste-membres">
        <?php foreach ($membres as $membre): ?>
        <?php renderCarteMembre($membre, $estAdmin, $tontineId); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?= rendreLiensPagination($pagination['page'], $pagination['total_pages'], APP_URL . '/pages/membres/liste.php?tontine=' . $tontineId) ?>

    <?php endif; ?>
</section>

<script>
function copierLien() {
    const el = document.getElementById('lienInvit');
    const valeur = el ? el.value : '';
    if (!valeur) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(valeur)
            .then(function() { alert('Lien copié !'); })
            .catch(function() { alert('Copie impossible. Veuillez copier manuellement.'); });
        return;
    }

    if (el) el.select();
    alert('Sélectionné. Copiez manuellement (Ctrl+C).');
}
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
