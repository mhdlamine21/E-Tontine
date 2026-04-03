<?php
declare(strict_types=1);

$nbNotifs = $nbNotifs ?? ((function_exists('estConnecte') && estConnecte() && function_exists('nbNotificationsNonLues') && function_exists('idUtilisateurConnecte'))
    ? nbNotificationsNonLues(idUtilisateurConnecte())
    : 0);
$pageActuelle         = basename($_SERVER['PHP_SELF']);
$navSectionPrincipale = $navSectionPrincipale ?? null;
$actifAccueil         = $pageActuelle === 'accueil.php' && empty($navSectionPrincipale);
$actifMesTontines     = $pageActuelle === 'mes_tontines.php' || $navSectionPrincipale === 'mes_tontines';
$actifMesAdhesions    = $pageActuelle === 'mes_adhesions.php' || $navSectionPrincipale === 'mes_adhesions';
$initialesUtilisateur = '';
$prenomNav            = '';
$notifsPanelListe     = [];
if (estConnecte()) {
    $nomComplet = trim((string)nomUtilisateurConnecte());
    $partiesNom = preg_split('/\s+/', $nomComplet) ?: [];
    $premiereLettre = $partiesNom[0][0] ?? '';
    $deuxiemeLettre = $partiesNom[count($partiesNom) - 1][0] ?? '';
    $initialesUtilisateur = strtoupper($premiereLettre . $deuxiemeLettre);
    $prenomNav = $partiesNom[0] !== '' ? $partiesNom[0] : 'Membre';
    $notifsPanelListe = notificationsUtilisateur(idUtilisateurConnecte(), 12);
}
?>
<?php if (estConnecte()): ?>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a href="<?= APP_URL ?>/pages/tableau_de_bord/accueil.php" class="nav-logo">
            <img src="<?= APP_EMBLEM_URL ?>" alt="<?= htmlspecialchars(APP_NOM) ?>" class="nav-logo-img" width="48" height="48" decoding="async">
            <span class="nav-logo-texte"><?= APP_NOM ?></span>
        </a>

        <div class="sidebar-section-title">Menu principal</div>
        <ul class="nav-liens-principaux">
            <li><a href="<?= APP_URL ?>/pages/tableau_de_bord/accueil.php" class="<?= $actifAccueil ? 'actif' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Accueil
            </a></li>
            <li><a href="<?= APP_URL ?>/pages/tontines/mes_tontines.php" class="<?= $actifMesTontines ? 'actif' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Mes tontines
            </a></li>
            <li><a href="<?= APP_URL ?>/pages/tontines/mes_adhesions.php" class="<?= $actifMesAdhesions ? 'actif' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Mes adhésions
            </a></li>
        </ul>

        <div class="sidebar-separateur"></div>
        <div class="sidebar-section-title">Actions</div>
        <div class="sidebar-bas">
            <a href="<?= APP_URL ?>/pages/tontines/creer.php" class="sidebar-lien-secondaire<?= $pageActuelle === 'creer.php' ? ' actif' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Créer une tontine
            </a>
            <a href="<?= APP_URL ?>/pages/tontines/rejoindre.php" class="sidebar-lien-secondaire<?= $pageActuelle === 'rejoindre.php' ? ' actif' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                Rejoindre un groupe
            </a>
            <button type="button" class="sidebar-lien-secondaire" onclick="ouvrirGuide('accueil')" style="text-align:left;border:none;background:none;width:100%;cursor:pointer;font-family:inherit;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Guides d'utilisation
            </button>
            <?php if (estSuperAdmin()): ?>
            <div class="sidebar-separateur"></div>
            <div class="sidebar-section-title" style="font-size:10px; margin-top:4px;">Administration</div>
            <a href="<?= APP_URL ?>/pages/super_admin/tableau_de_bord.php" class="sidebar-lien-secondaire sidebar-lien-admin<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/super_admin/') ? ' actif' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Super administration
            </a>
            <?php endif; ?>
        </div>
    </aside>
    <div class="app-backdrop" id="appBackdrop" onclick="toggleMenuMobile()"></div>

    <div class="app-main-shell">
        <header class="barre-nav">
            <div class="nav-contenu">
                <button type="button" class="nav-ouvrir-sidebar" onclick="toggleMenuMobile()" aria-expanded="false" aria-controls="appSidebar" aria-label="Ouvrir le menu latéral">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <div class="nav-contenu-centre">
                    <form class="topbar-recherche" onsubmit="return false;">
                        <input type="search" placeholder="Rechercher une tontine, un membre…" aria-label="Recherche">
                    </form>
                </div>

                <div class="nav-droite">
                    <button type="button" class="btn btn-secondaire btn-tres-petit nav-btn-guide" onclick="ouvrirGuide('accueil')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span class="nav-btn-guide__texte">Guides</span>
                    </button>

                    <div class="nav-notif-wrap">
                        <button type="button" class="nav-notif-btn nav-notif" id="btnNotifPanel" aria-expanded="false" aria-haspopup="true" aria-controls="panneauNotifs" title="Notifications">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                            <?php if ($nbNotifs > 0): ?>
                            <span class="nav-notif-compteur"><?= $nbNotifs > 99 ? '99+' : $nbNotifs ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="nav-notif-panel" id="panneauNotifs" role="dialog" aria-label="Notifications" hidden>
                            <div class="nav-notif-panel__entete">
                                <span>Notifications</span>
                                <a href="<?= APP_URL ?>/pages/notifications/liste.php" class="nav-notif-panel__lien-tout">Tout voir</a>
                            </div>
                            <ul class="nav-notif-panel__liste" id="listeNotifsPanel">
                                <?php if (empty($notifsPanelListe)): ?>
                                <li class="nav-notif-panel__vide">Aucune notification.</li>
                                <?php else: ?>
                                <?php foreach ($notifsPanelListe as $notif): ?>
                                <li class="nav-notif-panel__item<?= empty($notif['est_lu']) ? ' nav-notif-panel__item--nonlue' : '' ?>"
                                    data-notif-id="<?= (int)$notif['id'] ?>">
                                    <?php if (!empty($notif['lien'])): ?>
                                    <a href="<?= htmlspecialchars($notif['lien']) ?>" class="nav-notif-panel__lien">
                                        <span class="nav-notif-panel__titre"><?= htmlspecialchars($notif['titre']) ?></span>
                                        <span class="nav-notif-panel__msg"><?= htmlspecialchars(tronquer($notif['message'], 100)) ?></span>
                                        <span class="nav-notif-panel__date"><?= htmlspecialchars(tempsEcoule($notif['date_creation'])) ?></span>
                                    </a>
                                    <?php else: ?>
                                    <div class="nav-notif-panel__lien nav-notif-panel__lien--sanslien">
                                        <span class="nav-notif-panel__titre"><?= htmlspecialchars($notif['titre']) ?></span>
                                        <span class="nav-notif-panel__msg"><?= htmlspecialchars(tronquer($notif['message'], 100)) ?></span>
                                        <span class="nav-notif-panel__date"><?= htmlspecialchars(tempsEcoule($notif['date_creation'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="nav-utilisateur" id="navUtilisateur">
                        <button type="button" class="nav-avatar-btn" onclick="toggleMenuProfil()" aria-haspopup="true" aria-expanded="false" aria-controls="menuProfil" aria-label="Menu du compte">
                            <span class="nav-avatar"><?= htmlspecialchars($initialesUtilisateur !== '' ? $initialesUtilisateur : 'U') ?></span>
                            <span class="nav-avatar-nom"><?= htmlspecialchars($prenomNav) ?></span>
                            <span class="nav-avatar-fleche" aria-hidden="true">▾</span>
                        </button>
                        <div class="nav-menu-profil" id="menuProfil" role="menu">
                            <div class="nav-menu-entete">
                                <div class="nav-menu-nom-complet"><?= htmlspecialchars(nomUtilisateurConnecte()) ?></div>
                                <div class="nav-menu-email"><?= htmlspecialchars($_SESSION['utilisateur']['email'] ?? '') ?></div>
                                <span class="nav-menu-role-badge">
                                    <?= estSuperAdmin() ? 'Super Administrateur' : 'Membre' ?>
                                </span>
                            </div>
                            <ul class="nav-menu-liens">
                                <li>
                                    <a href="<?= APP_URL ?>/pages/tableau_de_bord/profil.php" role="menuitem">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        <span>Mon profil</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?= APP_URL ?>/pages/tontines/mes_tontines.php" role="menuitem">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                        <span>Mes tontines</span>
                                    </a>
                                </li>
                                <li>
                                    <button type="button" onclick="ouvrirGuide('accueil'); toggleMenuProfil();" role="menuitem">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                        <span>Guides &amp; Aide</span>
                                    </button>
                                </li>
                                <li class="nav-menu-separateur" role="separator"></li>
                                <li>
                                    <form method="POST" action="<?= APP_URL ?>/deconnexion.php" style="margin:0;padding:0;width:100%;">
                                        <?= champCsrf() ?>
                                        <button type="submit" class="nav-menu-deconnexion-btn" role="menuitem">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                                <polyline points="16 17 21 12 16 7"></polyline>
                                                <line x1="21" y1="12" x2="9" y2="12"></line>
                                            </svg>
                                            <span>Se déconnecter</span>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </header>
<?php else: ?>
<nav class="barre-nav">
    <div class="nav-contenu">
        <a href="<?= APP_URL ?>/connexion.php" class="nav-logo">
            <img src="<?= APP_LOGO_URL ?>" alt="<?= htmlspecialchars(APP_NOM) ?>" class="nav-logo-img" width="56" height="56" decoding="async">
            <span class="nav-logo-texte"><?= APP_NOM ?></span>
        </a>
        <div class="nav-droite">
            <a href="<?= APP_URL ?>/connexion.php" class="btn btn-secondaire">Se connecter</a>
            <a href="<?= APP_URL ?>/inscription.php" class="btn btn-principal">S'inscrire</a>
        </div>
    </div>
</nav>
<?php endif; ?>
