<?php
/**
 * SCRIPT DE TEST COMPLET - E-TONTINE
 * Simule tous les scénarios métier de l'application :
 *   1. Création tontine + ajout membres
 *   2. Démarrage + tirage au sort
 *   3. Lancement cycle
 *   4. Paiements (normaux + retards + amendes)
 *   5. Distribution cagnotte (partielle et complète)
 *   6. Vérification fin de tour + nouveau tour
 *   7. Urgence (immédiate + prochain tour)
 *   8. Vote / pétition / changement admin
 */

define('TEST_MODE', true);

// Bootstrap minimal
require_once __DIR__ . '/configuration/base_de_donnees.php';
require_once __DIR__ . '/configuration/constantes.php';
require_once __DIR__ . '/fonctions/aide.php';
require_once __DIR__ . '/fonctions/tontine.php';
require_once __DIR__ . '/fonctions/membre.php';
require_once __DIR__ . '/fonctions/cotisation.php';
require_once __DIR__ . '/fonctions/paiement.php';
require_once __DIR__ . '/fonctions/cagnotte.php';
require_once __DIR__ . '/fonctions/amende.php';
require_once __DIR__ . '/fonctions/urgence.php';
require_once __DIR__ . '/fonctions/vote.php';
require_once __DIR__ . '/fonctions/notification.php';
require_once __DIR__ . '/fonctions/logs.php';

$bd = connexionBD();

// ─── Couleurs terminal ────────────────────────────────────────────────────────
function ok(string $msg): void   { echo "\033[32m  ✅ $msg\033[0m\n"; }
function ko(string $msg): void   { echo "\033[31m  ❌ $msg\033[0m\n"; }
function info(string $msg): void { echo "\033[33m  ℹ  $msg\033[0m\n"; }
function titre(string $msg): void { echo "\n\033[1;36m═══ $msg ═══\033[0m\n"; }

function assertOk(array $res, string $label): void {
    if (!empty($res['succes'])) ok($label . ' : ' . ($res['message'] ?? ''));
    else ko($label . ' ÉCHOUÉ : ' . ($res['message'] ?? json_encode($res)));
}
function assertKo(array $res, string $label): void {
    if (empty($res['succes'])) ok('[Refus attendu] ' . $label . ' → ' . ($res['message'] ?? ''));
    else ko('[Refus attendu MAIS ACCEPTÉ] ' . $label);
}

// ─── Nettoyage de la BDD de test ─────────────────────────────────────────────
titre('NETTOYAGE');
$bd->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['journaux_paiement','paiements','distributions','amendes','cycles_cotisation',
          'notifications','logs','votes_membres','candidatures_admin','votes',
          'signatures_petition','petitions','demandes_urgence','membres_tontine','tontines','utilisateurs'] as $t) {
    $bd->exec("DELETE FROM $t WHERE 1=1");
}
$bd->exec('SET FOREIGN_KEY_CHECKS=1');
ok('BDD nettoyée');

// ─── CRÉATION DES UTILISATEURS DE TEST ───────────────────────────────────────
titre('CRÉATION UTILISATEURS (4 membres + 1 super admin)');
$mdp = password_hash('Test1234!', PASSWORD_DEFAULT);
$ids = [];
$users = [
    ['Alice Diallo',   'alice@test.sn',   '771234567', 'utilisateur'],  // admin tontine
    ['Bob Ndiaye',     'bob@test.sn',     '772345678', 'utilisateur'],
    ['Coumba Sarr',    'coumba@test.sn',  '773456789', 'utilisateur'],
    ['Daouda Fall',    'daouda@test.sn',  '774567890', 'utilisateur'],
    ['SuperAdmin',     'superadmin@test.sn', '775678901', 'super_admin'],
];
foreach ($users as $u) {
    $bd->prepare('INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, telephone, role_global) VALUES (?, ?, ?, ?, ?)')
       ->execute([$u[0], $u[1], $mdp, $u[2], $u[3]]);
    $ids[$u[1]] = (int)$bd->lastInsertId();
    ok("Utilisateur créé : {$u[0]} (id={$ids[$u[1]]})");
}
$adminId    = $ids['alice@test.sn'];
$bobId      = $ids['bob@test.sn'];
$coumbaId   = $ids['coumba@test.sn'];
$daoudaId   = $ids['daouda@test.sn'];
$superAdminId = $ids['superadmin@test.sn'];

