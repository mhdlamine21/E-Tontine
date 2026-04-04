<?php
//  TONTINES - MODIFIER
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('id');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide.';
    } else {
        $nom        = nettoyer($_POST['nom']        ?? '');
        $description= nettoyer($_POST['description']?? '');
        $montant    = nettoyer($_POST['montant']    ?? '');
        $frequence  = nettoyer($_POST['frequence']  ?? '');
        $nbMembres  = nettoyer($_POST['nb_membres'] ?? '');

        if (empty($nom)) {
            $erreurs[] = 'Le nom est obligatoire.';
        }
        if (!is_numeric($montant) || (float)$montant <= 0) {
            $erreurs[] = 'Montant invalide.';
        } elseif ((float)$montant > 10000000) {
            $erreurs[] = 'Le montant ne peut pas dépasser 10 000 000 FCFA.';
        }
        if (!in_array($frequence, [FREQUENCE_HEBDO, FREQUENCE_MENSUELLE, FREQUENCE_TRIMESTRIELLE], true)) {
            $erreurs[] = 'Fréquence invalide.';
        }

        $echeanceSem = null;
        $echeanceMois = null;
        if ($frequence === FREQUENCE_HEBDO) {
            $echeanceSem = (int)($_POST['echeance_jour_semaine'] ?? 0);
            if ($echeanceSem < 1 || $echeanceSem > 7) {
                $erreurs[] = 'Jour de la semaine invalide.';
            }
        } else {
            $echeanceMois = (int)($_POST['echeance_jour_mois'] ?? 0);
            if ($echeanceMois < 1 || $echeanceMois > 31) {
                $erreurs[] = 'Jour du mois invalide (1-31).';
            }
        }

        if (empty($erreurs)) {
            modifierTontine($tontineId, $nom, $description, (float)$montant, $frequence, (int)$nbMembres, $echeanceSem, $echeanceMois);
            redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'succes', 'Tontine mise à jour.');
        }
    }
}

$titrePage = 'Modifier - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Modifier la tontine</h1>
    </div>
</div>

<div class="formulaire-conteneur">
    <?php foreach ($erreurs as $err): ?>
    <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="">
        <?= champCsrf() ?>

        <div class="champ">
            <label>Nom de la tontine</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($tontine['nom']) ?>" required>
        </div>

        <div class="champ">
            <label>Description</label>
            <textarea name="description" rows="3"><?= htmlspecialchars($tontine['description'] ?? '') ?></textarea>
        </div>

        <div class="grille-deux-colonnes-form">
            <div class="champ">
                <label>Montant de la cotisation</label>
                <div class="champ-avec-suffixe">
                    <input type="number" name="montant" value="<?= $tontine['montant_cotisation'] ?>" min="100" step="100" required>
                    <span class="champ-suffixe">FCFA</span>
                </div>
            </div>
            <div class="champ">
                <label>Fréquence</label>
                <select name="frequence" id="frequence">
                    <option value="mensuelle"     <?= $tontine['frequence'] === 'mensuelle'     ? 'selected':'' ?>>Mensuelle</option>
                    <option value="hebdomadaire"  <?= $tontine['frequence'] === 'hebdomadaire'  ? 'selected':'' ?>>Hebdomadaire</option>
                    <option value="trimestrielle" <?= $tontine['frequence'] === 'trimestrielle' ? 'selected':'' ?>>Trimestrielle</option>
                </select>
            </div>
        </div>

        <?php
        $defSem = (int)($tontine['echeance_jour_semaine'] ?? 3);
        $defMois = (int)($tontine['echeance_jour_mois'] ?? 5);
        if ($defSem < 1 || $defSem > 7) {
            $defSem = 3;
        }
        if ($defMois < 1 || $defMois > 31) {
            $defMois = 5;
        }
        ?>
        <div class="champ" id="bloc-echeance-hebdo" style="display:none">
            <label for="echeance_jour_semaine">Jour limite des cotisations (semaine)</label>
            <select id="echeance_jour_semaine" name="echeance_jour_semaine">
                <?php
                $jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
                foreach ($jours as $num => $lib): ?>
                <option value="<?= $num ?>" <?= $defSem === $num ? 'selected' : '' ?>><?= $lib ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ" id="bloc-echeance-mois" style="display:none">
            <label for="echeance_jour_mois">Jour du mois (limite)</label>
            <input type="number" id="echeance_jour_mois" name="echeance_jour_mois" min="1" max="31" value="<?= $defMois ?>">
        </div>

        <div class="champ">
            <label>Nombre maximum de membres</label>
            <input type="number" name="nb_membres" value="<?= $tontine['nombre_max_membres'] ?>" min="2" max="100" required>
        </div>

        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Enregistrer</button>
        </div>
    </form>

    <!-- Zone danger : fermer la tontine -->
    <div class="zone-danger">
        <h3>Zone de danger</h3>
        <p>Fermer la tontine la rend inactive. Cette action est irréversible.</p>
        <a href="<?= APP_URL ?>/pages/tontines/fermer.php?id=<?= $tontineId ?>"
           class="btn btn-danger"
           onclick="return confirm('Êtes-vous sûr de vouloir fermer cette tontine ?')">
           Fermer la tontine
        </a>
    </div>
</div>

<script>
(function () {
    var freq = document.getElementById('frequence');
    var hebdo = document.getElementById('bloc-echeance-hebdo');
    var mois = document.getElementById('bloc-echeance-mois');
    function sync() {
        var v = freq.value;
        hebdo.style.display = v === 'hebdomadaire' ? 'block' : 'none';
        mois.style.display = (v === 'mensuelle' || v === 'trimestrielle') ? 'block' : 'none';
    }
    freq.addEventListener('change', sync);
    sync();
})();
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
