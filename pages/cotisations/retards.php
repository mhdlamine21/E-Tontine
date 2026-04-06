<?php
//  COTISATIONS - RETARDS AVEC BOUTON AMENDE
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/amende.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine     = obtenirTontine($tontineId);
$cycleActuel = cycleEnCours($tontineId);
$nonPayes    = $cycleActuel ? membresNonPayes($tontineId, $cycleActuel['id']) : [];

// Déclencher marquage en retard si date limite dépassée
if ($cycleActuel && date('Y-m-d') > $cycleActuel['date_limite']) {
    marquerMembresEnRetard($tontineId, $cycleActuel['id']);
    $nonPayes = membresNonPayes($tontineId, $cycleActuel['id']);
}

// Récupérer les amendes déjà appliquées pour ce cycle
$amendesExistantes = [];
if ($cycleActuel) {
    $bd = connexionBD();
    $req = $bd->prepare('SELECT membre_id, id FROM amendes WHERE tontine_id = ? AND cycle_id = ? AND est_paye = 0');
    $req->execute([$tontineId, $cycleActuel['id']]);
    while ($row = $req->fetch()) {
        $amendesExistantes[$row['membre_id']] = $row['id'];
    }
}

$titrePage = 'Retards - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Membres en retard</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<?php if (!$cycleActuel): ?>
<div class="etat-vide"><p>Aucun cycle actif.</p></div>
<?php elseif (empty($nonPayes)): ?>
<div class="alerte alerte-succes">Tous les membres ont payé pour le cycle n°<?= $cycleActuel['numero_cycle'] ?>.</div>
<?php else: ?>
<div class="alerte alerte-alerte">
    <strong><?= count($nonPayes) ?> membre(s)</strong> n'ont pas encore payé pour le cycle n°<?= $cycleActuel['numero_cycle'] ?>
    (limite : <?= formaterDate($cycleActuel['date_limite']) ?>).
</div>

<section class="section-tableau">
    <div class="section-entete"><h2>Membres en attente</h2></div>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Téléphone</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nonPayes as $m): ?>
                <?php $aAmende = isset($amendesExistantes[$m['utilisateur_id']]); ?>
                <tr>
                    <td><?= htmlspecialchars($m['nom_complet']) ?></td>
                    <td><?= htmlspecialchars($m['telephone']) ?></td>
                    <td><?= badgeStatut($m['statut']) ?></td>
                    <td class="actions">
                        <?php if ($m['statut'] === STATUT_RETARD && !$aAmende): ?>
                        <a href="<?= APP_URL ?>/pages/cotisations/appliquer_amende.php?tontine=<?= $tontineId ?>&cycle=<?= $cycleActuel['id'] ?>&membre=<?= $m['utilisateur_id'] ?>"
                           class="btn btn-alerte btn-tres-petit">
                            ⚠️ Appliquer amende
                        </a>
                        <?php elseif ($aAmende): ?>
                        <span class="badge badge-info">Amende appliquée</span>
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
<?php endif; ?>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>