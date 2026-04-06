document.addEventListener("DOMContentLoaded", function () {
  const formes = document.querySelectorAll(
    '#formePaiement, form[data-type="paiement"]',
  );

  formes.forEach(function (forme) {
    forme.addEventListener("submit", function (e) {
      const btnPayer = forme.querySelector('#btnPayer, button[type="submit"]');
      const modeSelectionne = forme.querySelector('input[name="mode"]:checked');

      if (!modeSelectionne || modeSelectionne.value === "especes") return;

      const telephone = forme.querySelector("#telephone");
      if (telephone && !validerTelephone(telephone.value)) {
        e.preventDefault();
        afficherErreurPaiement(
          "Numéro de téléphone invalide. Format attendu : 77 123 45 67",
        );
        return;
      }

      if (btnPayer) {
        const mode = modeSelectionne ? modeSelectionne.value : "wave";
        btnPayer.innerHTML = getAnimationChargement(mode);
        btnPayer.disabled = true;
        btnPayer.style.opacity = ".75";
      }
    });
  });

  // Validation en temps réel du numéro
  const champTel = document.getElementById("telephone");
  if (champTel) {
    champTel.addEventListener("input", function () {
      const valeur = this.value.replace(/\s/g, "");
      if (valeur.length > 0) {
        this.style.borderColor = validerTelephone(this.value)
          ? "#1D9E75"
          : "#E24B4A";
      } else {
        this.style.borderColor = "";
      }
    });
  }
});

// Valider numéro sénégalais
function validerTelephone(tel) {
  const nettoye = tel.replace(/[\s\-\.]/g, "");
  return /^(\+221)?7[0-9]{8}$/.test(nettoye);
}

// Animation de chargement
function getAnimationChargement(mode) {
  const labels = {
    wave: "Connexion Wave...",
    orange_money: "Connexion Orange Money...",
    especes: "Enregistrement...",
  };
  const label = labels[mode] || "Traitement...";
  return `<span style="display:inline-flex;align-items:center;gap:8px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             style="animation:tourner .8s linear infinite">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/>
        </svg>
        ${label}
    </span>`;
}

// Ajouter animation CSS
const styleAnim = document.createElement("style");
styleAnim.textContent =
  "@keyframes tourner { to { transform: rotate(360deg); } }";
document.head.appendChild(styleAnim);

// Afficher erreur paiement
function afficherErreurPaiement(message) {
  let boite = document.getElementById("erreurPaiement");
  if (!boite) {
    boite = document.createElement("div");
    boite.id = "erreurPaiement";
    boite.className = "alerte alerte-danger";
    boite.style.marginTop = "10px";
    const forme = document.querySelector(
      '#formePaiement, form[data-type="paiement"]',
    );
    if (forme) forme.prepend(boite);
  }
  boite.textContent = message;
  boite.scrollIntoView({ behavior: "smooth", block: "nearest" });
  setTimeout(function () {
    boite.remove();
  }, 5000);
}
