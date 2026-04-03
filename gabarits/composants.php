<?php
// Fonctions de rendu des composants UI réutilisables.

function renderCarteTontine(array $tontine, string $role): void {
    if (empty($tontine)) return;
    $lienVoir = APP_URL . '/pages/tontines/voir.php?id=' . (int)$tontine['id'];
    ?>
    <div class="carte-tontine">
        <div class="carte-tontine-entete">
            <div>
                <h3 class="carte-tontine-nom">
                    <a href="<?= $lienVoir ?>"><?= htmlspecialchars($tontine['nom']) ?></a>
                </h3>
                <p class="carte-tontine-detail">
                    <?= htmlspecialchars(libelleFrequence($tontine['frequence'])) ?>
                    &nbsp;·&nbsp;
                    <?= formaterMontant((float)$tontine['montant_cotisation']) ?>
                    &nbsp;·&nbsp;
                    <?= (int)($tontine['nb_membres'] ?? 0) ?> membre(s)
                </p>
            </div>
            <span class="badge <?= $role === ROLE_ADMIN ? 'badge-info' : 'badge-secondaire' ?>">
                <?= $role === ROLE_ADMIN ? 'Admin' : 'Membre' ?>
            </span>
            <?php
            $st = $tontine['statut'] ?? '';
            if ($st !== '' && $st !== TONTINE_ACTIVE): ?>
            <span class="badge <?= $st === TONTINE_BROUILLON ? 'badge-secondaire' : 'badge-alerte' ?>"><?= htmlspecialchars(libelleStatutTontine($st)) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($tontine['description'])): ?>
        <p class="carte-tontine-description"><?= htmlspecialchars(tronquer($tontine['description'], 100)) ?></p>
        <?php endif; ?>

        <div class="carte-tontine-actions">
            <a href="<?= $lienVoir ?>" class="btn btn-principal btn-petit">Voir</a>
            <?php if ($role === ROLE_ADMIN): ?>
            <a href="<?= APP_URL ?>/pages/membres/liste.php?tontine=<?= (int)$tontine['id'] ?>" class="btn btn-secondaire btn-petit">Membres</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderCarteMembre(array $membre, bool $estAdmin, int $tontineId): void {
    if (empty($membre)) return;
    $initiales = strtoupper(substr($membre['nom_complet'], 0, 2));
    $badgeRole = $membre['role'] === ROLE_ADMIN
        ? '<span class="badge badge-info">Admin</span>'
        : '<span class="badge badge-secondaire">Membre</span>';
    
    // Calcul du score et de sa classe CSS
    $score = (float)($membre['score_fiabilite'] ?? 100);
    $scoreClass = 'score-moyen';
    $scoreBadge = 'badge-secondaire';
    if ($score >= 80) {
        $scoreClass = 'score-haut';
        $scoreBadge = 'badge-succes';
    } elseif ($score < 50) {
        $scoreClass = 'score-bas';
        $scoreBadge = 'badge-danger';
    } elseif ($score < 70) {
        $scoreBadge = 'badge-alerte';
    }
    ?>
    <div class="carte-membre">
        <div class="carte-membre-gauche">
            <div class="avatar-cercle"><?= htmlspecialchars($initiales) ?></div>
            <div>
                <div class="carte-membre-nom"><?= htmlspecialchars($membre['nom_complet']) ?></div>
                <div class="carte-membre-detail">
                    Tour n°<?= (int)$membre['ordre_tour'] ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars($membre['telephone']) ?>
                    &nbsp;·&nbsp;
                    <span class="<?= $scoreClass ?>">Score : <?= number_format($score, 1) ?>%</span>
                </div>
            </div>
        </div>

        <div class="carte-membre-droite">
            <?= badgeStatut($membre['statut']) ?>
            <?= $badgeRole ?>
            
            <?php if ($estAdmin): ?>
            <span class="badge <?= $scoreBadge ?>">Fiab. <?= number_format($score, 1) ?>%</span>
            <?php endif; ?>

            <?php if ($estAdmin && $membre['role'] !== ROLE_ADMIN): ?>
            <div class="carte-membre-actions">
                <form method="POST" action="<?= APP_URL ?>/pages/membres/exclure.php?tontine=<?= $tontineId ?>&membre=<?= (int)$membre['utilisateur_id'] ?>">
                    <?= champCsrf() ?>
                    <button type="submit"
                            class="btn btn-danger btn-tres-petit"
                            onclick="return confirm('Confirmer l\'exclusion de ce membre ?')">
                        Exclure
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>