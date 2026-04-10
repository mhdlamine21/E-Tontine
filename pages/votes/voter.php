<?php
//  VOTES - VOTER OUI / NON SUR LE CHANGEMENT D'ADMINISTRATEUR
//  (corrigé pour correspondre au schéma réel : votes.nombre_oui /
//   nombre_non, votes_membres.choix ENUM('oui','non'))
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/vote.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$voteId        = obtenirGetInt('vote');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

// Trouver le vote ouvert si pas spécifié
if (!$voteId) {
    $votes = listerVotes($tontineId);
    foreach ($votes as $v) {
        if ($v['statut'] === VOTE_OUVERT) { $voteId = $v['id']; break; }
    }
}

$bd = connexionBD();

$req = $bd->prepare('SELECT v.*, p.motif AS motif_petition FROM votes v JOIN petitions p ON p.id = v.petition_id WHERE v.id = ? AND v.tontine_id = ?');
$req->execute([$voteId, $tontineId]);
$vote = $req->fetch();

$aVote   = false;
$monVote = null;
if ($vote) {
    $aVote = aDejaVote($voteId, $utilisateurId);
    if ($aVote) {
        $monVote = getVoteMembre($voteId, $utilisateurId);
    }
}

$candidats = [];
if ($vote) {
    $candidats = getCandidatsEtVotes($voteId);
}

$estCandidat = false;
if ($vote) {
    $req = $bd->prepare('SELECT id FROM candidatures_admin WHERE vote_id = ? AND candidat_id = ?');
    $req->execute([$voteId, $utilisateurId]);
    $estCandidat = (bool)$req->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $action = nettoyer($_POST['action'] ?? '');

    if ($action === 'voter' && $vote && $vote['statut'] === VOTE_OUVERT && !$aVote) {
        $choix = nettoyer($_POST['choix'] ?? '');
        $res = voter((int)$voteId, $utilisateurId, $choix);
        redirigerAvecMessage(APP_URL . '/pages/votes/voter.php?tontine=' . $tontineId . '&vote=' . $voteId,
            $res['succes'] ? 'succes' : 'erreur', $res['message']);
    }

    if ($action === 'candidater' && $vote && $vote['statut'] === VOTE_OUVERT && !estAdminDeTontine($utilisateurId, $tontineId)) {
        $messageCand = nettoyer($_POST['message_candidature'] ?? '');
        $res = sePorterCandidat((int)$voteId, $utilisateurId, $messageCand);
        redirigerAvecMessage(APP_URL . '/pages/votes/voter.php?tontine=' . $tontineId . '&vote=' . $voteId,
            $res['succes'] ? 'succes' : 'erreur', $res['message']);
    }
}

$totalVotes = $vote ? (int)$vote['nombre_oui'] + (int)$vote['nombre_non'] : 0;
$pctOui     = $totalVotes > 0 ? round(((int)$vote['nombre_oui'] / $totalVotes) * 100) : 0;

$titrePage = 'Vote - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/votes/petition.php?tontine=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Vote - Faut-il changer d’administrateur ?</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<?php if (!$vote): ?>
<div class="etat-vide"><p>Aucun vote ouvert pour cette tontine.</p></div>
<?php else: ?>

<section class="section-tableau">
    <div class="section-entete">
        <h2>Résultats actuels</h2>
        <span class="badge <?= $vote['statut'] === VOTE_OUVERT ? 'badge-info' : 'badge-secondaire' ?>">
            <?= $vote['statut'] === VOTE_OUVERT ? 'Vote ouvert' : 'Clôturé' ?>
        </span>
    </div>

    <div class="info-encadre" style="margin-bottom:16px">
        <strong>Motif de la pétition :</strong> <?= htmlspecialchars($vote['motif_petition']) ?>
    </div>

    <div class="grille-stats" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
        <div class="carte-stat carte-stat-succes">
            <div class="carte-stat-nombre"><?= (int)$vote['nombre_oui'] ?></div>
            <div class="carte-stat-label">Votes OUI</div>
        </div>
        <div class="carte-stat" style="border-color:var(--rouge-pale);background:var(--rouge-pale)">
            <div class="carte-stat-nombre" style="color:var(--rouge)"><?= (int)$vote['nombre_non'] ?></div>
            <div class="carte-stat-label">Votes NON</div>
        </div>
    </div>

    <div class="barre-progression-conteneur">
        <div class="barre-progression-labels">
            <span>OUI : <?= $pctOui ?>%</span>
            <span>Seuil requis : <?= (float)$vote['seuil_validation'] ?>%</span>
        </div>
        <div class="barre-progression">
            <div class="barre-progression-remplie barre-succes" style="width:<?= $pctOui ?>%"></div>
        </div>
    </div>

    <div class="texte-secondaire" style="margin-top:8px">
        Total des votes exprimés : <?= $totalVotes ?>
    </div>
