<?php
//  TONTINES - VOIR (tableau de bord d'une tontine)
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/membre.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/cagnotte.php';
require_once __DIR__ . '/../../fonctions/urgence.php';
require_once __DIR__ . '/../../fonctions/vote.php';
require_once __DIR__ . '/../../fonctions/echeances.php';
require_once __DIR__ . '/../../fonctions/notifications_cagnotte.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

$tontineId     = obtenirGetInt('id');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Tontine introuvable.');
}
if (($tontine['statut'] ?? '') === TONTINE_FERMEE) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Cette tontine est fermée.');
}

$opsOk = tontineAccepteOperations($tontine);

$estAdmin    = estAdminDeTontine($utilisateurId, $tontineId);
$membres     = listerMembres($tontineId);
$cycleActuel = cycleEnCours($tontineId);
$stats       = statistiquesTontine($tontineId);

if ($cycleActuel) {
    verifierDistributionAutomatique($tontineId, $cycleActuel['id']);
    notifierBeneficiaireJourLimiteSiApplicable($tontineId, $cycleActuel['id']);
    $cycleActuel = cycleEnCours($tontineId);
}

if ($cycleActuel && $opsOk && date('Y-m-d') > $cycleActuel['date_limite']) {
    marquerMembresEnRetard($tontineId, $cycleActuel['id']);
}

$paiementsCycle  = $cycleActuel ? paiementsDuCycle($tontineId, $cycleActuel['id']) : [];
$nonPayes        = $cycleActuel ? membresNonPayes($tontineId, $cycleActuel['id']) : [];
$urgencesAttente = $estAdmin ? listerUrgences($tontineId, URGENCE_EN_ATTENTE) : [];
$paiementsDejaPaye = $cycleActuel ? aDejaPayePourCycle($tontineId, $cycleActuel['id'], $utilisateurId) : false;
$tourEnAttente   = !empty($tontine['tour_complet_en_attente']);

if ($cycleActuel) {
    $etatDistrib = etatDistribution($tontineId, $cycleActuel['id']);
} else {
    $etatDistrib = null;
}

$soldesCycle = $cycleActuel ? resumeSoldesCycle((int)$cycleActuel['id'], $etatDistrib) : null;

$titrePage = $tontine['nom'];

$prochainDansOrdre = null;
if ($cycleActuel && !empty($membres) && !empty($cycleActuel['beneficiaire_id'])) {
    $bid = (int)$cycleActuel['beneficiaire_id'];
    $n = count($membres);
    foreach ($membres as $i => $m) {
        if ((int)$m['utilisateur_id'] === $bid) {
            $prochainDansOrdre = $membres[($i + 1) % $n];
            break;
        }
    }
}

require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete page-entete--tontine">
    <div>
        <a href="<?= APP_URL ?>/pages/tableau_de_bord/accueil.php" class="lien-retour">← Retour</a>
        <h1><?= htmlspecialchars($tontine['nom']) ?>
            <?php
            $classeBadgeTontine = match ($tontine['statut'] ?? '') {
                TONTINE_ACTIVE    => 'badge-succes',
                TONTINE_SUSPENDUE => 'badge-alerte',
                TONTINE_FERMEE    => 'badge-danger',
                default           => 'badge-secondaire',
            };
            ?>
            <span class="badge <?= $classeBadgeTontine ?>"><?= htmlspecialchars(libelleStatutTontine((string)($tontine['statut'] ?? ''))) ?></span>
        </h1>
        <p class="texte-secondaire">
            <?= libelleFrequence($tontine['frequence']) ?>
            · <?= formaterMontant((float)$tontine['montant_cotisation']) ?> / cotisation
            · <?= $stats['nb_membres'] ?> / <?= (int)$tontine['nombre_max_membres'] ?> membre(s)
        </p>
        <?php
        $recapEch = texteRecapEcheanceTontine($tontine);
        if ($recapEch !== ''): ?>
        <p class="texte-secondaire" style="margin-top:6px"><?= htmlspecialchars($recapEch) ?></p>
        <?php endif; ?>
        <?php if (!empty(trim((string)($tontine['description'] ?? '')))): ?>
        <p class="tontine-description-vue"><?= nl2br(htmlspecialchars($tontine['description'])) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($estAdmin): ?>
    <div class="actions-groupe">
        <a href="<?= APP_URL ?>/pages/tontines/modifier.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Modifier</a>
        <a href="<?= APP_URL ?>/pages/membres/liste.php?tontine=<?= $tontineId ?>" class="btn btn-secondaire">Membres</a>
        <?php if (($tontine['statut'] ?? '') === TONTINE_ACTIVE): ?>
        <a href="<?= APP_URL ?>/pages/tontines/arreter_activite.php?id=<?= $tontineId ?>" class="btn btn-danger btn-petit">Arrêter temporairement</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (($tontine['statut'] ?? '') === TONTINE_BROUILLON): ?>
