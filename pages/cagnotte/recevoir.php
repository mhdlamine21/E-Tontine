<?php
//  CAGNOTTE - RECEVOIR (choix : maintenant ou attendre)
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/cagnotte.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$cycleId       = obtenirGetInt('cycle');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}
if (!tontineAccepteOperations($tontine)) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'La tontine n’est pas en cours ; retrait indisponible.');
}

$cycle = obtenirCycleSimple($cycleId);
if (!$cycle || (int)$cycle['tontine_id'] !== $tontineId) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Cycle introuvable.');
}

// Seul le bénéficiaire du cycle peut accéder à cette page
if ((int)$cycle['beneficiaire_id'] !== $utilisateurId) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Ce n\'est pas votre tour de recevoir.');
}

$etatDistrib = etatDistribution($tontineId, $cycleId);
$soldes      = resumeSoldesCycle($cycleId, $etatDistrib);
$collectee   = $soldes['collectee_totale'];
$theorique   = (float)$cycle['cagnotte_theorique'];
$pct         = $theorique > 0 ? min(100, round(($collectee / $theorique) * 100)) : 0;
$dispoRetrait = $soldes['disponible_prochain_retrait'];

// Vérifier membre en retard
$bd  = connexionBD();
$req = $bd->prepare('SELECT statut FROM membres_tontine WHERE tontine_id = ? AND utilisateur_id = ?');
$req->execute([$tontineId, $utilisateurId]);
$membreStatut = $req->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $choix = nettoyer($_POST['choix'] ?? '');
    if (!in_array($choix, ['recevoir_maintenant', 'attendre'])) {
        redirigerAvecMessage(APP_URL . '/pages/cagnotte/recevoir.php?tontine=' . $tontineId . '&cycle=' . $cycleId, 'erreur', 'Choix invalide.');
    }
    $res = choisirModeReception($tontineId, $cycleId, $utilisateurId, $choix);
    redirigerAvecMessage(
        APP_URL . '/pages/cagnotte/etat.php?tontine=' . $tontineId,
        $res['succes'] ? 'succes' : 'erreur',
        $res['message']
    );
}

$titrePage = 'Recevoir la cagnotte';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Recevoir la cagnotte</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?> - Cycle n°<?= $cycle['numero_cycle'] ?></p>
    </div>
</div>

<?php if ($membreStatut === STATUT_RETARD): ?>
<div class="alerte alerte-danger">
    <strong>Vous êtes en retard de cotisation.</strong>
    Vous devez d'abord payer votre cotisation avant de pouvoir recevoir la cagnotte.
    <a href="<?= APP_URL ?>/pages/cotisations/payer.php?tontine=<?= $tontineId ?>&cycle=<?= $cycleId ?>" class="btn btn-danger btn-petit" style="margin-left:12px">
        Payer maintenant
    </a>
</div>
<?php elseif ($etatDistrib && $etatDistrib['statut'] === DISTRIB_COMPLET): ?>
<div class="alerte alerte-succes">
    Vous avez déjà reçu l'intégralité de votre cagnotte pour ce cycle (<?= formaterMontant((float)$etatDistrib['montant_recu']) ?>).
</div>
<?php else: ?>

