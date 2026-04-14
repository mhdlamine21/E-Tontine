<?php
declare(strict_types=1);
?>
<!-- MODALE INTERACTIVE : GUIDE D'UTILISATION & AIDE -->
<div id="guideModal" class="modal-auth modal-guide" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="guideModalTitre">
    <div class="modal-auth__backdrop" onclick="fermerGuide()"></div>
    <div class="modal-auth__dialog modal-guide__dialog">
        <button type="button" class="modal-auth__fermer" onclick="fermerGuide()" aria-label="Fermer le guide">✕</button>

        <div class="modal-auth__entete">
            <div class="modal-guide__badge-icon">📖</div>
            <h2 id="guideModalTitre" class="modal-auth__titre">Guide d'utilisation & Aide</h2>
            <p class="modal-auth__soustitre">Apprenez à maîtriser toutes les fonctionnalités de votre plateforme E-Tontine</p>
        </div>

        <div class="modal-guide__onglets" role="tablist">
            <button type="button" class="modal-guide__onglet actif" data-guide-onglet="creer" onclick="changerOngletGuide('creer')">
                🚀 Créer une tontine
            </button>
            <button type="button" class="modal-guide__onglet" data-guide-onglet="rejoindre" onclick="changerOngletGuide('rejoindre')">
                🤝 Rejoindre un groupe
            </button>
            <button type="button" class="modal-guide__onglet" data-guide-onglet="urgence" onclick="changerOngletGuide('urgence')">
                ⚡ Demande d'urgence
            </button>
            <button type="button" class="modal-guide__onglet" data-guide-onglet="vote" onclick="changerOngletGuide('vote')">
                🗳️ Pétition & Vote
            </button>
            <button type="button" class="modal-guide__onglet" data-guide-onglet="cotisation" onclick="changerOngletGuide('cotisation')">
                💰 Cotiser & Tours
            </button>
        </div>

        <!-- SECTION 1 : CRÉER UNE TONTINE -->
        <div class="modal-guide__panneau actif" data-guide-panneau="creer">
            <div class="guide-carte">
                <h3>1. Définir les paramètres financiers</h3>
                <p>En tant qu'initiateur, vous choisissez :</p>
                <ul>
                    <li><strong>Le nom & description</strong> : donnez un nom clair (ex: <em>Tontine Solidarité Famille</em>).</li>
                    <li><strong>Le montant de la cotisation</strong> : fixe pour chaque membre (ex: 10 000 FCFA).</li>
                    <li><strong>La fréquence</strong> : hebdomadaire, mensuelle ou trimestrielle avec jour d'échéance précis.</li>
                    <li><strong>Le nombre maximum de membres</strong> : la tontine démarre dès que l'effectif est atteint.</li>
                </ul>
            </div>
            <div class="guide-carte">
                <h3>2. Ordre des tours et tirage au sort</h3>
                <p>Chaque membre reçoit la cagnotte intégrale à son tour. Deux modes d'attribution :</p>
                <ul>
                    <li><strong>Tirage au sort automatique</strong> : aléatoire, impartial et transparent pour tous.</li>
                    <li><strong>Attribution manuelle</strong> : l'administrateur définit l'ordre en accord avec les membres.</li>
                </ul>
            </div>
            <div class="guide-carte">
                <h3>3. Règles d'amendes et discipline financière</h3>
                <p>Activez les amendes de retard avec un délai de grâce (ex: 2 jours). Passé ce délai, une pénalité par jour de retard est automatiquement calculée pour préserver la confiance du groupe.</p>
            </div>
            <div class="guide-actions">
                <a href="<?= APP_URL ?>/pages/tontines/creer.php" class="btn btn-principal btn-petit">+ Créer une tontine maintenant</a>
            </div>
        </div>

        <!-- SECTION 2 : REJOINDRE UN GROUPE -->
        <div class="modal-guide__panneau" data-guide-panneau="rejoindre">
            <div class="guide-carte">
                <h3>1. Comment recevoir une invitation ?</h3>
                <p>L'administrateur de la tontine peut vous inviter de deux manières :</p>
                <ul>
                    <li><strong>Par e-mail</strong> : vous recevez un lien direct d'adhésion sécurisé.</li>
                    <li><strong>Par lien d'invitation WhatsApp</strong> : un lien contenant un jeton d'invitation unique (ex: <code>?jeton=ABC123XYZ</code>).</li>
                </ul>
            </div>
            <div class="guide-carte">
                <h3>2. Valider son entrée dans la tontine</h3>
                <p>En cliquant sur le lien, vous consultez les détails de la tontine (montant, fréquence, participants actuels). Cliquez sur <strong>« Confirmer mon adhésion »</strong> pour intégrer le groupe.</p>
            </div>
            <div class="guide-carte">
                <h3>3. Statut et engagement</h3>
                <p>Chaque membre possède un <strong>score de fiabilité (sur 100)</strong>. Plus vous payez vos cotisations à temps, plus votre score est exemplaire !</p>
            </div>
            <div class="guide-actions">
                <a href="<?= APP_URL ?>/pages/tontines/mes_adhesions.php" class="btn btn-secondaire btn-petit">Voir mes adhésions</a>
            </div>
        </div>

        <!-- SECTION 3 : DEMANDER UNE URGENCE -->
        <div class="modal-guide__panneau" data-guide-panneau="urgence">
            <div class="guide-carte">
                <h3>1. Qu'est-ce qu'une demande d'urgence ?</h3>
                <p>En cas d'imprévu majeur (frais médicaux, hospitalisation, événement familial imprévu), la tontine moderne permet à un membre de solliciter la cagnotte avant son tour normal sans pénaliser les autres.</p>
            </div>
            <div class="guide-carte">
                <h3>2. Comment formuler la demande ?</h3>
                <ul>
                    <li>Rendez-vous dans la page de votre tontine, section <strong>Urgences</strong>.</li>
                    <li>Cliquez sur <strong>« Demander une urgence »</strong>.</li>
                    <li>Rédigez un motif précis expliquant votre situation.</li>
                </ul>
            </div>
            <div class="guide-carte">
                <h3>3. Examen et validation par l'administrateur</h3>
                <p>L'administrateur reçoit une notification immédiate et peut :</p>
                <ul>
                    <li><strong>Valider avec priorité au prochain tour</strong> : le demandeur recevra la cagnotte au cycle suivant.</li>
                    <li><strong>Valider avec paiement immédiat</strong> : si la cagnotte actuelle est suffisante.</li>
                    <li><strong>Refuser avec justification</strong> si les conditions ne sont pas réunies.</li>
                </ul>
            </div>
        </div>

        <!-- SECTION 4 : PÉTITION & VOTE -->
        <div class="modal-guide__panneau" data-guide-panneau="vote">
            <div class="guide-carte">
                <h3>1. Démocratie et transparence (Anti-abus)</h3>
                <p>Si un administrateur ne respecte pas ses engagements, est inactif ou bloque les fonds, les membres ont le pouvoir démocratique de le révoquer.</p>
            </div>
            <div class="guide-carte">
                <h3>2. Étape 1 : Lancer et signer la pétition</h3>
                <ul>
                    <li>Un membre actif lance une <strong>pétition de destitution</strong> avec un motif motivé et propose un remplaçant.</li>
                    <li>Les membres signent la pétition en un clic.</li>
                    <li><strong>Quorum requis</strong> : la pétition doit recueillir au moins 50% de signatures de membres actifs.</li>
                </ul>
            </div>
            <div class="guide-carte">
                <h3>3. Étape 2 : Le vote démocratique OUI / NON</h3>
                <p>Dès que le seuil est franchi, un <strong>vote officiel</strong> s'ouvre pour tous les membres pendant un délai imparti. Chaque membre vote <strong>OUI</strong> ou <strong>NON</strong> en toute sécurité.</p>
            </div>
            <div class="guide-carte">
                <h3>4. Résultat automatique</h3>
                <p>Si le vote <strong>OUI</strong> l'emporte à la majorité absolue, le système transfère immédiatement les privilèges administratifs au nouvel administrateur élu !</p>
            </div>
        </div>

        <!-- SECTION 5 : COTISER & TOURS -->
        <div class="modal-guide__panneau" data-guide-panneau="cotisation">
            <div class="guide-carte">
                <h3>1. Enregistrer un paiement</h3>
                <p>À chaque échéance, vous pouvez cotiser directement via :</p>
                <ul>
                    <li><strong>Wave</strong> (avec saisie du numéro de transaction).</li>
                    <li><strong>Orange Money</strong> (avec référence de paiement).</li>
                    <li><strong>Espèces / Remise directe</strong> (validée par l'administrateur).</li>
                </ul>
            </div>
            <div class="guide-carte">
                <h3>2. Paiement pour autrui</h3>
                <p>Vous pouvez régler la part d'un proche ou d'un collègue membre de la même tontine en cochant <em>« Paiement pour un autre membre »</em>.</p>
            </div>
            <div class="guide-carte">
                <h3>3. Tours complets & Réception des gains</h3>
                <p>Lorsque chaque membre a reçu sa cagnotte, le cycle du tour est complet. L'administrateur peut alors décider avec le groupe de :</p>
                <ul>
                    <li><strong>Lancer un nouveau tour</strong> : avec nouveau tirage au sort des positions.</li>
                    <li><strong>Clôturer la tontine</strong> : avec bilan comptable complet téléchargeable en PDF.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