<div class="alerte alerte-info">
    <strong>Préparation.</strong> Invitez des membres jusqu’à l’effectif complet (<?= (int)$tontine['nombre_max_membres'] ?> personnes), puis démarrez l’activité pour autoriser les cotisations.
    <?php if ($estAdmin && tontineEffectifComplet($tontine)): ?>
    <form method="POST" action="<?= APP_URL ?>/pages/tontines/demarrer_activite.php" style="display:inline;margin-left:12px">
        <?= champCsrf() ?>
        <input type="hidden" name="tontine_id" value="<?= $tontineId ?>">
        <button type="submit" class="btn btn-principal btn-petit">Démarrer l’activité</button>
    </form>
    <?php elseif ($estAdmin): ?>
    <span class="texte-secondaire"> - Effectif : <?= nombreMembresActifs($tontineId) ?> / <?= (int)$tontine['nombre_max_membres'] ?>.</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (($tontine['statut'] ?? '') === TONTINE_SUSPENDUE): ?>
<div class="alerte alerte-danger">
    <strong>Tontine arrêtée.</strong> Les cotisations et nouveaux cycles sont suspendus.
    <?php if ($estAdmin): ?>
    <form method="POST" action="<?= APP_URL ?>/pages/tontines/reactiver_activite.php" style="display:inline;margin-left:12px">
        <?= champCsrf() ?>
        <input type="hidden" name="tontine_id" value="<?= $tontineId ?>">
        <button type="submit" class="btn btn-principal btn-petit">Réactiver</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tourEnAttente): ?>
<div class="alerte alerte-succes">
    <strong>🎉 Tour complet !</strong> Tous les membres ont reçu la cagnotte une fois sur ce tour.
    <?php if ($estAdmin): ?>
    <a href="<?= APP_URL ?>/pages/tontines/nouveau_tour.php?id=<?= $tontineId ?>" class="btn btn-principal btn-petit" style="margin-left:12px">Décider de la suite →</a>
    <?php else: ?>
    <span class="texte-secondaire">L’administrateur va décider de lancer un nouveau tour ou de fermer la tontine.</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($cycleActuel && !empty($membres)): ?>
<section class="section-tableau section-ordre-cagnotte">
    <div class="section-entete">
        <h2>Ordre de réception de la cagnotte</h2>
        <span class="texte-secondaire">Tour du cycle actuel et suivants</span>
    </div>
    <p class="texte-secondaire ordre-cagnotte-intro">
        Seul le bénéficiaire désigné pour le cycle en cours peut retirer lorsque la cagnotte est complète.
    </p>
    <ol class="liste-ordre-cagnotte">
        <?php foreach ($membres as $idx => $m):
            $estTourActuel = (int)$m['utilisateur_id'] === (int)$cycleActuel['beneficiaire_id'];
            ?>
        <li class="<?= $estTourActuel ? 'liste-ordre-cagnotte--actuel' : '' ?>">
            <span class="liste-ordre-cagnotte__rang"><?= $idx + 1 ?></span>
            <span class="liste-ordre-cagnotte__nom"><?= htmlspecialchars($m['nom_complet']) ?></span>
            <?php if ($estTourActuel): ?>
            <span class="badge badge-succes">Tour actuel</span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php if ($prochainDansOrdre): ?>
    <p class="info-distribution ordre-cagnotte-suivant">
        <strong>Prochain bénéficiaire après ce cycle :</strong>
        <?= htmlspecialchars($prochainDansOrdre['nom_complet']) ?>
    </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- Alertes urgences admin -->
<?php if ($estAdmin && !empty($urgencesAttente)): ?>
<div class="alerte alerte-alerte">
    <strong><?= count($urgencesAttente) ?> demande(s) d'urgence</strong> en attente de votre décision.
    <a href="<?= APP_URL ?>/pages/urgences/valider.php?tontine=<?= $tontineId ?>">Traiter maintenant →</a>
</div>
<?php endif; ?>

