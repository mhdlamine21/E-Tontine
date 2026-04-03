<?php
declare(strict_types=1);

require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../configuration/session.php';
require_once __DIR__ . '/../fonctions/aide.php';

$csrfToken = genererCsrf();
?>
<!-- MODALE POPUP D'AUTHENTIFICATION (CONNEXION & INSCRIPTION) -->
<div id="authModal" class="modal-auth" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="authModalTitre">
    <div class="modal-auth__backdrop" data-fermer-auth></div>
    <div class="modal-auth__dialog">
        <button type="button" class="modal-auth__fermer" data-fermer-auth aria-label="Fermer la boîte de dialogue">✕</button>

        <div class="modal-auth__entete">
            <img src="<?= APP_EMBLEM_URL ?>" alt="<?= htmlspecialchars(APP_NOM) ?>" class="modal-auth__logo-img" width="52" height="52" decoding="async">
            <h2 id="authModalTitre" class="modal-auth__titre"><?= APP_NOM ?></h2>
            <p class="modal-auth__soustitre">La plateforme de tontine moderne et sécurisée</p>
        </div>

        <div class="modal-auth__onglets" role="tablist">
            <button type="button" class="modal-auth__onglet actif" data-onglet="connexion" role="tab" aria-selected="true" aria-controls="panneauConnexion">
                Se connecter
            </button>
            <button type="button" class="modal-auth__onglet" data-onglet="inscription" role="tab" aria-selected="false" aria-controls="panneauInscription">
                Créer un compte
            </button>
        </div>

        <!-- PANNEAU CONNEXION -->
        <div id="panneauConnexion" class="modal-auth__panneau actif" data-panneau="connexion" role="tabpanel">
            <form id="formAuthConnexion" action="<?= APP_URL ?>/connexion.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="modal-auth__alertes" aria-live="polite"></div>

                <div class="modal-auth__champ">
                    <label for="auth_conn_email">Adresse email</label>
                    <input type="email" id="auth_conn_email" name="email" placeholder="votre.email@exemple.com" required autocomplete="email">
                </div>

                <div class="modal-auth__champ">
                    <label for="auth_conn_password">Mot de passe</label>
                    <div class="modal-auth__champ-pwd">
                        <input type="password" id="auth_conn_password" name="mot_de_passe" placeholder="Votre mot de passe" required autocomplete="current-password">
                        <button type="button" class="modal-auth__oeil-btn" aria-label="Afficher le mot de passe">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="modal-auth__options">
                    <a href="<?= APP_URL ?>/mot_de_passe_oublie.php" class="modal-auth__lien-mdp">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="modal-auth__btn-soumettre">
                    <span>Se connecter</span>
                </button>

                <div class="modal-auth__bas">
                    <span>Nouveau sur <?= APP_NOM ?> ?</span>
                    <a href="#" data-basculer-auth="inscription">Créer un compte gratuitement</a>
                </div>
            </form>
        </div>

        <!-- PANNEAU INSCRIPTION -->
        <div id="panneauInscription" class="modal-auth__panneau" data-panneau="inscription" role="tabpanel">
            <form id="formAuthInscription" action="<?= APP_URL ?>/inscription.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="modal-auth__alertes" aria-live="polite"></div>

                <div class="modal-auth__champ">
                    <label for="auth_insc_nom">Nom complet</label>
                    <input type="text" id="auth_insc_nom" name="nom" placeholder="Ex: Fatou Ndiaye" required autocomplete="name">
                </div>

                <div class="modal-auth__champ">
                    <label for="auth_insc_email">Adresse email</label>
                    <input type="email" id="auth_insc_email" name="email" placeholder="votre.email@exemple.com" required autocomplete="email">
                </div>

                <div class="modal-auth__champ">
                    <label for="auth_insc_tel">Téléphone (Wave / OM)</label>
                    <input type="tel" id="auth_insc_tel" name="telephone" placeholder="Ex: 77 123 45 67" required autocomplete="tel">
                </div>

                <div class="modal-auth__champ">
                    <label for="auth_insc_pwd">Mot de passe (8 caractères minimum)</label>
                    <div class="modal-auth__champ-pwd">
                        <input type="password" id="auth_insc_pwd" name="mot_de_passe" minlength="8" placeholder="••••••••" required autocomplete="new-password">
                        <button type="button" class="modal-auth__oeil-btn" aria-label="Afficher le mot de passe">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="modal-auth__champ">
                    <label for="auth_insc_conf">Confirmez le mot de passe</label>
                    <div class="modal-auth__champ-pwd">
                        <input type="password" id="auth_insc_conf" name="confirmation" minlength="8" placeholder="••••••••" required autocomplete="new-password">
                        <button type="button" class="modal-auth__oeil-btn" aria-label="Afficher le mot de passe">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="modal-auth__btn-soumettre">
                    <span>Créer mon compte</span>
                </button>

                <div class="modal-auth__bas">
                    <span>Déjà inscrit ?</span>
                    <a href="#" data-basculer-auth="connexion">Se connecter à mon compte</a>
                </div>
            </form>
        </div>
    </div>
</div>
