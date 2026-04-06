<?php
//  COTISATIONS - PAYER POUR UN AUTRE MEMBRE
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/paiement_pour_autrui.php';
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
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Les cotisations ne sont pas ouvertes pour cette tontine.');
}

exigerAdminTontine($utilisateurId, $tontineId);

if ($cycleId <= 0) {
    $autoCy = cycleEnCours($tontineId);
    if ($autoCy) {
        header('Location: ' . APP_URL . '/pages/cotisations/payer_pour_autrui.php?tontine=' . $tontineId . '&cycle=' . (int)$autoCy['id']);
        exit;
    }
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Aucun cycle en cours. Démarrez un cycle depuis la vue d’ensemble.');
}

$cycle = obtenirCycleSimple($cycleId);
if (!$cycle || (int)$cycle['tontine_id'] !== $tontineId || ($cycle['statut'] ?? '') !== CYCLE_EN_COURS) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Cycle invalide ou déjà clos.');
}

$membres = listerMembres($tontineId);

// Filtrer : uniquement ceux qui n'ont pas encore payé et qui ne sont pas soi-même
$membresNonPayesListe = [];
foreach ($membres as $m) {
    if ((int)$m['utilisateur_id'] !== $utilisateurId
        && !aDejaPayePourCycle($tontineId, $cycleId, (int)$m['utilisateur_id'])
        && $m['statut'] !== STATUT_EXCLU) {
        $membresNonPayesListe[] = $m;
    }
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $beneficiaireId = (int)($_POST['beneficiaire_id'] ?? 0);
    $mode           = nettoyer($_POST['mode']      ?? '');
    $telephone      = nettoyer($_POST['telephone'] ?? '');
    $note           = nettoyer($_POST['note']       ?? '');

    if (!$beneficiaireId) $erreurs[] = 'Sélectionnez un membre.';
    if (!in_array($mode, [MODE_WAVE, MODE_ORANGE_MONEY, MODE_ESPECES])) $erreurs[] = 'Mode invalide.';
    if ($mode !== MODE_ESPECES && trim($telephone) === '') {
        $erreurs[] = 'Numéro de téléphone requis pour Wave ou Orange Money.';
    }

    if (empty($erreurs)) {
        $res = payerPourAutrui($tontineId, $cycleId, $utilisateurId, $beneficiaireId,
                               (float)$tontine['montant_cotisation'], $mode, $telephone, $note);
        if ($res['succes']) {
            redirigerAvecMessage(
                APP_URL . '/pages/cotisations/historique.php?tontine=' . $tontineId,
                'succes',
                $res['message'] . ' Référence : ' . $res['reference']
            );
        } else {
            $erreurs[] = $res['message'];
        }
    }
}

$titrePage = 'Payer pour un autre membre';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Payer pour un autre membre</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?> - Cycle n°<?= (int)$cycle['numero_cycle'] ?> · limite <?= formaterDate($cycle['date_limite']) ?></p>
    </div>
</div>

<div class="formulaire-conteneur formulaire-elargi">

    <div class="info-encadre">
        <strong>Information :</strong> Vous payez la cotisation d'un autre membre. L'application enregistre uniquement la trace du paiement.
        Si vous souhaitez être remboursé, arrangez-vous directement avec le membre concerné. L'historique servira de preuve.
    </div>

    <section class="section-tableau">
        <div class="section-entete"><h2>Qui a déjà cotisé ce cycle&nbsp;?</h2></div>
        <ul class="liste-simple">
            <?php foreach ($membres as $m): ?>
            <?php if (($m['statut'] ?? '') === STATUT_EXCLU) continue; ?>
            <?php
            $uidM = (int)$m['utilisateur_id'];
            $aPaye = aDejaPayePourCycle($tontineId, $cycleId, $uidM);
            ?>
            <li>
                <span><?= htmlspecialchars($m['nom_complet']) ?><?= $uidM === $utilisateurId ? ' (vous)' : '' ?></span>
                <span class="badge <?= $aPaye ? 'badge-succes' : 'badge-alerte' ?>"><?= $aPaye ? 'Payé' : 'Non payé' ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if (empty($membresNonPayesListe)): ?>
    <div class="alerte alerte-info">Tous les membres ont déjà payé pour ce cycle.</div>
    <?php else: ?>

    <?php foreach ($erreurs as $err): ?>
    <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="">
        <?= champCsrf() ?>

        <div class="champ">
            <label for="beneficiaire_id">Membre à aider <span class="obligatoire">*</span></label>
            <select id="beneficiaire_id" name="beneficiaire_id" required>
                <option value="">-- Choisir un membre --</option>
                <?php foreach ($membresNonPayesListe as $m): ?>
                <option value="<?= $m['utilisateur_id'] ?>"><?= htmlspecialchars($m['nom_complet']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="recap-paiement">
            <div class="recap-ligne recap-total">
                <span>Montant</span>
                <strong class="montant-principal"><?= formaterMontant((float)$tontine['montant_cotisation']) ?></strong>
            </div>
        </div>

        <div class="champ">
            <label>Mode de paiement</label>
            <div class="choix-paiement">
                <label class="option-paiement" for="wave2">
                    <input type="radio" id="wave2" name="mode" value="wave" checked onchange="toggleTelAutrui(this.value)">
                    <div class="option-paiement-corps">
                        <div class="option-paiement-logo logo-wave">W</div>
                        <span>Wave</span>
                    </div>
                </label>
                <label class="option-paiement" for="orange2">
                    <input type="radio" id="orange2" name="mode" value="orange_money" onchange="toggleTelAutrui(this.value)">
                    <div class="option-paiement-corps">
                        <div class="option-paiement-logo logo-orange">O</div>
                        <span>Orange Money</span>
                    </div>
                </label>
                <label class="option-paiement" for="especes2">
                    <input type="radio" id="especes2" name="mode" value="especes" onchange="toggleTelAutrui(this.value)">
                    <div class="option-paiement-corps">
                        <div class="option-paiement-logo logo-especes">E</div>
                        <span>Espèces</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="champ" id="champTelAutrui">
            <label for="telephone">Numéro de téléphone</label>
            <input type="tel" id="telephone" name="telephone"
                   placeholder="77 123 45 67" value="<?= htmlspecialchars($_SESSION['telephone'] ?? '') ?>">
            <small class="texte-secondaire">Requis pour Wave et Orange Money (simulation).</small>
        </div>

        <div class="info-encadre" id="infoSimAutrui">
            <strong>Mode simulation :</strong> aucun débit réel. Même principe que « Payer ma cotisation ».
        </div>

        <div class="champ">
            <label for="note">Note <span class="texte-secondaire">(optionnel)</span></label>
            <input type="text" id="note" name="note" placeholder="Ex : Aide exceptionnelle, accord entre membres...">
        </div>

        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Confirmer le paiement</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function toggleTelAutrui(mode) {
    var t = document.getElementById('champTelAutrui');
    var i = document.getElementById('infoSimAutrui');
    if (t) t.style.display = mode === 'especes' ? 'none' : '';
    if (i) i.style.display = mode === 'especes' ? 'none' : '';
}
document.addEventListener('DOMContentLoaded', function() {
    var c = document.querySelector('input[name="mode"]:checked');
    if (c) toggleTelAutrui(c.value);
});
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