<!-- Grille stats -->
<div class="grille-stats">
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= formaterMontant($stats['cagnotte_totale']) ?></div>
        <div class="carte-stat-label">Solde cagnotte (après retraits)</div>
        <?php if (($stats['cagnotte_deja_distribue'] ?? 0) > 0.01): ?>
        <div class="carte-stat-soustitre texte-secondaire" style="font-size:11px;margin-top:6px;line-height:1.35">
            Cotisations : <?= formaterMontant($stats['cagnotte_brut'] ?? 0) ?> · déjà retiré : <?= formaterMontant($stats['cagnotte_deja_distribue']) ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= $stats['nb_membres'] ?></div>
        <div class="carte-stat-label">Membres actifs</div>
    </div>
    <?php if ($estAdmin && $stats['nb_retards'] > 0): ?>
    <a href="<?= APP_URL ?>/pages/cotisations/retards.php?tontine=<?= $tontineId ?>" class="carte-stat carte-stat-alerte" style="text-decoration:none;display:block">
        <div class="carte-stat-nombre"><?= $stats['nb_retards'] ?></div>
        <div class="carte-stat-label">En retard - gérer →</div>
    </a>
    <?php else: ?>
    <div class="carte-stat <?= $stats['nb_retards'] > 0 ? 'carte-stat-alerte' : '' ?>">
        <div class="carte-stat-nombre"><?= $stats['nb_retards'] ?></div>
        <div class="carte-stat-label">En retard</div>
    </div>
    <?php endif; ?>
    <?php if ($estAdmin && $stats['urgences_attente'] > 0): ?>
    <a href="<?= APP_URL ?>/pages/urgences/valider.php?tontine=<?= $tontineId ?>" class="carte-stat carte-stat-alerte" style="text-decoration:none;display:block">
        <div class="carte-stat-nombre"><?= $stats['urgences_attente'] ?></div>
        <div class="carte-stat-label">Urgences - traiter →</div>
    </a>
    <?php else: ?>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= $stats['urgences_attente'] ?></div>
        <div class="carte-stat-label">Urgences</div>
    </div>
    <?php endif; ?>
</div>

