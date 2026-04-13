<?php
//  EXPORT - HISTORIQUE COMPLET D'UN MEMBRE (PDF imprimable)
require_once __DIR__ . '/../configuration/session.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../fonctions/tontine.php';
require_once __DIR__ . '/../fonctions/cotisation.php';
require_once __DIR__ . '/../fonctions/utilisateur.php';
require_once __DIR__ . '/../fonctions/aide.php';

exigerConnexion();
$tontineId     = obtenirGetInt('tontine');
$membreId      = obtenirGetInt('membre');
$utilisateurId = idUtilisateurConnecte();

// Accès : soi-même, admin de la tontine, ou super admin
if ($membreId !== $utilisateurId && !estAdminDeTontine($utilisateurId, $tontineId) && !estSuperAdmin()) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$tontine    = obtenirTontine($tontineId);
$membre     = obtenirUtilisateur($membreId);
$historique = historiquePaiementsMembre($membreId, $tontineId);
$total      = array_sum(array_column($historique, 'montant'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique - <?= htmlspecialchars($membre['nom_complet']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; background: #f5f5f5; }
        .doc { max-width: 760px; margin: 24px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .doc-entete { background: #c1602e; color: #fff; padding: 20px 28px; display: flex; justify-content: space-between; align-items: center; }
        .doc-entete h1 { font-size: 18px; font-weight: 700; }
        .doc-entete p { font-size: 12px; opacity: .8; margin-top: 2px; }
        .doc-corps { padding: 24px 28px; }
        .doc-meta { display: flex; gap: 24px; background: #f8f9ff; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; }
        .doc-meta-item { flex: 1; }
        .doc-meta-label { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: .4px; }
        .doc-meta-valeur { font-size: 14px; font-weight: 600; color: #4a2e1f; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        thead tr { background: #c1602e; color: #fff; }
        th { padding: 9px 12px; text-align: left; font-weight: 600; }
        td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
        tr:nth-child(even) td { background: #f8f9ff; }
        .total-ligne td { background: #f7ead9; font-weight: 700; }
        .badge-s { background: #EAF3DE; color: #27500A; padding: 2px 7px; border-radius: 10px; font-size: 10px; }
        .doc-pied { text-align: center; padding: 14px; border-top: 1px solid #f0f0f0; font-size: 10px; color: #aaa; }
        .btn-actions { display: flex; gap: 8px; justify-content: center; padding: 14px; }
        .btn { padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-principal { background: #c1602e; color: #fff; }
        .btn-secondaire { background: #fff; color: #c1602e; border: 1px solid #c1602e; }
        @media print { body { background:#fff; } .doc { box-shadow:none; margin:0; border-radius:0; max-width:100%; } .btn-actions { display:none; } }
    </style>
</head>
<body>
<div class="doc">
    <div class="doc-entete">
        <div>
            <h1>Historique des paiements</h1>
            <p><?= htmlspecialchars($tontine['nom']) ?> - Généré le <?= formaterDate(date('Y-m-d')) ?></p>
        </div>
        <div style="text-align:right">
            <div style="font-size:20px;font-weight:700">E-Tontine</div>
        </div>
    </div>
    <div class="doc-corps">
        <div class="doc-meta">
            <div class="doc-meta-item">
                <div class="doc-meta-label">Membre</div>
                <div class="doc-meta-valeur"><?= htmlspecialchars($membre['nom_complet']) ?></div>
            </div>
            <div class="doc-meta-item">
                <div class="doc-meta-label">Email</div>
                <div class="doc-meta-valeur"><?= htmlspecialchars($membre['email']) ?></div>
            </div>
            <div class="doc-meta-item">
                <div class="doc-meta-label">Total payé</div>
                <div class="doc-meta-valeur"><?= formaterMontant($total) ?></div>
            </div>
            <div class="doc-meta-item">
                <div class="doc-meta-label">Paiements</div>
                <div class="doc-meta-valeur"><?= count($historique) ?></div>
            </div>
        </div>

        <?php if (empty($historique)): ?>
        <p style="color:#888;text-align:center;padding:20px">Aucun paiement enregistré.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Cycle</th><th>Date</th><th>Montant</th><th>Mode</th><th>Type</th><th>Référence</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php foreach ($historique as $p): ?>
                <tr>
                    <td>Cycle <?= $p['numero_cycle'] ?></td>
                    <td><?= formaterDate($p['date_paiement']) ?></td>
                    <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                    <td><?= libelleModePaiement($p['mode_paiement']) ?></td>
                    <td><?= $p['type_paiement'] === TYPE_POUR_AUTRUI ? 'Pour autrui' : 'Normal' ?></td>
                    <td style="font-size:10px;color:#888"><?= htmlspecialchars($p['reference']) ?></td>
                    <td><span class="badge-s">Payé</span></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-ligne">
                    <td colspan="2"><strong>Total</strong></td>
                    <td><strong><?= formaterMontant($total) ?></strong></td>
                    <td colspan="4"></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="btn-actions">
        <button class="btn btn-principal" onclick="window.print()">Imprimer</button>
        <a href="javascript:history.back()" class="btn btn-secondaire">Retour</a>
    </div>
    <div class="doc-pied">
        <?= APP_NOM ?> &copy; <?= date('Y') ?> - Document généré automatiquement, valide comme justificatif interne.
    </div>
</div>
</body>
</html>
