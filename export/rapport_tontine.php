<?php
//  EXPORT - RAPPORT COMPLET D'UNE TONTINE (admin)
require_once __DIR__ . '/../configuration/session.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../fonctions/tontine.php';
require_once __DIR__ . '/../fonctions/membre.php';
require_once __DIR__ . '/../fonctions/cotisation.php';
require_once __DIR__ . '/../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$utilisateurId = idUtilisateurConnecte();

if (!estAdminDeTontine($utilisateurId, $tontineId) && !estSuperAdmin()) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$tontine     = obtenirTontine($tontineId);
$membres     = listerMembres($tontineId);
$cycles      = listerCycles($tontineId);
$stats       = statistiquesTontine($tontineId);
$cycleActuel = cycleEnCours($tontineId);
$paiementsCycle = $cycleActuel ? paiementsDuCycle($tontineId, $cycleActuel['id']) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport - <?= htmlspecialchars($tontine['nom']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; background: #f5f5f5; }
        .doc { max-width: 800px; margin: 24px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .doc-entete { background: #c1602e; color: #fff; padding: 20px 28px; display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 18px; font-weight: 700; }
        h2 { font-size: 14px; color: #c1602e; border-bottom: 2px solid #c1602e; padding-bottom: 6px; margin: 20px 0 12px; }
        .doc-corps { padding: 24px 28px; }
        .stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 20px; }
        .stat { background: #f7ead9; border-radius: 6px; padding: 12px; text-align: center; }
        .stat-nb { font-size: 22px; font-weight: 700; color: #4a2e1f; }
        .stat-lb { font-size: 10px; color: #c1602e; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 16px; }
        thead tr { background: #c1602e; color: #fff; }
        th { padding: 7px 10px; text-align: left; font-weight: 600; }
        td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; }
        tr:nth-child(even) td { background: #f8f9ff; }
        .badge-s  { background:#EAF3DE;color:#27500A;padding:2px 6px;border-radius:10px;font-size:10px; }
        .badge-d  { background:#FCEBEB;color:#791F1F;padding:2px 6px;border-radius:10px;font-size:10px; }
        .badge-a  { background:#FAEEDA;color:#633806;padding:2px 6px;border-radius:10px;font-size:10px; }
        .doc-pied { text-align:center;padding:14px;border-top:1px solid #f0f0f0;font-size:10px;color:#aaa; }
        .btn-actions { display:flex;gap:8px;justify-content:center;padding:14px; }
        .btn { padding:8px 18px;border-radius:6px;border:none;cursor:pointer;font-size:12px;text-decoration:none;display:inline-block; }
        .btn-principal { background:#c1602e;color:#fff; }
        .btn-secondaire { background:#fff;color:#c1602e;border:1px solid #c1602e; }
        @media print { body{background:#fff;} .doc{box-shadow:none;margin:0;border-radius:0;max-width:100%;} .btn-actions{display:none;} }
    </style>
</head>
<body>
<div class="doc">
    <div class="doc-entete">
        <div>
            <h1>Rapport de tontine</h1>
            <p><?= htmlspecialchars($tontine['nom']) ?> - <?= formaterDate(date('Y-m-d')) ?></p>
        </div>
        <div style="text-align:right;font-size:20px;font-weight:700">E-Tontine</div>
    </div>

    <div class="doc-corps">

        <!-- Infos générales -->
        <h2>Informations générales</h2>
        <table>
            <tr><td><strong>Nom</strong></td><td><?= htmlspecialchars($tontine['nom']) ?></td>
                <td><strong>Créateur</strong></td><td><?= htmlspecialchars($tontine['nom_createur']) ?></td></tr>
            <tr><td><strong>Fréquence</strong></td><td><?= libelleFrequence($tontine['frequence']) ?></td>
                <td><strong>Cotisation</strong></td><td><?= formaterMontant((float)$tontine['montant_cotisation']) ?></td></tr>
            <tr><td><strong>Statut</strong></td><td><?= $tontine['statut'] ?></td>
                <td><strong>Créée le</strong></td><td><?= formaterDate($tontine['date_creation']) ?></td></tr>
        </table>

        <!-- Statistiques -->
        <h2>Statistiques</h2>
        <div class="stats">
            <div class="stat"><div class="stat-nb"><?= $stats['nb_membres'] ?></div><div class="stat-lb">Membres actifs</div></div>
            <div class="stat"><div class="stat-nb"><?= $stats['nb_retards'] ?></div><div class="stat-lb">En retard</div></div>
            <div class="stat"><div class="stat-nb"><?= formaterMontant($stats['cagnotte_totale']) ?></div><div class="stat-lb">Solde cagnotte (après retraits)</div></div>
            <div class="stat"><div class="stat-nb"><?= count($cycles) ?></div><div class="stat-lb">Cycles</div></div>
        </div>

        <!-- Membres -->
        <h2>Membres (<?= count($membres) ?>)</h2>
        <table>
            <thead><tr><th>#</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Score</th><th>Statut</th></tr></thead>
            <tbody>
                <?php foreach ($membres as $m): ?>
                <tr>
                    <td><?= $m['ordre_tour'] ?></td>
                    <td><?= htmlspecialchars($m['nom_complet']) ?></td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td><?= $m['role'] === ROLE_ADMIN ? '<span class="badge-s">Admin</span>' : 'Membre' ?></td>
                    <td><?= number_format((float)$m['score_fiabilite'], 1) ?>%</td>
                    <td><?php
                        echo match($m['statut']) {
                            STATUT_ACTIF  => '<span class="badge-s">Actif</span>',
                            STATUT_RETARD => '<span class="badge-a">En retard</span>',
                            STATUT_EXCLU  => '<span class="badge-d">Exclu</span>',
                            default       => $m['statut'],
                        };
                    ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Cycles -->
        <h2>Historique des cycles</h2>
        <table>
            <thead><tr><th>Cycle</th><th>Début</th><th>Limite</th><th>Bénéficiaire</th><th>Collecté</th><th>Objectif</th><th>Statut</th></tr></thead>
            <tbody>
                <?php foreach ($cycles as $c): ?>
                <tr>
                    <td><?= $c['numero_cycle'] ?></td>
                    <td><?= formaterDate($c['date_debut']) ?></td>
                    <td><?= formaterDate($c['date_limite']) ?></td>
                    <td><?= htmlspecialchars($c['nom_beneficiaire'] ?? '-') ?></td>
                    <td><?= formaterMontant((float)$c['cagnotte_collectee']) ?></td>
                    <td><?= formaterMontant((float)$c['cagnotte_theorique']) ?></td>
                    <td><?php
                        echo match($c['statut']) {
                            'en_cours'  => '<span class="badge-a">En cours</span>',
                            'clos'      => '<span class="badge-d">Clos</span>',
                            'distribue' => '<span class="badge-s">Distribué</span>',
                            default     => $c['statut'],
                        };
                    ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paiements du cycle en cours -->
        <?php if ($cycleActuel && !empty($paiementsCycle)): ?>
        <h2>Paiements du cycle en cours (n°<?= $cycleActuel['numero_cycle'] ?>)</h2>
        <table>
            <thead><tr><th>Payeur</th><th>Pour</th><th>Montant</th><th>Mode</th><th>Date</th><th>Référence</th></tr></thead>
            <tbody>
                <?php foreach ($paiementsCycle as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nom_payeur']) ?></td>
                    <td><?= htmlspecialchars($p['nom_beneficiaire']) ?></td>
                    <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                    <td><?= libelleModePaiement($p['mode_paiement']) ?></td>
                    <td><?= formaterDate($p['date_paiement']) ?></td>
                    <td style="font-size:10px;color:#888"><?= htmlspecialchars($p['reference']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </div>

    <div class="btn-actions">
        <button class="btn btn-principal" onclick="window.print()">Imprimer</button>
        <a href="javascript:history.back()" class="btn btn-secondaire">Retour</a>
    </div>
    <div class="doc-pied">
        <?= APP_NOM ?> &copy; <?= date('Y') ?> - Rapport généré le <?= formaterDateHeure(date('Y-m-d H:i:s')) ?>
    </div>
</div>
</body>
</html>
