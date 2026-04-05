<?php
// Liste des tontines que j'ai rejointes.
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$utilisateurId  = idUtilisateurConnecte();
$tontinesMembre = tontinesMembreDe($utilisateurId);

$titrePage = 'Mes adhésions';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete page-entete--liste-tontines">
    <div class="page-entete__texte">
        <h1>Mes adhésions</h1>
        <p class="texte-secondaire">Tontines auxquelles vous participez</p>
    </div>
</div>

<section class="section-tableau section-liste-tontines">
    <div class="section-entete">
        <h2>Liste des adhésions <span class="badge badge-secondaire">Membre</span></h2>
    </div>

    <?php if (empty($tontinesMembre)): ?>
    <div class="etat-vide">
        <p>Vous n'avez rejoint aucune tontine pour le moment.</p>
        <p class="texte-secondaire">Demandez un lien d'invitation à l'admin d'une tontine.</p>
    </div>
    <?php else: ?>
    <div class="liste-cartes">
        <?php foreach ($tontinesMembre as $tontine):
            renderCarteTontine($tontine, ROLE_MEMBRE);
        endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
