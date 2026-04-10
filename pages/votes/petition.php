<?php
//  VOTES - PÉTITION
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/vote.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$estAdmin  = estAdminDeTontine($utilisateurId, $tontineId);
$petitions = listerPetitions($tontineId);
$votes     = listerVotes($tontineId);

// Pétition active ?
$petitionActive = null;
foreach ($petitions as $p) {
    if (in_array($p['statut'], ['en_cours', 'vote_ouvert'])) {
        $petitionActive = $p;
        break;
    }
}

// Vérifier si déjà signé
$aDejaSigné = false;
if ($petitionActive) {
    $bd  = connexionBD();
    $req = $bd->prepare('SELECT id FROM signatures_petition WHERE petition_id = ? AND signataire_id = ?');
    $req->execute([$petitionActive['id'], $utilisateurId]);
    $aDejaSigné = (bool)$req->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $action = nettoyer($_POST['action'] ?? '');

    if ($action === 'creer' && !$estAdmin) {
        $motif = nettoyer($_POST['motif'] ?? '');
        if (strlen($motif) < 20) {
            $erreurs[] = 'Motif trop court (20 caractères minimum).';
        } else {
            $res = creerPetition($tontineId, $utilisateurId, $motif);
            redirigerAvecMessage(APP_URL . '/pages/votes/petition.php?tontine=' . $tontineId,
                $res['succes'] ? 'succes' : 'erreur', $res['message']);
        }
    } elseif ($action === 'signer' && $petitionActive) {
        $res = signerPetition((int)$petitionActive['id'], $utilisateurId);
        redirigerAvecMessage(APP_URL . '/pages/votes/petition.php?tontine=' . $tontineId,
            $res['succes'] ? 'succes' : 'erreur', $res['message']);
    }
}

$erreurs = $erreurs ?? [];
$titrePage = 'Pétitions - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Pétitions et votes</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<?php foreach ($erreurs as $err): ?>
<div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="banniere-guide-page">
    <div class="banniere-guide-page__icone">🗳️</div>
    <div class="banniere-guide-page__contenu">
        <h2 class="banniere-guide-page__titre">Guide de gouvernance démocratique</h2>
        <p class="banniere-guide-page__texte">Dans E-Tontine, le pouvoir n'appartient pas qu'au créateur : la communauté décide ensemble.</p>
        <ul class="banniere-guide-page__etapes">
            <li><strong>Lancer une pétition :</strong> Signalez un litige, demandez l'exclusion d'un membre défaillant ou un ajustement.</li>
            <li><strong>Quorum de 50% :</strong> Dès que la moitié des membres signent, le vote officiel est immédiatement déclenché.</li>
            <li><strong>Scrutin transparent :</strong> Chaque voix compte équitablement (Pour, Contre, Abstention).</li>
        </ul>
        <button type="button" class="banniere-guide-page__lien" onclick="ouvrirGuide('vote')">
            Comprendre le système de vote et de pétition &rarr;
        </button>
    </div>
</div>

<div class="grille-deux-colonnes">
<div>

