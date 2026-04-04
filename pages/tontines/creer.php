<?php
//  TONTINES - CRÉER
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$erreurs = [];
$donnees = [
    'nom'                  => '',
    'description'          => '',
    'montant'              => '',
    'frequence'            => 'mensuelle',
    'nb_membres'           => '10',
    'echeance_jour_semaine'=> '3',
    'echeance_jour_mois'   => '5',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierCsrf()) {
        $erreurs[] = 'Requête invalide.';
    } else {
        $donnees['nom']                   = nettoyer($_POST['nom'] ?? '');
        $donnees['description']           = nettoyer($_POST['description'] ?? '');
        $donnees['montant']               = nettoyer($_POST['montant'] ?? '');
        $donnees['frequence']             = nettoyer($_POST['frequence'] ?? '');
        $donnees['nb_membres']            = nettoyer($_POST['nb_membres'] ?? '');
        $donnees['echeance_jour_semaine'] = nettoyer($_POST['echeance_jour_semaine'] ?? '');
        $donnees['echeance_jour_mois']    = nettoyer($_POST['echeance_jour_mois'] ?? '');

        if (empty($donnees['nom'])) {
            $erreurs[] = 'Le nom de la tontine est obligatoire.';
        }
        if (!is_numeric($donnees['montant']) || (float)$donnees['montant'] <= 0) {
            $erreurs[] = 'Le montant de la cotisation doit être un nombre positif.';
        } elseif ((float)$donnees['montant'] > 10000000) {
            $erreurs[] = 'Le montant ne peut pas dépasser 10 000 000 FCFA.';
        }
        if (!in_array($donnees['frequence'], [FREQUENCE_HEBDO, FREQUENCE_MENSUELLE, FREQUENCE_TRIMESTRIELLE], true)) {
            $erreurs[] = 'Fréquence invalide.';
        }
        if (!is_numeric($donnees['nb_membres']) || (int)$donnees['nb_membres'] < 2) {
            $erreurs[] = 'Le nombre de membres doit être au minimum 2.';
        }

        $echeanceSem = null;
        $echeanceMois = null;
        if ($donnees['frequence'] === FREQUENCE_HEBDO) {
            $echeanceSem = (int)$donnees['echeance_jour_semaine'];
            if ($echeanceSem < 1 || $echeanceSem > 7) {
                $erreurs[] = 'Choisissez le jour de la semaine d’échéance (lundi à dimanche).';
            }
        } else {
            $echeanceMois = (int)$donnees['echeance_jour_mois'];
            if ($echeanceMois < 1 || $echeanceMois > 31) {
                $erreurs[] = 'Indiquez un jour du mois entre 1 et 31 pour l’échéance des cotisations.';
            }
        }

        if (empty($erreurs)) {
            $res = creerTontine(
                idUtilisateurConnecte(),
                $donnees['nom'],
                $donnees['description'],
                (float)$donnees['montant'],
                $donnees['frequence'],
                (int)$donnees['nb_membres'],
                $echeanceSem,
                $echeanceMois
            );
            redirigerAvecMessage(
                APP_URL . '/pages/tontines/voir.php?id=' . $res['tontine_id'],
                'succes',
                'Tontine créée en « préparation ». Invitez les membres jusqu’à l’effectif complet, puis cliquez sur « Démarrer l’activité ».'
            );
        }
    }
}

$titrePage = 'Créer une tontine';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tableau_de_bord/accueil.php" class="lien-retour">← Retour</a>
        <h1>Créer une tontine</h1>
    </div>
</div>

