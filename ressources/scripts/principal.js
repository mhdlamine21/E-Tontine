function toggleMenuProfil() {
  if (typeof window.fermerPanneauNotifications === "function") {
    window.fermerPanneauNotifications();
  }
  const menu = document.getElementById("menuProfil");
  if (menu) menu.classList.toggle("ouvert");
}

// Fermer le menu si clic ailleurs
document.addEventListener("click", function (e) {
  const nav = document.querySelector(".nav-utilisateur");
  if (nav && !nav.contains(e.target)) {
    const menu = document.getElementById("menuProfil");
    if (menu) menu.classList.remove("ouvert");
  }
});

// Voir/masquer mot de passe
function toggleMdp(id) {
  const champ = document.getElementById(id);
  if (!champ) return;
  champ.type = champ.type === "password" ? "text" : "password";
}

// Ouvre/ferme la sidebar en mobile.
function toggleMenuMobile() {
  const sidebar = document.getElementById("appSidebar");
  const backdrop = document.getElementById("appBackdrop");
  if (sidebar) sidebar.classList.toggle("ouvert-mobile");
  if (backdrop) backdrop.classList.toggle("ouvert");
}

// Menu secondaire « cette tontine » (mobile / étroit : repliable).
// Exposé explicitement pour les handlers inline et les tests.
window.toggleSousnavTontine = function toggleSousnavTontine() {
  const c = document.getElementById("sousnavTontineContainer");
  const btn = document.getElementById("sousnavTontineToggle");
  if (!c || !btn) return;
  const open = c.classList.toggle("sousnav-tontine-container--open");
  btn.setAttribute("aria-expanded", open ? "true" : "false");
};

function fermerSousnavTontineSiMobile() {
  if (!window.matchMedia("(max-width: 1024px)").matches) return;
  const c = document.getElementById("sousnavTontineContainer");
  const btn = document.getElementById("sousnavTontineToggle");
  if (c) c.classList.remove("sousnav-tontine-container--open");
  if (btn) btn.setAttribute("aria-expanded", "false");
}

function initSousnavTontineMobile() {
  const btn = document.getElementById("sousnavTontineToggle");
  if (!btn) return;
  btn.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    window.toggleSousnavTontine();
  });
}

// Fermeture automatique des alertes
document.addEventListener("DOMContentLoaded", function () {
  const alertes = document.querySelectorAll(".alerte");
  alertes.forEach(function (alerte) {
    // Ne pas fermer automatiquement les alertes erreur
    if (
      alerte.classList.contains("alerte-succes") ||
      alerte.classList.contains("alerte-info")
    ) {
      setTimeout(function () {
        alerte.style.transition = "opacity .5s";
        alerte.style.opacity = "0";
        setTimeout(function () {
          alerte.remove();
        }, 500);
      }, 5000);
    }
  });

  // Marquer les notifications comme lues au clic
  document
    .querySelectorAll(".notif-item a, .notif-item-complet a")
    .forEach(function (lien) {
      lien.addEventListener("click", function () {
        const li = this.closest(".notif-non-lue");
        if (li) li.classList.remove("notif-non-lue");
      });
    });

  // Confirmation avant suppression
  document.querySelectorAll("[data-confirmer]").forEach(function (el) {
    el.addEventListener("click", function (e) {
      const msg = this.dataset.confirmer;
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // Ferme la sidebar mobile après clic sur un lien.
  const urlActuelle = window.location.pathname;
  document
    .querySelectorAll(".nav-liens-principaux a, .sidebar-bas a")
    .forEach(function (lien) {
      lien.addEventListener("click", function () {
        const sidebar = document.getElementById("appSidebar");
        const backdrop = document.getElementById("appBackdrop");
        if (sidebar) sidebar.classList.remove("ouvert-mobile");
        if (backdrop) backdrop.classList.remove("ouvert");
      });
    });

  initSousnavTontineMobile();

  document
    .querySelectorAll("#sousnavTontinePanel .sousnav-tontine__lien")
    .forEach(function (lien) {
      lien.addEventListener("click", function () {
        fermerSousnavTontineSiMobile();
      });
    });

  window.addEventListener("resize", function () {
    if (window.matchMedia("(min-width: 1025px)").matches) {
      const c = document.getElementById("sousnavTontineContainer");
      const btn = document.getElementById("sousnavTontineToggle");
      if (c) c.classList.remove("sousnav-tontine-container--open");
      if (btn) btn.setAttribute("aria-expanded", "false");
    }
  });
});

// Copier texte
function copierTexte(texte) {
  navigator.clipboard
    .writeText(texte)
    .then(function () {
      afficherToast("Copié dans le presse-papiers !");
    })
    .catch(function () {
      afficherToast("Copie impossible. Copiez manuellement.", "erreur");
    });
}

// Toast
function afficherToast(message, type) {
  type = type || "succes";
  const toast = document.createElement("div");
  toast.style.cssText =
    "position:fixed;bottom:24px;right:24px;background:" +
    (type === "succes" ? "#1D9E75" : "#E24B4A") +
    ";color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2);";
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(function () {
    toast.style.transition = "opacity .4s";
    toast.style.opacity = "0";
    setTimeout(function () {
      toast.remove();
    }, 400);
  }, 2800);
}

// Formater montant côté client
function formaterMontant(montant) {
  return new Intl.NumberFormat("fr-FR").format(Math.round(montant)) + " FCFA";
}

// ============================================================
// GESTION DU GUIDE D'UTILISATION MODAL
// ============================================================
window.ouvrirGuide = function (ongletCible) {
  ongletCible = ongletCible || "creer";
  const modal = document.getElementById("guideModal");
  if (!modal) return;
  modal.classList.add("active");
  modal.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
  window.changerOngletGuide(ongletCible);
};

window.fermerGuide = function () {
  const modal = document.getElementById("guideModal");
  if (!modal) return;
  modal.classList.remove("active");
  modal.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";
};

window.changerOngletGuide = function (nomOnglet) {
  const modal = document.getElementById("guideModal");
  if (!modal) return;
  const onglets = modal.querySelectorAll("[data-guide-onglet]");
  const panneaux = modal.querySelectorAll("[data-guide-panneau]");

  onglets.forEach(function (btn) {
    const estActif = btn.getAttribute("data-guide-onglet") === nomOnglet;
    btn.classList.toggle("actif", estActif);
    btn.setAttribute("aria-selected", estActif ? "true" : "false");
  });

  panneaux.forEach(function (p) {
    const estActif = p.getAttribute("data-guide-panneau") === nomOnglet;
    p.classList.toggle("actif", estActif);
  });
};

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    const guide = document.getElementById("guideModal");
    if (guide && guide.classList.contains("active")) {
      window.fermerGuide();
    }
  }
});