<!-- Pétition active -->
<?php if ($petitionActive): ?>
<section class="section-tableau">
    <div class="section-entete">
        <h2>Pétition en cours</h2>
        <?= badgeStatut($petitionActive['statut'] === 'vote_ouvert' ? 'validee' : 'en_attente') ?>
    </div>
    <div class="carte-petition">
        <p><strong>Initiateur :</strong> <?= htmlspecialchars($petitionActive['nom_initiateur']) ?></p>
        <p><strong>Motif :</strong> <?= htmlspecialchars($petitionActive['motif']) ?></p>
        <div class="petition-progression">
            <div class="barre-progression-labels">
                <span><?= $petitionActive['nombre_signatures'] ?> / <?= $petitionActive['seuil_signatures'] ?> signatures</span>
                <span><?= round(($petitionActive['nombre_signatures'] / max(1, $petitionActive['seuil_signatures'])) * 100) ?>%</span>
            </div>
            <div class="barre-progression">
                <div class="barre-progression-remplie"
                     style="width:<?= min(100, round(($petitionActive['nombre_signatures'] / max(1, $petitionActive['seuil_signatures'])) * 100)) ?>%">
                </div>
            </div>
        </div>
        <?php if ($petitionActive['statut'] === 'vote_ouvert'): ?>
        <div class="alerte alerte-info" style="margin-top:12px">
            Le seuil est atteint. Un vote est en cours.
            <a href="<?= APP_URL ?>/pages/votes/voter.php?tontine=<?= $tontineId ?>">Voter maintenant →</a>
        </div>
        <?php elseif (!$aDejaSigné && !$estAdmin): ?>
        <form method="POST" action="" style="margin-top:12px">
            <?= champCsrf() ?>
            <input type="hidden" name="action" value="signer">
            <button type="submit" class="btn btn-principal">Signer la pétition</button>
        </form>
        <?php elseif ($aDejaSigné): ?>
        <div class="alerte alerte-succes" style="margin-top:12px">Vous avez signé cette pétition.</div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Créer une pétition (membres uniquement, pas admin) -->
<?php if (!$estAdmin && !$petitionActive): ?>
<section class="section-tableau">
    <div class="section-entete"><h2>Initier une pétition</h2></div>
    <div class="info-encadre">
        Si vous estimez que l'admin ne remplit pas correctement son rôle, vous pouvez initier une pétition.
        Il faut que <strong><?= round(SEUIL_PETITION_TIERS * 100) ?>%</strong> des membres signent
        pour qu'un vote soit déclenché. Si <strong><?= SEUIL_VOTE_POURCENT ?>%</strong> des membres
        votent OUI, l'admin sera remplacé par le membre le plus fiable.
    </div>
    <form method="POST" action="">
        <?= champCsrf() ?>
        <input type="hidden" name="action" value="creer">
        <div class="champ">
            <label>Motif de la pétition <span class="obligatoire">*</span></label>
            <textarea name="motif" rows="4" required minlength="20"
                      placeholder="Expliquez clairement pourquoi vous souhaitez changer l'administrateur..."></textarea>
        </div>
        <button type="submit" class="btn btn-alerte"
                onclick="return confirm('Êtes-vous sûr de vouloir initier une pétition ?')">
            Initier la pétition
        </button>
    </form>
</section>
<?php endif; ?>

</div>

<!-- Historique des votes -->
<div>
    <section class="section-tableau">
        <div class="section-entete"><h2>Historique des votes</h2></div>
        <?php if (empty($votes)): ?>
        <div class="etat-vide"><p>Aucun vote pour le moment.</p></div>
        <?php else: ?>
        <?php foreach ($votes as $v): ?>
        <div class="carte-vote">
            <div class="carte-vote-entete">
                <span><?= formaterDate($v['date_ouverture']) ?></span>
                <span class="badge <?= $v['statut'] === VOTE_OUVERT ? 'badge-info' : 'badge-secondaire' ?>">
                    <?= $v['statut'] === VOTE_OUVERT ? 'En cours' : 'Clôturé' ?>
                </span>
            </div>
            <div class="vote-resultats">
                <span class="vote-oui">OUI : <?= $v['nombre_oui'] ?></span>
                <span class="vote-non">NON : <?= $v['nombre_non'] ?></span>
            </div>
            <?php if ($v['resultat']): ?>
            <div class="<?= $v['resultat'] === 'admin_change' ? 'alerte alerte-alerte' : 'alerte alerte-succes' ?>" style="margin:8px 0 0">
                <?= $v['resultat'] === 'admin_change' ? 'Admin remplacé' : 'Admin maintenu' ?>
            </div>
            <?php endif; ?>
            <?php if ($v['statut'] === VOTE_OUVERT): ?>
            <a href="<?= APP_URL ?>/pages/votes/voter.php?tontine=<?= $tontineId ?>&vote=<?= $v['id'] ?>"
               class="btn btn-principal btn-petit" style="margin-top:8px">
                Voter
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
