<?php
//  COTISATIONS - PAYER
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/paiement.php';
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

if ($cycleId <= 0) {
    $autoCy = cycleEnCours($tontineId);
    if ($autoCy) {
        header('Location: ' . APP_URL . '/pages/cotisations/payer.php?tontine=' . $tontineId . '&cycle=' . (int)$autoCy['id']);
        exit;
    }
}

$cycle = obtenirCycleSimple($cycleId);
if (!$cycle || (int)$cycle['tontine_id'] !== $tontineId) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Aucun cycle en cours ou cycle introuvable.');
}

if (aDejaPayePourCycle($tontineId, $cycleId, $utilisateurId)) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Vous avez déjà payé pour ce cycle.');
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $mode      = nettoyer($_POST['mode']      ?? '');
    $telephone = nettoyer($_POST['telephone'] ?? '');

    if (!in_array($mode, [MODE_WAVE, MODE_ORANGE_MONEY, MODE_ESPECES])) $erreurs[] = 'Mode de paiement invalide.';
    if ($mode !== MODE_ESPECES && empty($telephone)) $erreurs[] = 'Le numéro de téléphone est requis.';

    if (empty($erreurs)) {
        $res = enregistrerPaiement(
            $tontineId, $cycleId, $utilisateurId, $utilisateurId,
            (float)$tontine['montant_cotisation'], $mode, $telephone
        );
        if ($res['succes']) {
            redirigerAvecMessage(
                APP_URL . '/export/recu_paiement.php?paiement=' . $res['paiement_id'],
                'succes',
                'Paiement confirmé ! Référence : ' . $res['reference']
            );
        } else {
            $erreurs[] = $res['message'];
        }
    }
}

$titrePage = 'Payer ma cotisation';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Payer ma cotisation</h1>
    </div>
</div>

<div class="formulaire-conteneur formulaire-elargi">

    <!-- Récapitulatif -->
    <div class="recap-paiement">
        <div class="recap-ligne">
            <span>Tontine</span>
            <strong><?= htmlspecialchars($tontine['nom']) ?></strong>
        </div>
        <div class="recap-ligne">
            <span>Cycle n°</span>
            <strong><?= $cycle['numero_cycle'] ?></strong>
        </div>
        <div class="recap-ligne">
            <span>Date limite</span>
            <strong><?= formaterDate($cycle['date_limite']) ?></strong>
        </div>
        <div class="recap-ligne recap-total">
            <span>Montant à payer</span>
            <strong class="montant-principal"><?= formaterMontant((float)$tontine['montant_cotisation']) ?></strong>
        </div>
    </div>

    <?php foreach ($erreurs as $err): ?>
    <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="" id="formePaiement">
        <?= champCsrf() ?>

        <!-- Choix du mode de paiement -->
        <div class="champ">
            <label>Mode de paiement</label>
            <div class="choix-paiement">
                <label class="option-paiement" for="wave">
                    <input type="radio" id="wave" name="mode" value="wave" checked
                           onchange="toggleTelephone(this.value)">
                    <div class="option-paiement-corps">
                        <div class="option-paiement-logo logo-wave">W</div>
                        <span>Wave Money</span>
                    </div>
                </label>
                <label class="option-paiement" for="orange_money">
                    <input type="radio" id="orange_money" name="mode" value="orange_money"
                           onchange="toggleTelephone(this.value)">
                    <div class="option-paiement-corps">
                        <div class="option-paiement-logo logo-orange">O</div>
                        <span>Orange Money</span>
                    </div>
                </label>
                <label class="option-paiement" for="especes">
                    <input type="radio" id="especes" name="mode" value="especes"
                           onchange="toggleTelephone(this.value)">
                    <div class="option-paiement-corps">
                        <div class="option-paiement-logo logo-especes">E</div>
                        <span>Espèces</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="champ" id="champTelephone">
            <label for="telephone">Numéro de téléphone</label>
            <input type="tel" id="telephone" name="telephone"
                   placeholder="77 123 45 67" value="<?= htmlspecialchars($_SESSION['telephone'] ?? '') ?>">
            <small class="texte-secondaire">Numéro associé à votre compte Wave ou Orange Money.</small>
        </div>

        <div class="info-encadre" id="infoSimulation">
            <strong>Mode simulation :</strong> Aucune somme réelle ne sera débitée. Le paiement est simulé à des fins de démonstration.
        </div>

        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal" id="btnPayer">
                Payer <?= formaterMontant((float)$tontine['montant_cotisation']) ?>
            </button>
        </div>
    </form>
</div>

<script>
function toggleTelephone(mode) {
    document.getElementById('champTelephone').style.display = mode === 'especes' ? 'none' : '';
    document.getElementById('infoSimulation').style.display = mode === 'especes' ? 'none' : '';
}
document.getElementById('formePaiement').addEventListener('submit', function() {
    const btn = document.getElementById('btnPayer');
    btn.textContent = 'Traitement en cours...';
    btn.disabled = true;
});
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