<div class="formulaire-conteneur">

    <div class="banniere-guide-page">
        <div class="banniere-guide-page__icone">💡</div>
        <div class="banniere-guide-page__contenu">
            <h2 class="banniere-guide-page__titre">Guide de création d'une tontine</h2>
            <p class="banniere-guide-page__texte">En tant qu'administrateur, vous définissez les règles qui garantiront la confiance et la ponctualité de votre cercle :</p>
            <ul class="banniere-guide-page__etapes">
                <li><strong>Montant fixe :</strong> La somme versée par chaque adhérent à chaque cycle (ex : 20 000 FCFA).</li>
                <li><strong>Échéance stricte :</strong> Le jour limite pour payer. Le système signale automatiquement les retards le lendemain.</li>
                <li><strong>Capacité maximale :</strong> Une fois le quota de membres atteint, vous pourrez lancer les tirages ou confirmer l'ordre des tours.</li>
            </ul>
            <button type="button" class="banniere-guide-page__lien" onclick="ouvrirGuide('creer')">
                Consulter le guide complet sur la gestion des tontines &rarr;
            </button>
        </div>
    </div>

    <?php foreach ($erreurs as $err): ?>
    <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="">
        <?= champCsrf() ?>

        <div class="champ">
            <label for="nom">Nom de la tontine <span class="obligatoire">*</span></label>
            <input type="text" id="nom" name="nom"
                   value="<?= htmlspecialchars($donnees['nom']) ?>"
                   placeholder="Ex : Famille Sow, Collègues Bureau..." required autofocus>
        </div>

        <div class="champ">
            <label for="description">Description <span class="texte-secondaire">(optionnel)</span></label>
            <textarea id="description" name="description" rows="3"
                      placeholder="Décrivez l'objectif de cette tontine..."><?= htmlspecialchars($donnees['description']) ?></textarea>
        </div>

        <div class="grille-deux-colonnes-form">
            <div class="champ">
                <label for="montant">Montant de la cotisation <span class="obligatoire">*</span></label>
                <div class="champ-avec-suffixe">
                    <input type="number" id="montant" name="montant"
                           value="<?= htmlspecialchars($donnees['montant']) ?>"
                           placeholder="15000" min="100" step="100" required>
                    <span class="champ-suffixe">FCFA</span>
                </div>
            </div>

            <div class="champ">
                <label for="frequence">Fréquence <span class="obligatoire">*</span></label>
                <select id="frequence" name="frequence" required>
                    <option value="mensuelle"      <?= $donnees['frequence'] === 'mensuelle'      ? 'selected' : '' ?>>Mensuelle</option>
                    <option value="hebdomadaire"   <?= $donnees['frequence'] === 'hebdomadaire'   ? 'selected' : '' ?>>Hebdomadaire</option>
                    <option value="trimestrielle"  <?= $donnees['frequence'] === 'trimestrielle'  ? 'selected' : '' ?>>Trimestrielle</option>
                </select>
            </div>
        </div>

        <div class="champ" id="bloc-echeance-hebdo" style="display:none">
            <label for="echeance_jour_semaine">Jour limite des cotisations (chaque semaine) <span class="obligatoire">*</span></label>
            <select id="echeance_jour_semaine" name="echeance_jour_semaine">
                <?php
                $jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
                $selSem = (int)($donnees['echeance_jour_semaine'] ?: 3);
                foreach ($jours as $num => $lib): ?>
                <option value="<?= $num ?>" <?= $selSem === $num ? 'selected' : '' ?>><?= $lib ?></option>
                <?php endforeach; ?>
            </select>
            <small class="texte-secondaire">Les versements sont attendus au plus tard ce jour-là ; le lendemain, les retardataires sont signalés. Le bénéficiaire peut retirer la cagnotte une fois elle complète à partir de cette échéance.</small>
        </div>

        <div class="champ" id="bloc-echeance-mois" style="display:none">
            <label for="echeance_jour_mois">Jour du mois (limite des cotisations) <span class="obligatoire">*</span></label>
            <input type="number" id="echeance_jour_mois" name="echeance_jour_mois" min="1" max="31"
                   value="<?= htmlspecialchars($donnees['echeance_jour_mois']) ?>">
            <small class="texte-secondaire">Ex. : le 5 → chaque mois, cotisations au plus tard le 5 ; retards à partir du 6 ; retrait possible à l’échéance si la cagnotte est complète.</small>
        </div>

        <div class="champ">
            <label for="nb_membres">Nombre maximum de membres <span class="obligatoire">*</span></label>
            <input type="number" id="nb_membres" name="nb_membres"
                   value="<?= htmlspecialchars($donnees['nb_membres']) ?>"
                   min="2" max="100" required>
            <small class="texte-secondaire">Minimum 2 membres. Vous serez automatiquement compté.</small>
        </div>

        <div class="info-encadre">
            <strong>Important :</strong> vous êtes administrateur. La tontine est créée en <strong>préparation</strong> : invitez les membres, puis démarrez l’activité lorsque l’effectif est au complet.
        </div>

        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/tableau_de_bord/accueil.php" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Créer la tontine</button>
        </div>
    </form>
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