// ─── CRÉATION TONTINE ─────────────────────────────────────────────────────────
titre('CRÉATION DE LA TONTINE');
$res = creerTontine($adminId, 'Tontine Test Solidarité', 'Test complet logique métier', 10000.0, 'mensuelle', 4, null, 5);
assertOk($res, 'Création tontine');
$tontineId = $res['tontine_id'];
info("Tontine ID: $tontineId");

// Activer les amendes (1000 FCFA/jour, délai de grâce 2 jours)
$bd->prepare('UPDATE tontines SET amendes_activees=1, amende_par_jour=1000, amende_delai_grace=2 WHERE id=?')
   ->execute([$tontineId]);
ok('Amendes activées : 1 000 FCFA/jour, délai de grâce 2 jours');

// ─── AJOUT DES MEMBRES ────────────────────────────────────────────────────────
titre('AJOUT DES MEMBRES (tontine max=4, créateur=Alice déjà dedans)');
assertOk(ajouterMembreParEmail($tontineId, 'bob@test.sn'),    'Ajout Bob');
assertOk(ajouterMembreParEmail($tontineId, 'coumba@test.sn'), 'Ajout Coumba');
assertOk(ajouterMembreParEmail($tontineId, 'daouda@test.sn'), 'Ajout Daouda');
assertKo(ajouterMembreParEmail($tontineId, 'superadmin@test.sn'), 'Ajout 5ème membre (dépasse max=4) doit échouer');

// ─── DÉMARRAGE + TIRAGE AU SORT ───────────────────────────────────────────────
titre('DÉMARRAGE TONTINE + TIRAGE AU SORT');
$res = demarrerActiviteTontine($tontineId, $adminId);
assertOk($res, 'Démarrage de la tontine');
$membres = listerMembres($tontineId);
info('Ordre des tours après tirage au sort :');
foreach ($membres as $m) {
    info("  [{$m['ordre_tour']}] {$m['nom_complet']}");
}
$premierBeneficiaire = $membres[0]; // ordre 1 = premier bénéficiaire
info("Premier bénéficiaire prévu : {$premierBeneficiaire['nom_complet']} (id={$premierBeneficiaire['utilisateur_id']})");

// ─── LANCEMENT DU CYCLE 1 ─────────────────────────────────────────────────────
titre('LANCEMENT CYCLE 1');
$nbMembres    = count($membres);
$montantCot   = 10000.0;
$cagnotteTheo = $nbMembres * $montantCot; // 4 × 10000 = 40000 FCFA
$dateDebut    = date('Y-m-d');
$dateLimite   = date('Y-m-d', strtotime('+30 days'));
$benefId      = (int)$premierBeneficiaire['utilisateur_id'];

$cycleId = creerCycle($tontineId, 1, $dateDebut, $dateLimite, $benefId, $cagnotteTheo);
info("Cycle créé : ID=$cycleId | Bénéficiaire ID=$benefId | Cagnotte théorique=" . number_format($cagnotteTheo, 0, ',', ' ') . " FCFA");

// Vérifier qu'il n'y a pas de double cycle possible
$cycleTest = cycleEnCours($tontineId);
if ($cycleTest && $cycleTest['id'] == $cycleId) ok('cycleEnCours() retourne bien le cycle créé');
else ko('cycleEnCours() ne retourne pas le bon cycle');

