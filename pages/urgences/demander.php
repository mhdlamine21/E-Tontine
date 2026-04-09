<?php
//  URGENCES - FAIRE UNE DEMANDE
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../configuration/base_de_donnees.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/urgence.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();

$tontine = obtenirTontine($tontineId);
if (!$tontine || !estMembreDeTontine($utilisateurId, $tontineId)) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}
if (!tontineAccepteOperations($tontine)) {
    redirigerAvecMessage(APP_URL . '/pages/tontines/voir.php?id=' . $tontineId, 'erreur', 'Les demandes d’urgence ne sont pas possibles tant que la tontine n’est pas en cours.');
}

// Vérifier demande déjà en cours
$bd  = connexionBD();
$req = $bd->prepare("SELECT id FROM demandes_urgence WHERE tontine_id = ? AND demandeur_id = ? AND statut = 'en_attente'");
$req->execute([$tontineId, $utilisateurId]);
$demandeEnCours = $req->fetch();

$mesUrgences = listerUrgences($tontineId);
$mesUrgences = array_filter($mesUrgences, fn($u) => (int)$u['demandeur_id'] === $utilisateurId);

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierCsrf()) {
    $motif = nettoyer($_POST['motif'] ?? '');
    if (strlen($motif) < 20) {
        $erreurs[] = 'Veuillez détailler votre motif (au moins 20 caractères).';
    } else {
        $res = creerDemandeUrgence($tontineId, $utilisateurId, $motif);
        redirigerAvecMessage(
            APP_URL . '/pages/urgences/demander.php?tontine=' . $tontineId,
            $res['succes'] ? 'succes' : 'erreur',
            $res['message']
        );
    }
}

$titrePage = 'Demande d\'urgence - ' . $tontine['nom'];
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="lien-retour">← Retour</a>
        <h1>Demande d'urgence</h1>
        <p class="texte-secondaire"><?= htmlspecialchars($tontine['nom']) ?></p>
    </div>
</div>

<div class="grille-deux-colonnes">

    <!-- Formulaire -->
    <div>
        <section class="section-tableau">
            <div class="section-entete"><h2>Nouvelle demande</h2></div>

            <div class="banniere-guide-page" style="margin-bottom:16px;">
                <div class="banniere-guide-page__icone">🚨</div>
                <div class="banniere-guide-page__contenu">
                    <h2 class="banniere-guide-page__titre">Fonctionnement de l'aide d'urgence</h2>
                    <p class="banniere-guide-page__texte">En cas de force majeure, la solidarité E-Tontine vous permet d’avancer votre tour de cagnotte :</p>
                    <ul class="banniere-guide-page__etapes">
                        <li><strong>Motifs légitimes :</strong> Frais hospitaliers, urgence familiale ou imprévu majeur justifié.</li>
                        <li><strong>Échange de tour :</strong> Si acceptée, votre tour est interverti avec le bénéficiaire prévu ou le tour suivant.</li>
                        <li><strong>Examen impartial :</strong> L’administrateur étudie votre demande et notifie l'ensemble des membres.</li>
                    </ul>
                    <button type="button" class="banniere-guide-page__lien" onclick="ouvrirGuide('urgence')">
                        Voir les conditions détaillées des urgences &rarr;
                    </button>
                </div>
            </div>

            <?php if ($demandeEnCours): ?>
            <div class="alerte alerte-alerte">
                Vous avez déjà une demande d'urgence en attente d'examen.
            </div>
            <?php else: ?>

            <?php foreach ($erreurs as $err): ?>
            <div class="alerte alerte-danger"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>

            <form method="POST" action="">
                <?= champCsrf() ?>
                <div class="champ">
                    <label for="motif">Motif de la demande <span class="obligatoire">*</span></label>
                    <textarea id="motif" name="motif" rows="5" required
                              placeholder="Décrivez votre situation d'urgence (urgence médicale, problème familial, imprévu financier...)&#10;Plus votre description est précise, plus l'admin pourra traiter rapidement."><?= htmlspecialchars($_POST['motif'] ?? '') ?></textarea>
                    <small class="texte-secondaire">Minimum 20 caractères. Soyez précis pour faciliter la décision de l'admin.</small>
                </div>
                <div class="actions-formulaire">
                    <a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $tontineId ?>" class="btn btn-secondaire">Annuler</a>
                    <button type="submit" class="btn btn-principal">Soumettre la demande</button>
                </div>
            </form>
            <?php endif; ?>
        </section>
    </div>

    <!-- Historique de mes demandes -->
    <div>
        <section class="section-tableau">
            <div class="section-entete"><h2>Mes demandes passées</h2></div>
            <?php if (empty($mesUrgences)): ?>
            <div class="etat-vide"><p>Aucune demande précédente.</p></div>
            <?php else: ?>
            <?php foreach ($mesUrgences as $u): ?>
            <div class="carte-urgence">
                <div class="carte-urgence-entete">
                    <span><?= formaterDate($u['date_demande']) ?></span>
                    <?= badgeStatut($u['statut']) ?>
                </div>
                <p class="carte-urgence-motif"><?= htmlspecialchars(tronquer($u['motif'], 120)) ?></p>
                <?php if ($u['commentaire_admin']): ?>
                <div class="carte-urgence-reponse">
                    <strong>Réponse admin :</strong> <?= htmlspecialchars($u['commentaire_admin']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

</div>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>
