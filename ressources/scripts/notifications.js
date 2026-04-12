// Compteur et panneau de notifications

(function () {
  if (!window.APP_URL) return;

  var btn = document.getElementById("btnNotifPanel");
  var panel = document.getElementById("panneauNotifs");
  var liste = document.getElementById("listeNotifsPanel");

  window.fermerPanneauNotifications = function () {
    if (!panel || !btn) return;
    panel.hidden = true;
    panel.classList.remove("nav-notif-panel--ouvert");
    btn.setAttribute("aria-expanded", "false");
  };

  function majCompteurSurBtn(nb) {
    var wrap = document.querySelector(".nav-notif-btn");
    if (!wrap) return;
    var compteur = wrap.querySelector(".nav-notif-compteur");
    if (nb > 0) {
      var txt = nb > 99 ? "99+" : String(nb);
      if (!compteur) {
        compteur = document.createElement("span");
        compteur.className = "nav-notif-compteur";
        wrap.appendChild(compteur);
      }
      compteur.textContent = txt;
      compteur.style.display = "";
    } else if (compteur) {
      compteur.style.display = "none";
    }
  }

  function actualiserCompteur() {
    fetch(window.APP_URL + "/pages/notifications/compteur.php", {
      method: "GET",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (typeof data.nb !== "undefined") {
          majCompteurSurBtn(parseInt(data.nb, 10));
        }
      })
      .catch(function () {});
  }

  function marquerLue(id, cb) {
    if (!window.CSRF_TOKEN || !id) {
      if (cb) cb();
      return;
    }
    var fd = new FormData();
    fd.append("csrf_token", window.CSRF_TOKEN);
    fd.append("id", String(id));
    fetch(window.APP_URL + "/pages/notifications/marquer_lue_ajax.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: fd,
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (data && typeof data.nb !== "undefined") {
          majCompteurSurBtn(parseInt(data.nb, 10));
        }
        rafraichirListePanel();
        if (cb) cb();
      })
      .catch(function () {
        if (cb) cb();
      });
  }

  function echapper(t) {
    var d = document.createElement("div");
    d.textContent = t;
    return d.innerHTML;
  }

  function escAttr(t) {
    return String(t)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;");
  }

  function rendreListe(items) {
    if (!liste) return;
    if (!items || !items.length) {
      liste.innerHTML =
        '<li class="nav-notif-panel__vide">Aucune notification.</li>';
      return;
    }
    var html = "";
    for (var i = 0; i < items.length; i++) {
      var it = items[i];
      var nonlue = it.lu ? "" : " nav-notif-panel__item--nonlue";
      var titre = echapper(it.titre || "");
      var msg = echapper(it.message || "");
      var date = echapper(it.date || "");
      var lien = it.lien || "";
      html +=
        '<li class="nav-notif-panel__item' +
        nonlue +
        '" data-notif-id="' +
        it.id +
        '">';
      if (lien) {
        html +=
          '<a href="' + escAttr(lien) + '" class="nav-notif-panel__lien">';
      } else {
        html +=
          '<div class="nav-notif-panel__lien nav-notif-panel__lien--sanslien">';
      }
      html += '<span class="nav-notif-panel__titre">' + titre + "</span>";
      html += '<span class="nav-notif-panel__msg">' + msg + "</span>";
      html += '<span class="nav-notif-panel__date">' + date + "</span>";
      html += lien ? "</a>" : "</div>";
      html += "</li>";
    }
    liste.innerHTML = html;
    attacherClicsLiens();
  }

  function rafraichirListePanel() {
    if (!liste) return;
    fetch(window.APP_URL + "/pages/notifications/panel_json.php?limite=15", {
      method: "GET",
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (data && data.items) {
          rendreListe(data.items);
        }
        if (data && typeof data.nb !== "undefined") {
          majCompteurSurBtn(parseInt(data.nb, 10));
        }
      })
      .catch(function () {});
  }

  function attacherClicsLiens() {
    if (!liste) return;
    liste
      .querySelectorAll(".nav-notif-panel__lien[href]")
      .forEach(function (a) {
        a.addEventListener("click", function () {
          var li = a.closest("[data-notif-id]");
          if (li) {
            var id = parseInt(li.getAttribute("data-notif-id"), 10);
            li.classList.remove("nav-notif-panel__item--nonlue");
            marquerLue(id, null);
          }
        });
      });
    liste
      .querySelectorAll(".nav-notif-panel__lien--sanslien")
      .forEach(function (div) {
        div.addEventListener("click", function () {
          var li = div.closest("[data-notif-id]");
          if (!li) return;
          var id = parseInt(li.getAttribute("data-notif-id"), 10);
          li.classList.remove("nav-notif-panel__item--nonlue");
          marquerLue(id, null);
        });
      });
  }

  if (btn && panel) {
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      var ouvrir = panel.hidden;
      if (ouvrir) {
        panel.hidden = false;
        panel.classList.add("nav-notif-panel--ouvert");
        btn.setAttribute("aria-expanded", "true");
        rafraichirListePanel();
      } else {
        window.fermerPanneauNotifications();
      }
    });

    document.addEventListener("click", function (e) {
      var wrap = document.querySelector(".nav-notif-wrap");
      if (wrap && !wrap.contains(e.target)) {
        window.fermerPanneauNotifications();
      }
    });
  }

  attacherClicsLiens();

  actualiserCompteur();

  function planifier() {
    window.setTimeout(function () {
      if (!document.hidden) actualiserCompteur();
      planifier();
    }, 60000);
  }
  planifier();

  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) actualiserCompteur();
  });
})();