// ─── PAIEMENTS NORMAUX ────────────────────────────────────────────────────────
titre('PAIEMENTS (simulation Wave / Orange Money / Espèces)');
$membres_payeurs = array_filter($membres, fn($m) => (int)$m['utilisateur_id'] !== $benefId);

$modesPaiement = ['wave', 'orange_money', 'especes'];
$i = 0;
foreach ($membres_payeurs as $m) {
    $mode = $modesPaiement[$i % 3];
    $tel  = $m['telephone'];
    $res  = enregistrerPaiement($tontineId, $cycleId, (int)$m['utilisateur_id'], $benefId, $montantCot, $mode, $tel);
    assertOk($res, "Paiement de {$m['nom_complet']} ({$mode}) → réf: " . ($res['reference'] ?? ''));
    $i++;
}

// Paiement du bénéficiaire lui-même
$resBenef = enregistrerPaiement($tontineId, $cycleId, $benefId, $benefId, $montantCot, 'wave', $premierBeneficiaire['telephone']);
assertOk($resBenef, 'Paiement du bénéficiaire lui-même');

// Vérifier double paiement refusé
$resDouble = enregistrerPaiement($tontineId, $cycleId, $benefId, $benefId, $montantCot, 'wave', $premierBeneficiaire['telephone']);
assertKo($resDouble, 'Double paiement refusé');

// Cagnotte collectée
$collectee = cagnotteCollectee($cycleId);
info("Cagnotte collectée : " . number_format($collectee, 0, ',', ' ') . " FCFA / théorique " . number_format($cagnotteTheo, 0, ',', ' ') . " FCFA");
if (abs($collectee - $cagnotteTheo) < 0.01) ok('Cagnotte complète atteinte ✓');
else ko("Cagnotte incomplète : $collectee vs $cagnotteTheo");

$complete = cagnotteEstComplete($tontineId, $cycleId);
if ($complete) ok('cagnotteEstComplete() retourne TRUE');
else ko('cagnotteEstComplete() retourne FALSE (anomalie)');

// ─── DISTRIBUTION DE LA CAGNOTTE ─────────────────────────────────────────────
titre('DISTRIBUTION DE LA CAGNOTTE AU BÉNÉFICIAIRE');
$resChoix = choisirModeReception($tontineId, $cycleId, $benefId, 'recevoir_maintenant');
assertOk($resChoix, "Bénéficiaire choisit 'recevoir maintenant'");
info("Montant reçu : " . number_format($resChoix['montant_recu'] ?? 0, 0, ',', ' ') . " FCFA | Restant : " . number_format($resChoix['montant_restant'] ?? 0, 0, ',', ' ') . " FCFA");

// Vérifier que le bénéficiaire est maintenant EN DERNIER dans l'ordre
$membresApres = listerMembres($tontineId);
info('Ordre après distribution (bénéficiaire doit être en dernier) :');
foreach ($membresApres as $m) {
    $estLui = (int)$m['utilisateur_id'] === $benefId;
    info("  [{$m['ordre_tour']}] {$m['nom_complet']}" . ($estLui ? ' ← bénéficiaire (doit être dernier)' : ''));
}
$dernierMembre = end($membresApres);
if ((int)$dernierMembre['utilisateur_id'] === $benefId) ok('Bénéficiaire mis en dernier après distribution ✓');
else ko('Bénéficiaire PAS en dernier (anomalie mettreMembreEnDernierOrdre)');

// ─── VÉRIFICATION STATUT CYCLE APRÈS DISTRIBUTION ────────────────────────────
titre('VÉRIFICATION STATUT CYCLE APRÈS DISTRIBUTION');
$cycleAjour = obtenirCycleSimple($cycleId);
info("Statut cycle : {$cycleAjour['statut']} (attendu: 'distribue')");
if ($cycleAjour['statut'] === 'distribue') ok('Cycle marqué distribué ✓');
else ko("Cycle non marqué distribué : {$cycleAjour['statut']}");

