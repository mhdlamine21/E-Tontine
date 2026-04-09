<?php
//  URGENCES - VALIDER / REFUSER (ADMIN)
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/urgence.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine  = obtenirTontine($tontineId);
$urgences = listerUrgences($tontineId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $action    = nettoyer($_POST['action']     ?? '');
    $urgenceId = (int)($_POST['urgence_id']    ?? 0);
    $mode      = nettoyer($_POST['mode']       ?? 'immediat');
    $commentaire = nettoyer($_POST['commentaire'] ?? '');

    if ($action === 'valider') {
        $res = validerUrgence($urgenceId, $utilisateurId, $mode, $commentaire);
    } elseif ($action === 'refuser') {
        if (empty($commentaire)) {
            $res = ['succes' => false, 'message' => 'Un commentaire est requis pour refuser une demande.'];
        } else {
            $res = refuserUrgence($urgenceId, $utilisateurId, $commentaire);
        }
    } else {
        $res = ['succes' => false, 'message' => 'Action inconnue.'];
    }

    redirigerAvecMessage(
        APP_URL . '/pages/urgences/valider.php?tontine=' . $tontineId,
        $res['succes'] ? 'succes' : 'erreur',
        $res['message']
    );
}

// Séparer urgences en attente et traitées
$enAttente = array_filter($urgences, fn($u) => $u['statut'] === URGENCE_EN_ATTENTE);
$traitees  = array_filter($urgences, fn($u) => $u['statut'] !== URGENCE_EN_ATTENTE);

$titrePage = 'Urgences - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Gestion des urgences</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<!-- Urgences en attente -->
<section class="section-tableau">
    <div class="section-entete">
        <h2>Demandes en attente</h2>
        <?php if (!empty($enAttente)): ?>
        <span class="badge badge-alerte"><?= count($enAttente) ?></span>
        <?php endif; ?>
    </div>

    <?php if (empty($enAttente)): ?>
    <div class="etat-vide"><p>Aucune demande en attente.</p></div>
    <?php else: ?>
    <?php foreach ($enAttente as $u): ?>
    <div class="carte-urgence carte-urgence-admin">
        <div class="carte-urgence-entete">
            <div>
                <strong><?= htmlspecialchars($u['nom_demandeur']) ?></strong>
                <span class="texte-secondaire" style="margin-left:8px"><?= formaterDateHeure($u['date_demande']) ?></span>
            </div>
            <?= badgeStatut($u['statut']) ?>
        </div>
        <div class="carte-urgence-motif"><?= htmlspecialchars($u['motif']) ?></div>

        <!-- Formulaire de décision -->
        <form method="POST" action="" class="forme-decision">
            <?= champCsrf() ?>
            <input type="hidden" name="urgence_id" value="<?= $u['id'] ?>">

            <div class="decision-mode">
                <label>Mode si validation :</label>
                <div class="choix-inline">
                    <label>
                        <input type="radio" name="mode" value="immediat" checked>
                        Paiement immédiat (depuis la cagnotte)
                    </label>
                    <label>
                        <input type="radio" name="mode" value="prochain_cycle">
                        Prochain cycle (passe en tête de liste)
                    </label>
                </div>
            </div>

            <div class="champ" style="margin-top:10px">
                <label>Commentaire <span class="texte-secondaire">(requis pour refus)</span></label>
                <input type="text" name="commentaire"
                       placeholder="Votre commentaire à destination du membre...">
            </div>

            <div class="actions-decision">
                <button type="submit" name="action" value="valider"
                        class="btn btn-succes"
                        onclick="return confirm('Valider cette demande d\'urgence ?')">
                    Valider
                </button>
                <button type="submit" name="action" value="refuser"
                        class="btn btn-danger"
                        onclick="return confirm('Refuser cette demande ? Un commentaire est requis.')">
                    Refuser
                </button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- Historique des urgences traitées -->
<?php if (!empty($traitees)): ?>
<section class="section-tableau">
    <div class="section-entete"><h2>Demandes traitées</h2></div>
    <?php foreach ($traitees as $u): ?>
    <div class="carte-urgence">
        <div class="carte-urgence-entete">
            <div>
                <strong><?= htmlspecialchars($u['nom_demandeur']) ?></strong>
                <span class="texte-secondaire" style="margin-left:8px"><?= formaterDate($u['date_demande']) ?></span>
            </div>
            <?= badgeStatut($u['statut']) ?>
        </div>
        <p class="carte-urgence-motif"><?= htmlspecialchars(tronquer($u['motif'], 100)) ?></p>
        <?php if ($u['commentaire_admin']): ?>
        <div class="carte-urgence-reponse">
            <strong>Votre réponse :</strong> <?= htmlspecialchars($u['commentaire_admin']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
