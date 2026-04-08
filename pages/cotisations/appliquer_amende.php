<?php
//  APPLIQUER UNE AMENDE À UN MEMBRE EN RETARD
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/amende.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId = obtenirGetInt('tontine');
$cycleId = obtenirGetInt('cycle');
$membreId = obtenirGetInt('membre');
$utilisateurId = idUtilisateurConnecte();

// Vérifier que l'utilisateur est admin de la tontine
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
$cycle = obtenirCycleSimple($cycleId);

if (!$cycle || $cycle['tontine_id'] != $tontineId) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Cycle invalide.');
}

// Calcul des jours de retard
$dateLimite = $cycle['date_limite'];
$aujourdhui = date('Y-m-d');
$joursRetard = max(0, (strtotime($aujourdhui) - strtotime($dateLimite)) / 86400);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $typeCalcul = nettoyer($_POST['type_calcul'] ?? 'fixe');
    $montant = 0;
    $motif = nettoyer($_POST['motif'] ?? '');
    
    if ($typeCalcul === 'fixe') {
        $montant = (float)($_POST['montant_fixe'] ?? 0);
    } else {
        $montantParJour = (float)($_POST['montant_par_jour'] ?? 0);
        $montant = $montantParJour * $joursRetard;
    }
    
    if ($montant <= 0) {
        redirigerAvecMessage(APP_URL . '/pages/cotisations/retards.php?tontine=' . $tontineId, 'erreur', 'Le montant de l\'amende doit être supérieur à 0.');
    }
    
    $res = appliquerAmende($tontineId, $cycleId, $membreId, $montant, $typeCalcul, (int)$joursRetard, $motif);
    redirigerAvecMessage(APP_URL . '/pages/cotisations/retards.php?tontine=' . $tontineId, 
                         $res['succes'] ? 'succes' : 'erreur', 
                         $res['message']);
}

$titrePage = 'Appliquer une amende';
require_once __DIR__ . '/../../gabarits/entete.php';

// Récupérer les infos du membre
$bd = connexionBD();
$req = $bd->prepare('SELECT nom_complet FROM utilisateurs WHERE id = ?');
$req->execute([$membreId]);
$membre = $req->fetch();
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/cotisations/retards.php?tontine=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Appliquer une amende</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?> - Membre : <?= htmlspecialchars($membre['nom_complet']) ?></p>
    </div>
</div>

<div class="formulaire-conteneur">
    <div class="info-encadre">
        <strong>Informations :</strong><br>
        Date limite du cycle : <?= formaterDate($cycle['date_limite']) ?><br>
        Jours de retard : <strong><?= $joursRetard ?></strong> jour(s)
    </div>

    <form method="POST" action="">
        <?= champCsrf() ?>
        
        <div class="champ">
            <label>Type de calcul de l'amende</label>
            <div class="choix-inline" style="display:flex; gap:20px; margin-top:8px">
                <label>
                    <input type="radio" name="type_calcul" value="fixe" checked onchange="toggleTypeCalcul()">
                    Montant fixe
                </label>
                <label>
                    <input type="radio" name="type_calcul" value="par_jour" onchange="toggleTypeCalcul()">
                    Montant par jour de retard
                </label>
            </div>
        </div>

        <div class="champ" id="bloc-montant-fixe">
            <label for="montant_fixe">Montant de l'amende (FCFA)</label>
            <input type="number" id="montant_fixe" name="montant_fixe" min="100" step="100" placeholder="Ex: 2000">
        </div>

        <div class="champ" id="bloc-montant-par-jour" style="display:none">
            <label for="montant_par_jour">Montant par jour de retard (FCFA/jour)</label>
            <input type="number" id="montant_par_jour" name="montant_par_jour" min="100" step="100" placeholder="Ex: 500">
            <small class="texte-secondaire">Total calculé automatiquement : <span id="totalCalcule">0</span> FCFA (<?= $joursRetard ?> jours)</small>
        </div>

        <div class="champ">
            <label for="motif">Motif de l'amende <span class="texte-secondaire">(optionnel)</span></label>
            <textarea id="motif" name="motif" rows="3" placeholder="Raison de l'amende..."></textarea>
        </div>

        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/cotisations/retards.php?tontine=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Appliquer l'amende</button>
        </div>
    </form>
</div>

<script>
function toggleTypeCalcul() {
    const type = document.querySelector('input[name="type_calcul"]:checked').value;
    const blocFixe = document.getElementById('bloc-montant-fixe');
    const blocParJour = document.getElementById('bloc-montant-par-jour');
    
    if (type === 'fixe') {
        blocFixe.style.display = 'block';
        blocParJour.style.display = 'none';
    } else {
        blocFixe.style.display = 'none';
        blocParJour.style.display = 'block';
    }
}

document.getElementById('montant_par_jour')?.addEventListener('input', function() {
    const jours = <?= $joursRetard ?>;
    const total = parseInt(this.value) * jours;
    document.getElementById('totalCalcule').innerText = total.toLocaleString('fr-FR');
});
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>