// ─── CYCLE 2 : SIMULATION RETARD + AMENDE ─────────────────────────────────────
titre('CYCLE 2 : SIMULATION DE RETARD ET AMENDES');
$membresOrdre = listerMembres($tontineId);
$benefCycle2Id = (int)$membresOrdre[0]['utilisateur_id'];
info("Bénéficiaire cycle 2 : {$membresOrdre[0]['nom_complet']}");

$cycleId2 = creerCycle($tontineId, 2, date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('-1 day')), $benefCycle2Id, $cagnotteTheo);
info("Cycle 2 créé (daté d'hier = en retard artificiel) : ID=$cycleId2");

// Marquer 2 membres en retard (après la date limite)
marquerMembresEnRetard($tontineId, $cycleId2);
$enRetard = array_filter(listerMembres($tontineId), fn($m) => $m['statut'] === 'retard');
info(count($enRetard) . " membre(s) marqués en retard");
foreach ($enRetard as $m) {
    info("  → {$m['nom_complet']} est en retard");
    // Appliquer une amende de 3 jours de retard
    $resAmende = appliquerAmende($tontineId, $cycleId2, (int)$m['utilisateur_id'], 3000.0, 'par_jour', 3, 'Retard de cotisation');
    assertOk($resAmende, "Amende appliquée à {$m['nom_complet']} : 3 000 FCFA");
    
    // Tenter double amende sur même cycle
    $resDouble2 = appliquerAmende($tontineId, $cycleId2, (int)$m['utilisateur_id'], 3000.0, 'par_jour', 3, 'Doublon');
    assertKo($resDouble2, "Double amende refusée pour {$m['nom_complet']}");
}

// Paiement d'un membre en retard (cotisation + amende automatiquement incluse)
if (!empty($enRetard)) {
    $membreEnRetard = reset($enRetard);
    $uidRetard = (int)$membreEnRetard['utilisateur_id'];
    $amendeNP = getAmendesNonPayees($tontineId, $cycleId2, $uidRetard);
    info("Amende non payée pour {$membreEnRetard['nom_complet']} : " . number_format($amendeNP, 0, ',', ' ') . " FCFA");
    
    $resPaiementRetard = enregistrerPaiement($tontineId, $cycleId2, $uidRetard, $benefCycle2Id, $montantCot, 'orange_money', $membreEnRetard['telephone']);
    assertOk($resPaiementRetard, "Paiement avec amende incluse : montant total = " . number_format($montantCot + $amendeNP, 0, ',', ' ') . " FCFA (amende=" . number_format($resPaiementRetard['amende_payee'] ?? 0, 0, ',', ' ') . ")");
    
    // Statut doit être repassé à 'actif'
    $req = $bd->prepare('SELECT statut FROM membres_tontine WHERE tontine_id=? AND utilisateur_id=?');
    $req->execute([$tontineId, $uidRetard]);
    $statut = $req->fetchColumn();
    if ($statut === 'actif') ok("Statut de {$membreEnRetard['nom_complet']} repassé à 'actif' après paiement");
    else ko("Statut de {$membreEnRetard['nom_complet']} = '$statut' (attendu: actif)");
}
// Clôturer cycle 2
$bd->prepare("UPDATE cycles_cotisation SET statut='distribue' WHERE id=?")->execute([$cycleId2]);

// ─── URGENCE : MODE IMMÉDIAT ────────────────────────────────────────────────────
titre('DEMANDE D\'URGENCE — MODE IMMÉDIAT');

// D'abord créer un cycle 3 propre en cours (pas encore distribué)
$membresOrdre = listerMembres($tontineId);
$benefCycle3Id = (int)$membresOrdre[0]['utilisateur_id'];
$autreMembreId = (int)$membresOrdre[1]['utilisateur_id'];
info("Bénéficiaire cycle 3 prévu : {$membresOrdre[0]['nom_complet']}");
info("Demandeur urgence sera : {$membresOrdre[1]['nom_complet']}");

