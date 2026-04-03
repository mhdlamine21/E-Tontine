<?php
// Pied de page commun.
$aSousNavTontine = $aSousNavTontine ?? false;
?>
<?php if (!empty($aSousNavTontine)): ?>
</div>
</div>
<?php endif; ?>
</main>

<footer class="pied-de-page">
    <div class="pied-contenu">
        <span><?= APP_NOM ?> &copy; <?= date('Y') ?></span>
    </div>
</footer>

<?php if (estConnecte()): ?>
    <?php require_once __DIR__ . '/modal_guide.php'; ?>
    </div><!-- .app-main-shell -->
</div><!-- .app-shell -->
<?php endif; ?>

<script src="<?= APP_URL ?>/ressources/scripts/principal.js"></script>
<script src="<?= APP_URL ?>/ressources/scripts/notifications.js"></script>
</body>
</html>
