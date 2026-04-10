<?php
//  VOTES - RÉSULTAT D'UN VOTE CLÔTURÉ
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/vote.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId = obtenirGetInt('tontine');
$voteId    = obtenirGetInt('vote');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$bd  = connexionBD();
$req = $bd->prepare('SELECT v.*, p.motif AS motif_petition FROM votes v JOIN petitions p ON p.id = v.petition_id WHERE v.id = ? AND v.tontine_id = ?');
$req->execute([$voteId, $tontineId]);
$vote = $req->fetch();

if (!$vote) {
    redirigerAvecMessage(APP_URL . '/pages/votes/petition.php?tontine=' . $tontineId, 'erreur', 'Vote introuvable.');
}

// Détail des votes membres
$req = $bd->prepare('SELECT vm.choix, u.nom_complet FROM votes_membres vm JOIN utilisateurs u ON u.id = vm.votant_id WHERE vm.vote_id = ? ORDER BY vm.date_vote ASC');
$req->execute([$voteId]);
$detailVotes = $req->fetchAll();

$totalVotes  = (int)$vote['nombre_oui'] + (int)$vote['nombre_non'];
$pctOui      = $totalVotes > 0 ? round(($vote['nombre_oui'] / $totalVotes) * 100) : 0;

// Nouvel admin si changement
$nouvelAdmin = null;
if ($vote['nouvel_admin_id']) {
    $req = $bd->prepare('SELECT nom_complet FROM utilisateurs WHERE id = ?');
    $req->execute([$vote['nouvel_admin_id']]);
    $nouvelAdmin = $req->fetchColumn();
}

$titrePage = 'Résultat du vote - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/votes/petition.php?tontine=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Résultat du vote</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<!-- Résultat final -->
<?php if ($vote['resultat']): ?>
<div class="alerte <?= $vote['resultat'] === 'admin_change' ? 'alerte-alerte' : 'alerte-succes' ?>" style="font-size:15px;padding:16px 20px">
    <?php if ($vote['resultat'] === 'admin_change'): ?>
        <strong>L'administrateur a été remplacé.</strong>
        <?php if ($nouvelAdmin): ?>
        Le nouveau administrateur est <strong><?= htmlspecialchars($nouvelAdmin) ?></strong>.
        <?php endif; ?>
    <?php else: ?>
        <strong>L'administrateur a été maintenu.</strong>
        Le vote n'a pas atteint <?= SEUIL_VOTE_POURCENT ?>% de OUI.
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grille-deux-colonnes">

    <!-- Statistiques du vote -->
    <section class="section-tableau">
        <div class="section-entete"><h2>Statistiques</h2></div>

        <div class="grille-stats" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
            <div class="carte-stat carte-stat-succes">
                <div class="carte-stat-nombre"><?= $vote['nombre_oui'] ?></div>
                <div class="carte-stat-label">Votes OUI</div>
            </div>
            <div class="carte-stat" style="border-color:var(--rouge-pale);background:var(--rouge-pale)">
                <div class="carte-stat-nombre" style="color:var(--rouge)"><?= $vote['nombre_non'] ?></div>
                <div class="carte-stat-label">Votes NON</div>
            </div>
        </div>

        <div class="barre-progression-conteneur">
            <div class="barre-progression-labels">
                <span>OUI : <?= $pctOui ?>%</span>
                <span>Seuil requis : <?= SEUIL_VOTE_POURCENT ?>%</span>
            </div>
            <div class="barre-progression">
                <div class="barre-progression-remplie barre-succes" style="width:<?= $pctOui ?>%"></div>
            </div>
        </div>

        <div class="info-encadre" style="margin-top:14px">
            <strong>Motif de la pétition :</strong><br>
            <?= htmlspecialchars($vote['motif_petition']) ?>
        </div>

        <div class="texte-secondaire" style="font-size:12px;margin-top:8px">
            Ouvert le <?= formaterDate($vote['date_ouverture']) ?>
            <?php if ($vote['date_cloture']): ?>
            - Clôturé le <?= formaterDate($vote['date_cloture']) ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Détail des votes -->
    <section class="section-tableau">
        <div class="section-entete">
            <h2>Détail des votes</h2>
            <span class="texte-secondaire"><?= $totalVotes ?> votant(s)</span>
        </div>

        <?php if (empty($detailVotes)): ?>
        <div class="etat-vide"><p>Aucun vote enregistré.</p></div>
        <?php else: ?>
        <div class="table-conteneur">
            <table class="tableau-donnees">
                <thead>
                    <tr><th>Membre</th><th>Vote</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($detailVotes as $dv): ?>
                    <tr>
                        <td><?= htmlspecialchars($dv['nom_complet']) ?></td>
                        <td>
                            <?php if ($dv['choix'] === 'oui'): ?>
                            <span class="badge badge-succes">OUI</span>
                            <?php else: ?>
                            <span class="badge badge-danger">NON</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