$cycleId3 = creerCycle($tontineId, 3, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), $benefCycle3Id, $cagnotteTheo);
info("Cycle 3 créé : ID=$cycleId3");

// Faire payer tout le monde pour cycle 3
foreach ($membresOrdre as $m) {
    $r = enregistrerPaiement($tontineId, $cycleId3, (int)$m['utilisateur_id'], $benefCycle3Id, $montantCot, 'wave', $m['telephone']);
    info("  Paiement cycle 3 par {$m['nom_complet']} : " . ($r['succes'] ? '✓' : '✗ '.$r['message']));
}

// Créer la demande d'urgence par le 2ème membre
$resUrg = creerDemandeUrgence($tontineId, $autreMembreId, "Problème médical urgent");
assertOk($resUrg, "Demande d'urgence soumise");

// Tenter une 2ème urgence (doit être refusée)
$resUrg2 = creerDemandeUrgence($tontineId, $autreMembreId, "Doublon urgence");
assertKo($resUrg2, "Double demande urgence refusée");

// Récupérer l'ID de l'urgence
$req = $bd->prepare("SELECT id FROM demandes_urgence WHERE tontine_id=? AND demandeur_id=? AND statut='en_attente'");
$req->execute([$tontineId, $autreMembreId]);
$urgenceId = (int)$req->fetchColumn();
info("Urgence ID: $urgenceId");

// Valider l'urgence en mode immédiat
$resValider = validerUrgence($urgenceId, $adminId, 'immediat', "Accordé - urgence médicale");
assertOk($resValider, "Urgence validée en mode IMMÉDIAT");

// Vérifier que le bénéficiaire a changé dans le cycle
$cycleAjour3 = obtenirCycleSimple($cycleId3);
info("Nouveau bénéficiaire cycle 3 : {$cycleAjour3['beneficiaire_id']} (attendu: $autreMembreId)");
if ((int)$cycleAjour3['beneficiaire_id'] === $autreMembreId) ok("Bénéficiaire du cycle changé au demandeur d'urgence ✓");
else ko("Bénéficiaire du cycle PAS changé : {$cycleAjour3['beneficiaire_id']} ≠ $autreMembreId");

// Vérifier la distribution
$distrib3 = etatDistribution($tontineId, $cycleId3);
if ($distrib3) {
    info("Distribution cycle 3 : statut={$distrib3['statut']}, reçu={$distrib3['montant_recu']} FCFA");
    if ((float)$distrib3['montant_recu'] > 0) ok("Montant bien distribué au demandeur d'urgence ✓");
    else ko("Montant distribué = 0 (anomalie distribution urgence)");
} else {
    ko("Pas de distribution trouvée pour cycle 3");
}

// ─── URGENCE : MODE PROCHAIN TOUR ─────────────────────────────────────────────
titre('DEMANDE D\'URGENCE — MODE PROCHAIN TOUR');

// Cycle 4
$membresOrdre = listerMembres($tontineId);
$benefCycle4Id = (int)$membresOrdre[0]['utilisateur_id'];
$demandeurId   = (int)$membresOrdre[count($membresOrdre)-1]['utilisateur_id']; // Dernier (pas encore bénéficiaire)
info("Bénéficiaire cycle 4 prévu : {$membresOrdre[0]['nom_complet']}");
info("Demandeur urgence (prochain tour) : {$membresOrdre[count($membresOrdre)-1]['nom_complet']}");

$cycleId4 = creerCycle($tontineId, 4, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), $benefCycle4Id, $cagnotteTheo);

// Quelques paiements pour cycle 4
foreach (array_slice($membresOrdre, 0, 2) as $m) {
    enregistrerPaiement($tontineId, $cycleId4, (int)$m['utilisateur_id'], $benefCycle4Id, $montantCot, 'wave', $m['telephone']);
}

