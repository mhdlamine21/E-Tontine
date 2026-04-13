<?php
//  SUPER ADMIN - TABLEAU DE BORD GLOBAL AVEC GRAPHIQUES
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/utilisateur.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerSuperAdmin();
verifierExpirationSession();

if (!estSuperAdmin()) {
    header('Location: ' . APP_URL . '/pages/tableau_de_bord/accueil.php');
    exit;
}

$bd = connexionBD();

// --- Statistiques globales ---
$nbTontines    = (int)$bd->query('SELECT COUNT(*) FROM tontines')->fetchColumn();
$nbUtilisateurs= (int)$bd->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn();
$nbActives     = (int)$bd->query("SELECT COUNT(*) FROM tontines WHERE statut = 'active'")->fetchColumn();
$nbSuspendues  = (int)$bd->query("SELECT COUNT(*) FROM tontines WHERE statut = 'suspendue'")->fetchColumn();
$nbBrouillon   = (int)$bd->query("SELECT COUNT(*) FROM tontines WHERE statut = 'brouillon'")->fetchColumn();
$nbFermees     = (int)$bd->query("SELECT COUNT(*) FROM tontines WHERE statut = 'fermee'")->fetchColumn();
$nbBloques     = (int)$bd->query('SELECT COUNT(*) FROM utilisateurs WHERE est_bloque = 1')->fetchColumn();
$nbPaiements   = (int)$bd->query('SELECT COUNT(*) FROM paiements')->fetchColumn();
$montantTotal  = (float)$bd->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut = 'paye'")->fetchColumn();
$montantEnAttente = (float)$bd->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut = 'en_attente'")->fetchColumn();

// Score de fiabilité moyen global
$scoreMoyenGlobal = (float)($bd->query("SELECT COALESCE(AVG(score_fiabilite), 100) FROM membres_tontine")->fetchColumn());

// Nouveaux inscrits ce mois
$nbNouveauxCeMois = (int)$bd->query("SELECT COUNT(*) FROM utilisateurs WHERE DATE_FORMAT(date_inscription, '%Y-%m') = '" . date('Y-m') . "'")->fetchColumn();

// --- Graphique 1 : paiements collectifs mensuels (12 derniers mois) ---
$nomMoisFr = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin',
               '07'=>'Juil','08'=>'Août','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];

$moisLabels           = [];
$paiementsParMois     = [];
$inscriptionsParMois  = [];
$tontinesCreesParMois = [];

for ($i = 11; $i >= 0; $i--) {
    $timestamp = strtotime("-$i months");
    $mois      = date('Y-m', $timestamp);
    $mNum      = date('m', $timestamp);
    $annee     = date('y', $timestamp);
    $moisLabels[] = ($nomMoisFr[$mNum] ?? date('M', $timestamp)) . " '" . $annee;

    $req = $bd->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE DATE_FORMAT(date_paiement, '%Y-%m') = ? AND statut = 'paye'");
    $req->execute([$mois]);
    $paiementsParMois[] = (float)$req->fetchColumn();

    $req = $bd->prepare("SELECT COUNT(*) FROM utilisateurs WHERE DATE_FORMAT(date_inscription, '%Y-%m') = ?");
    $req->execute([$mois]);
    $inscriptionsParMois[] = (int)$req->fetchColumn();

    $req = $bd->prepare("SELECT COUNT(*) FROM tontines WHERE DATE_FORMAT(date_creation, '%Y-%m') = ?");
    $req->execute([$mois]);
    $tontinesCreesParMois[] = (int)$req->fetchColumn();
}

// --- Graphique 2 : Répartition des statuts des tontines (donut) ---
$statsTontines = [
    'active'    => $nbActives,
    'suspendue' => $nbSuspendues,
    'brouillon' => $nbBrouillon,
    'fermee'    => $nbFermees,
];

// --- Graphique 3 : Répartition des modes de paiement ---
$req = $bd->query("SELECT mode_paiement, COALESCE(SUM(montant),0) as total FROM paiements WHERE statut='paye' GROUP BY mode_paiement");
$modesData = $req->fetchAll(PDO::FETCH_ASSOC);
$modesLabels  = ['Wave', 'Orange Money', 'Espèces'];
$modesValeurs = [0, 0, 0];
foreach ($modesData as $m) {
    if ($m['mode_paiement'] === 'wave') {
        $modesValeurs[0] = (float)$m['total'];
    } elseif ($m['mode_paiement'] === 'orange_money') {
        $modesValeurs[1] = (float)$m['total'];
    } elseif ($m['mode_paiement'] === 'especes') {
        $modesValeurs[2] = (float)$m['total'];
    }
}