</section>

<!-- Candidats déclarés -->
<section class="section-tableau">
    <div class="section-entete">
        <h2>Candidats déclarés</h2>
        <span class="texte-secondaire">Si le OUI l’emporte, le candidat le plus fiable devient admin</span>
    </div>

    <?php if (empty($candidats)): ?>
    <div class="etat-vide"><p>Aucun candidat pour le moment.</p></div>
    <?php else: ?>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead><tr><th>Candidat</th><th>Score fiabilité</th><th>Message</th></tr></thead>
            <tbody>
                <?php foreach ($candidats as $c): ?>
                <tr class="<?= $c['candidat_id'] == $utilisateurId ? 'ligne-surbrillance' : '' ?>">
                    <td><strong><?= htmlspecialchars($c['nom_complet']) ?></strong> <?= $c['candidat_id'] == $utilisateurId ? '(Vous)' : '' ?></td>
                    <td>
                        <?php
                        $scoreClass = 'score-moyen';
                        if ($c['score_fiabilite'] >= 80) $scoreClass = 'score-haut';
                        if ($c['score_fiabilite'] < 50) $scoreClass = 'score-bas';
                        ?>
                        <span class="<?= $scoreClass ?>"><?= number_format((float)$c['score_fiabilite'], 1) ?>%</span>
                    </td>
                    <td><?= htmlspecialchars($c['message'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<!-- Formulaire de vote OUI/NON -->
<?php if ($vote['statut'] === VOTE_OUVERT && !$aVote): ?>
<section class="section-tableau">
    <div class="section-entete"><h2>Votre vote</h2></div>
    <form method="POST" action="">
        <?= champCsrf() ?>
        <input type="hidden" name="action" value="voter">

        <div class="champ">
            <label>Faut-il remplacer l’administrateur actuel ?</label>
            <div class="choix-inline" style="display:flex; gap:20px; margin-top:8px">
                <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:1px solid var(--gris-bordure); border-radius:10px; cursor:pointer">
                    <input type="radio" name="choix" value="oui" required> OUI, changer l’administrateur
                </label>
                <label style="display:flex; align-items:center; gap:8px; padding:10px 16px; border:1px solid var(--gris-bordure); border-radius:10px; cursor:pointer">
                    <input type="radio" name="choix" value="non" required> NON, le maintenir
                </label>
            </div>
        </div>

        <div class="actions-formulaire" style="margin-top:16px">
            <button type="submit" class="btn btn-principal" onclick="return confirm('Votre vote est définitif. Confirmer ?')">
                Confirmer mon vote
            </button>
        </div>
    </form>
</section>
<?php elseif ($aVote): ?>
<div class="alerte alerte-succes">
    Vous avez voté <strong><?= strtoupper($monVote['choix'] ?? '') ?></strong>. Merci pour votre participation.
</div>
<?php endif; ?>

<!-- Se porter candidat -->
<?php if ($vote['statut'] === VOTE_OUVERT && !estAdminDeTontine($utilisateurId, $tontineId) && !$estCandidat): ?>
<section class="section-tableau">
    <div class="section-entete"><h2>Se porter candidat</h2></div>
    <p class="texte-secondaire">Vous pouvez vous présenter pour devenir administrateur si le OUI l’emporte.</p>
    <form method="POST" action="">
        <?= champCsrf() ?>
        <input type="hidden" name="action" value="candidater">
        <div class="champ">
            <label>Votre message de candidature <span class="texte-secondaire">(optionnel)</span></label>
            <input type="text" name="message_candidature" placeholder="Pourquoi souhaitez-vous devenir administrateur ?">
        </div>
        <button type="submit" class="btn btn-secondaire">Me porter candidat</button>
    </form>
</section>
<?php elseif ($estCandidat): ?>
<div class="alerte alerte-succes">Vous êtes candidat. Vous serez prioritaire si le OUI l’emporte.</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