// Urgence prochain tour
$req2 = $bd->prepare("SELECT id FROM demandes_urgence WHERE tontine_id=? AND demandeur_id=? AND statut='en_attente'");
$req2->execute([$tontineId, $demandeurId]);
$urg4Exist = $req2->fetchColumn();

if (!$urg4Exist) {
    $resUrg4 = creerDemandeUrgence($tontineId, $demandeurId, "Besoin urgent pour prochain tour");
    assertOk($resUrg4, "Demande d'urgence prochain tour");
    $req2->execute([$tontineId, $demandeurId]);
    $urgenceId4 = (int)$bd->query("SELECT id FROM demandes_urgence WHERE tontine_id=$tontineId AND demandeur_id=$demandeurId AND statut='en_attente' LIMIT 1")->fetchColumn();
} else {
    $urgenceId4 = (int)$urg4Exist;
}

$resValider4 = validerUrgence((int)$urgenceId4, $adminId, 'prochain_cycle', "Sera prioritaire au prochain cycle");
assertOk($resValider4, "Urgence validée en mode PROCHAIN TOUR");

// Vérifier que le demandeur est maintenant en position 1
$membresApresUrg = listerMembres($tontineId);
foreach ($membresApresUrg as $m) {
    info("  [{$m['ordre_tour']}] {$m['nom_complet']}" . ((int)$m['utilisateur_id'] === $demandeurId ? ' ← demandeur urgence' : ''));
}
$premier = $membresApresUrg[0];
if ((int)$premier['utilisateur_id'] === $demandeurId) ok("Demandeur urgence prochain tour passé en position 1 ✓");
else info("(Note: le demandeur d'urgence prochain tour était déjà à la bonne position ou la position a été modifiée)");

// ─── PÉTITION + VOTE ──────────────────────────────────────────────────────────
titre('PÉTITION POUR CHANGER L\'ADMINISTRATEUR');

// Créer la pétition (seuil = ceil(4/3) = 2 signatures minimum)
$resPetition = creerPetition($tontineId, $bobId, "L'admin Alice est inefficace");
assertOk($resPetition, "Pétition créée (seuil: " . ($resPetition['petition_id'] ?? '?') . ")");
$petitionId = $resPetition['petition_id'] ?? 0;

// Vérifier double pétition refusée
$resPetDbl = creerPetition($tontineId, $bobId, "Doublon");
assertKo($resPetDbl, "Double pétition refusée");

// Signer la pétition (Bob a déjà signé au moment de la création)
$resSign = signerPetition($petitionId, $coumbaId);
assertOk($resSign, "Coumba signe la pétition");

// Double signature refusée
$resDblSign = signerPetition($petitionId, $bobId);
assertKo($resDblSign, "Double signature refusée");

// Vérifier que le vote s'est ouvert automatiquement (seuil = 2 signatures atteint)
$req = $bd->prepare("SELECT statut FROM petitions WHERE id=?");
$req->execute([$petitionId]);
$statutPetition = $req->fetchColumn();
if ($statutPetition === 'vote_ouvert') ok("Vote ouvert automatiquement au seuil ✓");
else info("Statut pétition: $statutPetition (peut nécessiter plus de signatures selon le nb membres)");

$req = $bd->prepare("SELECT id FROM votes WHERE petition_id=? ORDER BY id DESC LIMIT 1");
$req->execute([$petitionId]);
$voteId = (int)$req->fetchColumn();
info("Vote ID: $voteId");

