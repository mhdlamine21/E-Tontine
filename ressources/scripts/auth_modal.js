/**
 * E-Tontine - Gestion de la modale d'authentification rapide (Popup Connexion / Inscription)
 */
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('authModal');
    if (!modal) return;

    const dialog = modal.querySelector('.modal-auth__dialog');
    const onglets = modal.querySelectorAll('.modal-auth__onglet');
    const panneaux = modal.querySelectorAll('.modal-auth__panneau');
    const btnFermer = modal.querySelectorAll('[data-fermer-auth]');
    const declencheurs = document.querySelectorAll('[data-auth-mode]');

    // Fonction d'ouverture
    function ouvrirModal(mode) {
        mode = mode || 'connexion';
        basculerOnglet(mode);
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Focus sur le premier champ actif
        setTimeout(function () {
            const panneauActif = modal.querySelector('.modal-auth__panneau.actif');
            if (panneauActif) {
                const premierChamp = panneauActif.querySelector('input:not([type="hidden"])');
                if (premierChamp) premierChamp.focus();
            }
        }, 150);
    }

    // Fonction de fermeture
    function fermerModal() {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        // Réinitialiser les alertes
        const alertes = modal.querySelectorAll('.modal-auth__alertes');
        alertes.forEach(function (el) { el.innerHTML = ''; });
    }

    // Basculer entre Connexion et Inscription
    function basculerOnglet(mode) {
        onglets.forEach(function (btn) {
            const estActif = btn.getAttribute('data-onglet') === mode;
            btn.classList.toggle('actif', estActif);
            btn.setAttribute('aria-selected', estActif ? 'true' : 'false');
        });

        panneaux.forEach(function (panneau) {
            const estActif = panneau.getAttribute('data-panneau') === mode;
            panneau.classList.toggle('actif', estActif);
        });

        // Focus
        const panneauActif = modal.querySelector(`.modal-auth__panneau[data-panneau="${mode}"]`);
        if (panneauActif) {
            const premierChamp = panneauActif.querySelector('input:not([type="hidden"])');
            if (premierChamp) premierChamp.focus();
        }
    }

    // Écouteurs sur les boutons déclencheurs
    declencheurs.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            // Si l'utilisateur clique avec Ctrl ou clic molette, laisser le comportement par défaut
            if (e.ctrlKey || e.metaKey || e.button === 1) return;
            e.preventDefault();
            const mode = btn.getAttribute('data-auth-mode') || 'connexion';
            ouvrirModal(mode);
        });
    });

    // Clics sur les onglets de la modale
    onglets.forEach(function (onglet) {
        onglet.addEventListener('click', function () {
            const mode = onglet.getAttribute('data-onglet');
            basculerOnglet(mode);
        });
    });

    // Liens internes pour basculer (ex: "Pas encore de compte ? S'inscrire")
    const liensBascule = modal.querySelectorAll('[data-basculer-auth]');
    liensBascule.forEach(function (lien) {
        lien.addEventListener('click', function (e) {
            e.preventDefault();
            const mode = lien.getAttribute('data-basculer-auth');
            basculerOnglet(mode);
        });
    });

    // Fermeture de la modale
    btnFermer.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            fermerModal();
        });
    });

    // Fermer avec la touche Echap
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            fermerModal();
        }
    });

    // Empêcher la fermeture si on clique à l'intérieur du dialogue
    if (dialog) {
        dialog.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    // Basculer la visibilité du mot de passe (icône oeil)
    const btnYeux = modal.querySelectorAll('.modal-auth__oeil-btn');
    btnYeux.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const champ = btn.closest('.modal-auth__champ-pwd').querySelector('input');
            if (!champ) return;
            const estMdp = champ.type === 'password';
            champ.type = estMdp ? 'text' : 'password';
            btn.setAttribute('aria-label', estMdp ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            btn.innerHTML = estMdp
                ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`
                : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        });
    });

    // Gestion soumission AJAX générique
    function gererSoumission(formulaire, urlAction, zoneAlerte, boutonSoumettre, texteBoutonNormal) {
        formulaire.addEventListener('submit', function (e) {
            e.preventDefault();
            zoneAlerte.innerHTML = '';

            // Activer l'état de chargement
            boutonSoumettre.disabled = true;
            boutonSoumettre.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="icone-chargement" style="animation: spin 0.8s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1"></path>
                </svg>
                <span>Traitement en cours...</span>
            `;

            const formData = new FormData(formulaire);
            formData.append('ajax', '1');

            fetch(urlAction, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(function (res) {
                return res.json().catch(function () {
                    throw new Error('Réponse serveur non reconnue.');
                });
            })
            .then(function (data) {
                if (data.succes) {
                    zoneAlerte.innerHTML = `<div class="alerte alerte-succes">${data.message || 'Succès ! Redirection en cours...'}</div>`;
                    boutonSoumettre.innerHTML = `<span>✓ Redirection...</span>`;
                    setTimeout(function () {
                        window.location.href = data.redirection || './pages/tableau_de_bord/accueil.php';
                    }, 400);
                } else {
                    boutonSoumettre.disabled = false;
                    boutonSoumettre.innerHTML = texteBoutonNormal;
                    const erreurs = data.erreurs || [data.message || 'Une erreur est survenue.'];
                    zoneAlerte.innerHTML = erreurs.map(function (err) {
                        return `<div class="alerte alerte-danger">${err}</div>`;
                    }).join('');
                }
            })
            .catch(function (err) {
                boutonSoumettre.disabled = false;
                boutonSoumettre.innerHTML = texteBoutonNormal;
                zoneAlerte.innerHTML = `<div class="alerte alerte-danger">Impossible de contacter le serveur. Veuillez vérifier votre connexion.</div>`;
            });
        });
    }

    // Initialisation formulaire connexion
    const formConnexion = document.getElementById('formAuthConnexion');
    if (formConnexion) {
        const zoneAlerteConn = formConnexion.querySelector('.modal-auth__alertes');
        const btnConn = formConnexion.querySelector('.modal-auth__btn-soumettre');
        gererSoumission(formConnexion, formConnexion.action, zoneAlerteConn, btnConn, '<span>Se connecter</span>');
    }

    // Initialisation formulaire inscription
    const formInscription = document.getElementById('formAuthInscription');
    if (formInscription) {
        const zoneAlerteInsc = formInscription.querySelector('.modal-auth__alertes');
        const btnInsc = formInscription.querySelector('.modal-auth__btn-soumettre');
        gererSoumission(formInscription, formInscription.action, zoneAlerteInsc, btnInsc, '<span>Créer mon compte</span>');
    }

    // Ouvrir automatiquement si paramètre URL présent (?auth=connexion ou ?auth=inscription)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('auth')) {
        const authParam = urlParams.get('auth');
        if (authParam === 'inscription' || authParam === 'connexion') {
            ouvrirModal(authParam);
        }
    }
});