<div class="grille-deux-colonnes">

    <!-- Cycle en cours -->
    <div>
        <?php if ($cycleActuel): ?>
        <section class="section-tableau">
            <div class="section-entete">
                <h2>Cycle n°<?= $cycleActuel['numero_cycle'] ?> en cours</h2>
                <span class="texte-secondaire">Limite : <?= formaterDate($cycleActuel['date_limite']) ?></span>
            </div>

            <!-- Barre de progression cagnotte -->
            <?php
            $colBrut = $soldesCycle ? $soldesCycle['collectee_totale'] : (float)$cycleActuel['cagnotte_collectee'];
            $resteEnCaisse = $soldesCycle ? $soldesCycle['non_encore_verse'] : $colBrut;
            $objAff = (float)$cycleActuel['cagnotte_theorique'];
            $pct = $objAff > 0 ? min(100, round(($colBrut / $objAff) * 100)) : 0;
            ?>
            <div class="barre-progression-conteneur">
                <div class="barre-progression-labels">
                    <span><?= formaterMontant($colBrut) ?> cotisés · objectif <?= formaterMontant($objAff) ?></span>
                    <span><?= $pct ?>%</span>
                </div>
                <div class="barre-progression">
                    <div class="barre-progression-remplie" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="texte-secondaire" style="font-size:12px;margin-top:4px">
                    <strong>Reste en cagnotte (non retiré) : <?= formaterMontant($resteEnCaisse) ?></strong>
                    <?php if ($soldesCycle && $soldesCycle['deja_verse_beneficiaire'] > 0.01): ?>
                    · déjà retiré : <?= formaterMontant($soldesCycle['deja_verse_beneficiaire']) ?>
                    · plafond bénéficiaire restant : <?= formaterMontant($soldesCycle['reste_du_beneficiaire']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($soldesCycle && ($soldesCycle['deja_verse_beneficiaire'] > 0.01 || $soldesCycle['non_encore_verse'] > 0.01)): ?>
            <div class="info-distribution" style="margin-top:10px">
                <strong>Clarification :</strong> les cotisations affichées incluent tout ce qui a été payé sur ce cycle.
                <?= $soldesCycle['deja_verse_beneficiaire'] > 0.01
                    ? ' Une partie (' . formaterMontant($soldesCycle['deja_verse_beneficiaire']) . ') a déjà été versée au bénéficiaire. '
                    : '' ?>
                Encore attribuable au titre de ce cycle (sans dépasser le plafond) : <strong><?= formaterMontant($soldesCycle['disponible_prochain_retrait']) ?></strong>.
                Les nouvelles cotisations s’ajoutent au total tant que le cycle n’est pas clos.
            </div>
            <?php endif; ?>

            <!-- Bénéficiaire du tour -->
            <?php if ($cycleActuel['beneficiaire_id']): ?>
            <div class="info-beneficiaire">
                <strong>Bénéficiaire du cycle :</strong>
                <?php
                foreach ($membres as $m) {
                    if ((int)$m['utilisateur_id'] === (int)$cycleActuel['beneficiaire_id']) {
                        echo htmlspecialchars($m['nom_complet']);
                        break;
                    }
                }
                ?>
                <?php if ((int)$cycleActuel['beneficiaire_id'] === $utilisateurId): ?>
                <span class="badge badge-succes">C'est votre tour !</span>
                <?php endif; ?>
                <?php if ($etatDistrib): ?>
                    <?php if ($etatDistrib['statut'] === DISTRIB_COMPLET): ?>
                    <span class="badge badge-succes">Cagnotte entièrement versée</span>
                    <?php elseif ($etatDistrib['statut'] === DISTRIB_PARTIEL): ?>
                    <span class="badge badge-alerte">Versement partiel (<?= formaterMontant((float)$etatDistrib['montant_recu']) ?> reçus)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions du membre connecté -->
            <?php if (!$paiementsDejaPaye && $opsOk): ?>
            <div class="actions-cycle">
                <a href="<?= APP_URL ?>/pages/cotisations/payer.php?tontine=<?= $tontineId ?>&cycle=<?= $cycleActuel['id'] ?>" class="btn btn-principal">
                    Payer ma cotisation
                </a>
                <?php if ($estAdmin): ?>
                <a href="<?= APP_URL ?>/pages/cotisations/payer_pour_autrui.php?tontine=<?= $tontineId ?>&cycle=<?= $cycleActuel['id'] ?>" class="btn btn-secondaire">
                    Payer pour un autre membre
                </a>
                <?php endif; ?>
            </div>
            <?php elseif (!$opsOk): ?>
            <div class="alerte alerte-alerte">Cotisations fermées (tontine en préparation ou arrêtée).</div>
            <?php else: ?>
            <div class="alerte alerte-succes">Vous avez payé votre cotisation pour ce cycle.</div>
            <?php endif; ?>

            <!-- Bouton recevoir la cagnotte (si c'est son tour) -->
            <?php if ($opsOk && (int)$cycleActuel['beneficiaire_id'] === $utilisateurId && $etatDistrib && $etatDistrib['statut'] !== DISTRIB_COMPLET): ?>
            <a href="<?= APP_URL ?>/pages/cagnotte/recevoir.php?tontine=<?= $tontineId ?>&cycle=<?= $cycleActuel['id'] ?>" class="btn btn-succes">
                Recevoir la cagnotte
            </a>
            <?php endif; ?>

        </section>

        <!-- Membres non payés -->
        <?php if (!empty($nonPayes)): ?>
        <section class="section-tableau">
            <div class="section-entete">
                <h2>En attente de paiement</h2>
                <span class="badge badge-alerte"><?= count($nonPayes) ?></span>
            </div>
            <ul class="liste-simple">
                <?php foreach ($nonPayes as $np): ?>
                <li><?= htmlspecialchars($np['nom_complet']) ?> <?= badgeStatut($np['statut']) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php else: ?>
        <section class="section-tableau">
            <div class="etat-vide">
                <?php if ($tourEnAttente): ?>
                <p>🎉 Le tour précédent est terminé.</p>
                <?php if ($estAdmin): ?>
                <p class="texte-secondaire">Choisissez de lancer un nouveau tour (avec un nouveau tirage au sort de l’ordre) ou de fermer la tontine.</p>
                <a href="<?= APP_URL ?>/pages/tontines/nouveau_tour.php?id=<?= $tontineId ?>" class="btn btn-principal" style="margin-top:12px">Décider de la suite →</a>
                <?php else: ?>
                <p class="texte-secondaire">En attente de la décision de l’administrateur (nouveau tour ou fermeture).</p>
                <?php endif; ?>
                <?php else: ?>
                <p>Aucun cycle en cours.</p>
                <?php if ($estAdmin && $opsOk): ?>
                <p class="texte-secondaire">La date limite de chaque cycle est calculée selon l’échéance définie (jour de la semaine ou jour du mois).</p>
                <a href="<?= APP_URL ?>/pages/tontines/demarrer_cycle.php?id=<?= $tontineId ?>" class="btn btn-principal" style="margin-top:12px">Démarrer un cycle</a>
                <?php elseif ($estAdmin && !$opsOk): ?>
                <p class="texte-secondaire">Démarrez d’abord l’activité de la tontine ou réactivez-la pour lancer un cycle.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- Liste membres -->
    <div>
        <section class="section-tableau">
            <div class="section-entete">
                <h2>Membres (<?= count($membres) ?>)</h2>
                <?php if ($estAdmin): ?>
                <a href="<?= APP_URL ?>/pages/membres/ajouter.php?tontine=<?= $tontineId ?>" class="btn btn-principal btn-petit">+ Ajouter</a>
                <?php endif; ?>
            </div>

            <?php foreach ($membres as $membre): ?>
            <?php renderCarteMembre($membre, $estAdmin, $tontineId); ?>
            <?php endforeach; ?>
        </section>
    </div>

</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