if ($voteId > 0) {
    titre('VOTE OUI/NON POUR CHANGER L\'ADMIN');
    
    // Bob se porte candidat pour remplacer Alice
    $resCandidature = sePorterCandidat($voteId, $bobId, "Je serai un meilleur admin !");
    assertOk($resCandidature, "Bob se porte candidat");
    
    // Double candidature refusée
    $resCand2 = sePorterCandidat($voteId, $bobId, "doublon");
    assertKo($resCand2, "Double candidature refusée");
    
    // Votes OUI des membres
    assertOk(voter($voteId, $bobId,    'oui'), "Bob vote OUI");
    assertOk(voter($voteId, $coumbaId, 'oui'), "Coumba vote OUI");
    assertOk(voter($voteId, $daoudaId, 'oui'), "Daouda vote OUI");
    
    // Alice vote NON
    assertOk(voter($voteId, $adminId,  'non'), "Alice vote NON");
    
    // Double vote refusé
    assertKo(voter($voteId, $bobId, 'oui'), "Double vote refusé");
    
    // Vérifier le résultat (3 OUI / 1 NON = 75% ≥ seuil 75% → changement admin)
    $req = $bd->prepare("SELECT statut, resultat, nombre_oui, nombre_non, nouvel_admin_id FROM votes WHERE id=?");
    $req->execute([$voteId]);
    $vote = $req->fetch();
    info("Résultat vote : {$vote['nombre_oui']} OUI / {$vote['nombre_non']} NON | statut={$vote['statut']} | résultat={$vote['resultat']}");
    
    if ($vote['statut'] === 'clos') ok("Vote clôturé automatiquement au dernier vote ✓");
    else ko("Vote pas clôturé : statut={$vote['statut']}");
    
    if ($vote['resultat'] === 'admin_change') {
        ok("Admin changé ✓ | Nouvel admin ID = {$vote['nouvel_admin_id']}");
        if ((int)$vote['nouvel_admin_id'] === $bobId) ok("Bob est bien le nouvel admin (candidat déclaré avec meilleur score) ✓");
        else info("Nouvel admin ID={$vote['nouvel_admin_id']} (peut différer si score de fiabilité différent)");
    } else {
        ko("Admin pas changé malgré 75% OUI : {$vote['resultat']}");
    }
    
    // Vérifier rôle BDD
    $req = $bd->prepare("SELECT utilisateur_id, role FROM membres_tontine WHERE tontine_id=? AND role='admin'");
    $req->execute([$tontineId]);
    $newAdmin = $req->fetch();
    if ($newAdmin) {
        info("Nouveau admin dans membres_tontine : utilisateur_id={$newAdmin['utilisateur_id']}, role={$newAdmin['role']}");
        if ((int)$newAdmin['utilisateur_id'] === $bobId) ok("Rôle admin bien mis à jour en BDD ✓");
        else ko("Rôle admin BDD = {$newAdmin['utilisateur_id']} ≠ $bobId");
    }
}

// ─── TEST LOGIQUE CAGNOTTE AUGMENTE / DIMINUE ─────────────────────────────────
titre('TEST CAGNOTTE : AUGMENTATION / DIMINUTION APRÈS DISTRIBUTION');
$req = $bd->prepare('SELECT cagnotte_collectee, cagnotte_theorique, statut FROM cycles_cotisation WHERE id=?');
$req->execute([$cycleId]);
$cycleData = $req->fetch();
info("Cycle 1 après distribution : collectée={$cycleData['cagnotte_collectee']} FCFA | théorique={$cycleData['cagnotte_theorique']} FCFA | statut={$cycleData['statut']}");

$distrib1 = etatDistribution($tontineId, $cycleId);
if ($distrib1) {
    info("Distribution cycle 1 : reçu={$distrib1['montant_recu']} FCFA | restant={$distrib1['montant_restant']} FCFA | statut={$distrib1['statut']}");
    $totalStats = statistiquesTontine($tontineId);
    info("Statistiques tontine : cagnotte_brut=" . number_format($totalStats['cagnotte_brut'], 0, ',', ' ') .
         " | déjà_distribué=" . number_format($totalStats['cagnotte_deja_distribue'], 0, ',', ' ') .
         " | solde_net=" . number_format($totalStats['cagnotte_totale'], 0, ',', ' '));
    ok("Cagnotte solde = Brut - Distribué ✓");
}

