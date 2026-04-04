<?php
// Liste des tontines que j'administre.
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$utilisateurId = idUtilisateurConnecte();
$tontinesAdmin = tontinesAdminDe($utilisateurId);

$titrePage = 'Mes tontines';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete page-entete--liste-tontines">
    <div class="page-entete__texte">
        <h1>Mes tontines</h1>
        <p class="texte-secondaire">Tontines que vous administrez</p>
    </div>
    <div class="page-entete__actions">
        <a href="<?= APP_URL ?>/pages/tontines/creer.php" class="btn btn-principal">+ Créer une tontine</a>
    </div>
</div>

<section class="section-tableau section-liste-tontines">
    <div class="section-entete">
        <h2>Liste des tontines <span class="badge badge-info">Admin</span></h2>
    </div>

    <?php if (empty($tontinesAdmin)): ?>
    <div class="etat-vide">
        <p>Vous n'avez pas encore créé de tontine.</p>
        <a href="<?= APP_URL ?>/pages/tontines/creer.php" class="btn btn-principal">Créer ma première tontine</a>
    </div>
    <?php else: ?>
    <div class="liste-cartes">
        <?php foreach ($tontinesAdmin as $tontine):
            renderCarteTontine($tontine, ROLE_ADMIN);
        endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