<!-- État de la cagnotte -->
<section class="section-tableau">
    <div class="section-entete"><h2>État actuel de la cagnotte</h2></div>

    <div class="grille-stats" style="margin-bottom:16px">
        <div class="carte-stat carte-stat-succes">
            <div class="carte-stat-nombre"><?= formaterMontant($dispoRetrait) ?></div>
            <div class="carte-stat-label">Retrait possible maintenant</div>
        </div>
        <div class="carte-stat">
            <div class="carte-stat-nombre"><?= formaterMontant($collectee) ?></div>
            <div class="carte-stat-label">Total cotisé (cycle)</div>
        </div>
        <div class="carte-stat">
            <div class="carte-stat-nombre"><?= formaterMontant($theorique) ?></div>
            <div class="carte-stat-label">Objectif du cycle</div>
        </div>
    </div>
    <?php if ($soldes['deja_verse_beneficiaire'] > 0.01): ?>
    <p class="texte-secondaire" style="margin:-8px 0 12px;font-size:13px">
        Déjà reçu sur ce cycle : <strong><?= formaterMontant($soldes['deja_verse_beneficiaire']) ?></strong>
        · encore dû jusqu’au plafond : <strong><?= formaterMontant($soldes['reste_du_beneficiaire']) ?></strong>
        · cotisations non encore attribuées à votre retrait : <strong><?= formaterMontant($soldes['non_encore_verse']) ?></strong>
    </p>
    <?php endif; ?>

    <div class="barre-progression-conteneur">
        <div class="barre-progression-labels">
            <span><?= $pct ?>% collecté</span>
            <span>Limite : <?= formaterDate($cycle['date_limite']) ?></span>
        </div>
        <div class="barre-progression">
            <div class="barre-progression-remplie" style="width:<?= $pct ?>%"></div>
        </div>
    </div>
</section>

<!-- Choix du mode de réception -->
<section class="section-tableau">
    <div class="section-entete"><h2>Comment souhaitez-vous recevoir votre cagnotte ?</h2></div>

    <form method="POST" action="">
        <?= champCsrf() ?>

        <div class="options-reception">

            <!-- Option 1 : Recevoir maintenant -->
            <label class="option-reception <?= $dispoRetrait <= 0 ? 'option-desactivee' : '' ?>"
                   for="recevoir_maintenant">
                <input type="radio" id="recevoir_maintenant" name="choix"
                       value="recevoir_maintenant"
                       <?= $dispoRetrait <= 0 ? 'disabled' : 'checked' ?>>
                <div class="option-reception-corps">
                    <div class="option-reception-icone icone-succes">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <polyline points="20 12 20 22 4 22 4 12"/>
                            <rect x="2" y="7" width="20" height="5"/>
                            <line x1="12" y1="22" x2="12" y2="7"/>
                            <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/>
                            <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="option-reception-titre">Recevoir maintenant</div>
                        <div class="option-reception-detail">
                            Vous recevez immédiatement jusqu’à
                            <strong><?= formaterMontant($dispoRetrait) ?></strong>
                            (nouvelles cotisations peuvent s’ajouter ensuite).
                            <?php if ($collectee < $theorique): ?>
                            <br><small class="texte-secondaire">
                                Il manque encore <?= formaterMontant(max(0, $theorique - $collectee)) ?> de cotisations pour le montant complet du cycle.
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </label>

            <!-- Option 2 : Attendre -->
            <label class="option-reception" for="attendre">
                <input type="radio" id="attendre" name="choix" value="attendre"
                       <?= $dispoRetrait <= 0 ? 'checked' : '' ?>>
                <div class="option-reception-corps">
                    <div class="option-reception-icone icone-info">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div>
                        <div class="option-reception-titre">Attendre le montant complet</div>
                        <div class="option-reception-detail">
                            Vous attendez que tous les membres paient.
                            Dès que la cagnotte atteint
                            <strong><?= formaterMontant($theorique) ?></strong>,
                            vous la recevez automatiquement.
                        </div>
                    </div>
                </div>
            </label>

        </div>

        <?php if ($etatDistrib && $etatDistrib['choix_beneficiaire'] === 'attendre'): ?>
        <div class="alerte alerte-info" style="margin-top:16px">
            Vous avez choisi d'attendre. La cagnotte vous sera versée automatiquement dès qu'elle sera complète.
            Vous pouvez changer d'avis et recevoir maintenant ci-dessous.
        </div>
        <?php endif; ?>

        <div class="actions-formulaire" style="margin-top:24px">
            <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Confirmer mon choix</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