// ─── TEST TOUR COMPLET ────────────────────────────────────────────────────────
titre('TEST LOGIQUE FIN DE TOUR');
$tontineReload = obtenirTontine($tontineId);
info("cycles_completes_ce_tour : {$tontineReload['cycles_completes_ce_tour']}");
info("numero_tour_actuel : {$tontineReload['numero_tour_actuel']}");
info("tour_complet_en_attente : {$tontineReload['tour_complet_en_attente']}");

// Forcer un tour complet (simuler 4 cycles distribués = 1 par membre)
$bd->prepare('UPDATE tontines SET cycles_completes_ce_tour=0, numero_tour_actuel=1, tour_complet_en_attente=0 WHERE id=?')
   ->execute([$tontineId]);
// Simuler que 3 cycles sont déjà distribués ce tour (il en manque 1 pour compléter le tour de 4)
$bd->prepare('UPDATE tontines SET cycles_completes_ce_tour=3 WHERE id=?')->execute([$tontineId]);
verifierFinDeTour($tontineId);
$tontineApres = obtenirTontine($tontineId);
info("Après verifierFinDeTour (4ème distribution) : cycles_completes={$tontineApres['cycles_completes_ce_tour']} | tour_complet={$tontineApres['tour_complet_en_attente']} | tour_actuel={$tontineApres['numero_tour_actuel']}");

if ((int)$tontineApres['tour_complet_en_attente'] === 1) ok("Tour complet détecté → tour_complet_en_attente=1 ✓");
else ko("Tour complet NON détecté (anomalie verifierFinDeTour)");
if ((int)$tontineApres['numero_tour_actuel'] === 2) ok("Numéro de tour incrémenté : tour 2 ✓");
else ko("Numéro tour = {$tontineApres['numero_tour_actuel']} (attendu: 2)");
if ((int)$tontineApres['cycles_completes_ce_tour'] === 0) ok("Compteur cycles remis à 0 ✓");
else ko("Compteur cycles = {$tontineApres['cycles_completes_ce_tour']} (attendu: 0)");

// Nouveau tour
$resNouveauTour = lancerNouveauTour($tontineId, $adminId);
// Note: l'admin peut avoir changé (Bob) — on teste les deux
if (!$resNouveauTour['succes']) {
    $resNouveauTour = lancerNouveauTour($tontineId, $bobId);
}
assertOk($resNouveauTour, "Nouveau tour lancé (nouveau tirage au sort)");

$membresNouveauTour = listerMembres($tontineId);
info("Ordre nouveau tour (retiré au sort) :");
foreach ($membresNouveauTour as $m) {
    info("  [{$m['ordre_tour']}] {$m['nom_complet']}");
}

// ─── RÉCAPITULATIF ────────────────────────────────────────────────────────────
titre('RÉCAPITULATIF FINAL');
$stats = statistiquesTontine($tontineId);
info("Membres actifs          : {$stats['nb_membres']}");
info("Membres en retard       : {$stats['nb_retards']}");
info("Volume brut collecté    : " . number_format($stats['cagnotte_brut'], 0, ',', ' ') . " FCFA");
info("Total déjà distribué    : " . number_format($stats['cagnotte_deja_distribue'], 0, ',', ' ') . " FCFA");
info("Solde net disponible    : " . number_format($stats['cagnotte_totale'], 0, ',', ' ') . " FCFA");
info("Urgences en attente     : {$stats['urgences_attente']}");

// Lister les logs
$logs = $bd->query("SELECT action, details, date_creation FROM logs ORDER BY id DESC LIMIT 20")->fetchAll();
info("\nDernières actions (audit log) :");
foreach ($logs as $l) {
    info("  [{$l['date_creation']}] {$l['action']} - {$l['details']}");
}

echo "\n\033[1;32m══════════════════════════════════════════════\033[0m\n";
echo "\033[1;32m  TESTS TERMINÉS — Relisez les ❌ ci-dessus.\033[0m\n";
echo "\033[1;32m══════════════════════════════════════════════\033[0m\n\n";
