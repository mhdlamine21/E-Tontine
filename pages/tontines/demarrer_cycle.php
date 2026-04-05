<?php
declare(strict_types=1);

require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/echeances.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$tontineId     = obtenirGetInt('id');
$utilisateurId = idUtilisateurConnecte();
exigerAdminTontine($utilisateurId, $tontineId);

$tontine = obtenirTontine($tontineId);
if (!$tontine || ($tontine['statut'] ?? '') === TONTINE_FERMEE) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Tontine introuvable ou fermée.');
}

if (($tontine['statut'] ?? '') !== TONTINE_ACTIVE) {
    redirigerAvecMessage(
        APP_URL . '/pages/tontines/voir.php?id=' . $tontineId,
        'erreur',
        'La tontine doit être « en cours » pour lancer un cycle (démarrez l’activité ou réactivez la tontine).'
    );
}

if (!empty($tontine['tour_complet_en_attente'])) {
    redirigerAvecMessage(
        APP_URL . '/pages/tontines/nouveau_tour.php?id=' . $tontineId,
        'info',
        'Le tour précédent est terminé : choisissez d’abord de lancer un nouveau tour ou de fermer la tontine.'
    );
}

if (cycleEnCours($tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Un cycle est déjà en cours.');
}

$membres = listerMembres($tontineId);
$erreurs = [];

$dateDebut = date('Y-m-d');
$ecSem     = isset($tontine['echeance_jour_semaine']) ? (int)$tontine['echeance_jour_semaine'] : null;
$ecMois    = isset($tontine['echeance_jour_mois']) ? (int)$tontine['echeance_jour_mois'] : null;
$dateLimiteCalculee = calculerDateLimiteCycle(
    (string)$tontine['frequence'],
    $ecSem > 0 ? $ecSem : null,
    $ecMois > 0 ? $ecMois : null,
    $dateDebut
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    if (empty($membres)) {
        $erreurs[] = 'Ajoutez au moins un membre avant de démarrer un cycle.';
    }

    if (empty($erreurs)) {
        $beneficiaireId = (int)$membres[0]['utilisateur_id'];
        $nbMembres      = count($membres);
        $theorique      = $nbMembres * (float)$tontine['montant_cotisation'];
        $numero         = prochainNumeroCycle($tontineId);
        $dateLimite      = calculerDateLimiteCycle(
            (string)$tontine['frequence'],
            $ecSem > 0 ? $ecSem : null,
            $ecMois > 0 ? $ecMois : null,
            $dateDebut
        );

        creerCycle($tontineId, $numero, $dateDebut, $dateLimite, $beneficiaireId, $theorique);
        redirigerAvecMessage(
            APP_URL . '/pages/tontines/voir.php?id=' . $tontineId,
            'succes',
            'Cycle n°' . $numero . ' démarré. Date limite des versements : ' . formaterDate($dateLimite) . '.'
        );
    }
}

$titrePage = 'Démarrer un cycle - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Démarrer un cycle de cotisation</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?> · <?= count($membres) ?> membre(s)</p>
    </div>
</div>

<div class="formulaire-conteneur">
    <?php foreach ($erreurs as $err): ?>
    <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if (empty($membres)): ?>
    <div class="alerte alerte-alerte">Invitez des membres avant de lancer un cycle.</div>
    <a href="<?= APP_URL ?>/pages/membres/liste.php?tontine=<?= $tontineId ?>" class="btn btn-principal">Gérer les membres</a>
    <?php else: ?>
    <p>Le bénéficiaire de ce cycle sera <strong><?= htmlspecialchars($membres[0]['nom_complet']) ?></strong> (premier dans l’ordre des tours). Vous pourrez ajuster l’ordre plus tard si besoin.</p>
    <p class="texte-secondaire">Montant total à collecter ce cycle : <strong><?= formaterMontant(count($membres) * (float)$tontine['montant_cotisation']) ?></strong></p>

    <div class="info-encadre" style="margin-bottom:18px">
        <p><strong>Date limite des cotisations (ce cycle) :</strong> <?= formaterDate($dateLimiteCalculee) ?></p>
        <?php $recap = texteRecapEcheanceTontine($tontine); ?>
        <?php if ($recap !== ''): ?>
        <p class="texte-secondaire" style="margin:8px 0 0"><?= htmlspecialchars($recap) ?> Après cette date, les membres n’ayant pas payé sont marqués en retard (lendemain).</p>
        <?php else: ?>
        <p class="texte-secondaire" style="margin:8px 0 0">Définissez une échéance dans « Modifier la tontine » pour un calcul précis ; en attendant, une date par défaut est proposée.</p>
        <?php endif; ?>
    </div>

    <form method="POST" action="">
        <?= champCsrf() ?>
        <div class="actions-formulaire">
            <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
            <button type="submit" class="btn btn-principal">Confirmer et démarrer le cycle</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
