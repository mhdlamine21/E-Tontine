<?php
//  MEMBRES - ORDRE DES TOURS
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
$membres  = listerMembres($tontineId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    if (isset($_POST['tirage_au_sort'])) {
        tirageAuSortOrdre($tontineId);
        redirigerAvecMessage(APP_URL . '/pages/membres/ordre_des_tours.php?tontine=' . $tontineId, 'succes', 'Ordre tiré au sort avec succès.');
    } elseif (isset($_POST['ordre_ids'])) {
        $ordreIds = array_map('intval', $_POST['ordre_ids']);
        mettreAJourOrdre($tontineId, $ordreIds);
        redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'succes', 'Ordre des tours mis à jour.');
    }
}

$titrePage = 'Ordre des tours - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Ordre des tours</h1>
    </div>
</div>

<div class="formulaire-conteneur">

    <!-- Tirage au sort -->
    <section class="section-tableau">
        <div class="section-entete"><h2>Tirage au sort automatique</h2></div>
        <p>L'application attribue un ordre aléatoire à tous les membres.</p>
        <form method="POST" action="">
            <?= champCsrf() ?>
            <button type="submit" name="tirage_au_sort" class="btn btn-secondaire"
                    onclick="return confirm('Lancer le tirage au sort ? L\'ordre actuel sera remplacé.')">
                Lancer le tirage au sort
            </button>
        </form>
    </section>

    <div class="separateur-ou"><span>ou</span></div>

    <!-- Ordre manuel (glisser-déposer) -->
    <section class="section-tableau">
        <div class="section-entete"><h2>Définir l'ordre manuellement</h2></div>
        <p class="texte-secondaire">Glissez les membres pour changer leur position.</p>

        <form method="POST" action="" id="formeOrdre">
            <?= champCsrf() ?>
            <ul id="listeTriable" class="liste-triable">
                <?php foreach ($membres as $m): ?>
                <li class="item-triable" data-id="<?= $m['utilisateur_id'] ?>">
                    <span class="poignee">⠿</span>
                    <span class="avatar-cercle avatar-petit"><?= strtoupper(substr($m['nom_complet'], 0, 2)) ?></span>
                    <span class="item-nom"><?= htmlspecialchars($m['nom_complet']) ?></span>
                    <span class="badge badge-secondaire">Tour <?= $m['ordre_tour'] ?></span>
                    <input type="hidden" name="ordre_ids[]" value="<?= $m['utilisateur_id'] ?>">
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="actions-formulaire" style="margin-top:16px">
                <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
                <button type="submit" class="btn btn-principal">Enregistrer l'ordre</button>
            </div>
        </form>
    </section>
</div>

<script>
// Glisser-déposer simple avec l'API Drag and Drop
const liste = document.getElementById('listeTriable');
let elementEnCours = null;

liste.addEventListener('dragstart', e => {
    elementEnCours = e.target.closest('.item-triable');
    elementEnCours.classList.add('en-deplacement');
});
liste.addEventListener('dragend', e => {
    e.target.closest('.item-triable').classList.remove('en-deplacement');
    // Renuméroter les inputs cachés selon l'ordre visuel
    const items = liste.querySelectorAll('.item-triable');
    items.forEach((item, i) => {
        item.querySelector('input[type=hidden]').value = item.dataset.id;
    });
});
liste.addEventListener('dragover', e => {
    e.preventDefault();
    const cible = e.target.closest('.item-triable');
    if (cible && cible !== elementEnCours) {
        const rect = cible.getBoundingClientRect();
        const apres = e.clientY > rect.top + rect.height / 2;
        liste.insertBefore(elementEnCours, apres ? cible.nextSibling : cible);
    }
});

// Rendre les items draggable
document.querySelectorAll('.item-triable').forEach(el => el.setAttribute('draggable', true));
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