// --- Tableaux récents ---
$req = $bd->query('SELECT t.*, u.nom_complet AS nom_createur, 
                   (SELECT COUNT(*) FROM membres_tontine WHERE tontine_id = t.id) AS nb_membres 
                   FROM tontines t 
                   JOIN utilisateurs u ON u.id = t.createur_id 
                   ORDER BY t.date_creation DESC LIMIT 6');
$dernieresTontines = $req->fetchAll();

$req = $bd->query('SELECT * FROM utilisateurs ORDER BY date_inscription DESC LIMIT 6');
$derniersUtilisateurs = $req->fetchAll();

// Derniers paiements
$req = $bd->query("SELECT p.*, u.nom_complet AS nom_payeur, t.nom AS nom_tontine 
                   FROM paiements p 
                   JOIN utilisateurs u ON u.id = p.payeur_id 
                   JOIN tontines t ON t.id = p.tontine_id 
                   ORDER BY p.date_paiement DESC, p.id DESC LIMIT 8");
$derniersPaiements = $req->fetchAll();

$titrePage = 'Administration globale';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete">
    <div>
        <h1>Tableau de bord — Super Admin</h1>
        <p class="texte-secondaire">Vue globale en temps réel de la plateforme E-Tontine</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/export/export_csv.php?type=paiements" class="btn btn-secondaire" style="font-size:13px;">📥 Exporter</a>
        <a href="<?= APP_URL ?>/pages/super_admin/logs.php" class="btn btn-info" style="font-size:13px;">📋 Logs système</a>
    </div>
</div>

<!-- KPI principaux -->
<div class="grille-stats" style="margin-bottom:20px;">
    <div class="carte-stat carte-stat-succes">
        <div class="carte-stat-nombre"><?= formaterMontant($montantTotal) ?></div>
        <div class="carte-stat-label">Volume collecté (payé)</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= $nbTontines ?></div>
        <div class="carte-stat-label">Tontines totales</div>
    </div>
    <div class="carte-stat carte-stat-succes">
        <div class="carte-stat-nombre"><?= $nbActives ?></div>
        <div class="carte-stat-label">Tontines actives</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= $nbUtilisateurs ?></div>
        <div class="carte-stat-label">Utilisateurs inscrits</div>
    </div>
    <div class="carte-stat <?= $nbBloques > 0 ? 'carte-stat-alerte' : '' ?>">
        <div class="carte-stat-nombre"><?= $nbBloques ?></div>
        <div class="carte-stat-label">Comptes bloqués</div>
    </div>
    <div class="carte-stat <?= $nbSuspendues > 0 ? 'carte-stat-alerte' : '' ?>">
        <div class="carte-stat-nombre"><?= $nbSuspendues ?></div>
        <div class="carte-stat-label">Tontines suspendues</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= $nbPaiements ?></div>
        <div class="carte-stat-label">Paiements enregistrés</div>
    </div>
    <div class="carte-stat">
        <div class="carte-stat-nombre"><?= round($scoreMoyenGlobal, 1) ?>%</div>
        <div class="carte-stat-label">Fiabilité moyenne membres</div>
    </div>
</div>

<!-- Graphiques -->
<div class="grille-deux-colonnes" style="margin-bottom:20px;">

    <!-- Graphique 1: Évolution paiements + inscriptions sur 12 mois -->
    <section class="section-tableau">
        <div class="section-entete">
            <h2>📈 Évolution plateforme (12 mois)</h2>
        </div>
        <div style="padding:10px 14px 18px;">
            <canvas id="chartEvolution" style="width:100%; max-height:260px;"></canvas>
        </div>
    </section>

    <!-- Graphique 2 : Répartition statuts tontines + modes paiement -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <section class="section-tableau" style="flex:1; margin-bottom:0;">
            <div class="section-entete">
                <h2>🥧 Statuts des tontines</h2>
            </div>
            <div style="padding:10px 14px 18px; display:flex; justify-content:center;">
                <canvas id="chartStatuts" style="max-height:180px; max-width:280px;"></canvas>
            </div>
        </section>

        <section class="section-tableau" style="flex:1; margin-bottom:0;">
            <div class="section-entete">
                <h2>💳 Canaux de paiement</h2>
            </div>
            <div style="padding:10px 14px 18px; display:flex; justify-content:center;">
                <canvas id="chartCanaux" style="max-height:180px; max-width:280px;"></canvas>
            </div>
        </section>
    </div>

</div>

<!-- Actions d'export -->
<div class="actions-groupe" style="margin-bottom:20px; justify-content:flex-end; flex-wrap:wrap; gap:8px;">
    <a href="<?= APP_URL ?>/export/export_csv.php?type=tontines" class="btn btn-secondaire">📥 Tontines (CSV)</a>
    <a href="<?= APP_URL ?>/export/export_csv.php?type=utilisateurs" class="btn btn-secondaire">📥 Utilisateurs (CSV)</a>
    <a href="<?= APP_URL ?>/export/export_csv.php?type=paiements" class="btn btn-secondaire">📥 Paiements (CSV)</a>
    <a href="<?= APP_URL ?>/pages/super_admin/utilisateurs.php" class="btn btn-info">👥 Gérer les comptes</a>
</div>

<div class="grille-deux-colonnes" style="margin-bottom:20px;">

    <!-- Dernières tontines créées -->
    <section class="section-tableau">
        <div class="section-entete">
            <h2>Dernières tontines créées</h2>
            <a href="<?= APP_URL ?>/pages/super_admin/toutes_tontines.php" class="lien-secondaire">Tout voir</a>
        </div>
        <div class="table-conteneur">
            <table class="tableau-donnees">
                <thead>
                    <tr><th>Nom</th><th>Créateur</th><th>Membres</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($dernieresTontines as $t): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= $t['id'] ?>" style="color:var(--bleu-fonce);font-weight:600;"><?= htmlspecialchars($t['nom']) ?></a></td>
                        <td><?= htmlspecialchars($t['nom_createur']) ?></td>
                        <td><?= $t['nb_membres'] ?> / <?= $t['nombre_max_membres'] ?></td>
                        <td><?php
                            $cl = match ($t['statut']) {
                                'active'    => 'badge-succes',
                                'suspendue' => 'badge-alerte',
                                'fermee'    => 'badge-danger',
                                default     => 'badge-secondaire',
                            };
                        ?><span class="badge <?= $cl ?>"><?= htmlspecialchars(libelleStatutTontine($t['statut'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Derniers inscrits -->
    <section class="section-tableau">
        <div class="section-entete">
            <h2>Derniers inscrits <span class="badge badge-info" style="font-size:11px;"><?= $nbNouveauxCeMois ?> ce mois</span></h2>
            <a href="<?= APP_URL ?>/pages/super_admin/utilisateurs.php" class="lien-secondaire">Tout voir</a>
        </div>
        <div class="table-conteneur">
            <table class="tableau-donnees">
                <thead>
                    <tr><th>Nom</th><th>Email</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($derniersUtilisateurs as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nom_complet']) ?></td>
                        <td style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['est_bloque'] ? '<span class="badge badge-danger">Bloqué</span>' : '<span class="badge badge-succes">Actif</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<!-- Derniers paiements -->
<section class="section-tableau" style="margin-bottom:24px;">
    <div class="section-entete">
        <h2>💰 Historique des paiements récents</h2>
        <span class="texte-secondaire" style="font-size:12px;">8 dernières transactions</span>
    </div>
    <div class="table-conteneur">
        <table class="tableau-donnees">
            <thead>
                <tr><th>Membre</th><th>Tontine</th><th>Montant</th><th>Mode</th><th>Date</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php foreach ($derniersPaiements as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nom_payeur']) ?></td>
                    <td><?= htmlspecialchars($p['nom_tontine']) ?></td>
                    <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                    <td>
                        <?php
                        $modeIcon = match ($p['mode_paiement']) {
                            'wave'         => '🌊 Wave',
                            'orange_money' => '🟠 Orange',
                            'especes'      => '💵 Espèces',
                            default        => ucfirst($p['mode_paiement'] ?? '')
                        };
                        ?>
                        <span style="font-size:12px;"><?= htmlspecialchars($modeIcon) ?></span>
                    </td>
                    <td style="font-size:12px; color:var(--gris-sec);"><?= formaterDate($p['date_paiement']) ?></td>
                    <td>
                        <?php if ($p['statut'] === 'paye'): ?>
                            <span class="badge badge-succes">Payé</span>
                        <?php elseif ($p['statut'] === 'en_attente'): ?>
                            <span class="badge badge-alerte">En attente</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Retard</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($derniersPaiements)): ?>
                <tr><td colspan="6">
                    <div class="etat-vide"><p>Aucun paiement enregistré pour le moment.</p></div>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const chartDefaults = {
        tooltipBg:   '#29150b',
        tooltipTxt:  '#fdfbf7',
        tooltipBody: '#f7ead9',
        gridColor:   'rgba(227, 214, 196, 0.35)',
        tickColor:   '#8c7a65',
        legendColor: '#4a2e1f',
        fontFamily:  "'Plus Jakarta Sans', sans-serif"
    };

    // ─── Graphique 1 : Évolution plateforme (ligne multi-séries) ─────────────
    const ctx1 = document.getElementById('chartEvolution');
    if (ctx1) {
        new Chart(ctx1.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($moisLabels) ?>,
                datasets: [
                    {
                        label: 'Volume collecté (FCFA)',
                        data: <?= json_encode($paiementsParMois) ?>,
                        borderColor: '#c1602e',
                        backgroundColor: 'rgba(193, 96, 46, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.38,
                        fill: true,
                        yAxisID: 'yLeft',
                        pointBackgroundColor: '#c1602e',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Nouveaux inscrits',
                        data: <?= json_encode($inscriptionsParMois) ?>,
                        borderColor: '#1d9e75',
                        backgroundColor: 'rgba(29, 158, 117, 0.08)',
                        borderWidth: 2,
                        tension: 0.38,
                        fill: false,
                        yAxisID: 'yRight',
                        pointBackgroundColor: '#1d9e75',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Tontines créées',
                        data: <?= json_encode($tontinesCreesParMois) ?>,
                        borderColor: '#e0a63e',
                        borderDash: [5, 4],
                        borderWidth: 2,
                        tension: 0.38,
                        fill: false,
                        yAxisID: 'yRight',
                        pointBackgroundColor: '#e0a63e',
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: chartDefaults.legendColor,
                            font: { family: chartDefaults.fontFamily, size: 12, weight: '600' },
                            usePointStyle: true, boxWidth: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: chartDefaults.tooltipBg,
                        titleColor: chartDefaults.tooltipTxt,
                        bodyColor:  chartDefaults.tooltipBody,
                        padding: 10, cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                if (ctx.dataset.yAxisID === 'yLeft') {
                                    return ctx.dataset.label + ' : ' + Number(ctx.raw || 0).toLocaleString('fr-FR') + ' FCFA';
                                }
                                return ctx.dataset.label + ' : ' + ctx.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: chartDefaults.gridColor },
                        ticks: { color: chartDefaults.tickColor, font: { family: chartDefaults.fontFamily, size: 11 } }
                    },
                    yLeft: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        grid: { color: chartDefaults.gridColor },
                        ticks: {
                            color: chartDefaults.tickColor,
                            font: { family: chartDefaults.fontFamily, size: 11 },
                            callback: v => v >= 1000 ? (v / 1000) + 'k' : v
                        }
                    },
                    yRight: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: chartDefaults.tickColor,
                            font: { family: chartDefaults.fontFamily, size: 11 },
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // ─── Graphique 2 : Statuts tontines (donut) ──────────────────────────────
    const ctx2 = document.getElementById('chartStatuts');
    if (ctx2) {
        new Chart(ctx2.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Actives', 'Suspendues', 'Brouillon', 'Fermées'],
                datasets: [{
                    data: <?= json_encode(array_values($statsTontines)) ?>,
                    backgroundColor: ['#1d9e75', '#ba7517', '#8c7a65', '#e24b4a'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: chartDefaults.legendColor,
                            font: { family: chartDefaults.fontFamily, size: 12, weight: '600' },
                            usePointStyle: true, boxWidth: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: chartDefaults.tooltipBg,
                        titleColor: chartDefaults.tooltipTxt,
                        bodyColor:  chartDefaults.tooltipBody,
                        padding: 10, cornerRadius: 8
                    }
                }
            }
        });
    }

    // ─── Graphique 3 : Canaux de paiement (donut) ────────────────────────────
    const ctx3 = document.getElementById('chartCanaux');
    if (ctx3) {
        new Chart(ctx3.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($modesLabels) ?>,
                datasets: [{
                    data: <?= json_encode($modesValeurs) ?>,
                    backgroundColor: ['#1d9e75', '#d9762f', '#8c7a65'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: chartDefaults.legendColor,
                            font: { family: chartDefaults.fontFamily, size: 12, weight: '600' },
                            usePointStyle: true, boxWidth: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: chartDefaults.tooltipBg,
                        titleColor: chartDefaults.tooltipTxt,
                        bodyColor:  chartDefaults.tooltipBody,
                        padding: 10, cornerRadius: 8,
                        callbacks: {
                            label: c => c.label + ' : ' + Number(c.raw || 0).toLocaleString('fr-FR') + ' FCFA'
                        }
                    }
                }
            }
        });
    }

});
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>