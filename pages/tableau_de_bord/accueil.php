<?php
// Page d'accueil utilisateur - Tableau de bord enrichi E-Tontine.
require_once __DIR__ . '/../../configuration/session.php';
require_once __DIR__ . '/../../configuration/constantes.php';
require_once __DIR__ . '/../../configuration/base_de_donnees.php';
require_once __DIR__ . '/../../fonctions/tontine.php';
require_once __DIR__ . '/../../fonctions/cotisation.php';
require_once __DIR__ . '/../../fonctions/utilisateur.php';
require_once __DIR__ . '/../../fonctions/aide.php';

exigerConnexion();
verifierExpirationSession();

// Rediriger le super admin vers son tableau de bord dédié
if (estSuperAdmin()) {
    header('Location: ' . APP_URL . '/pages/super_admin/tableau_de_bord.php');
    exit;
}

$utilisateurId  = idUtilisateurConnecte();
$utilisateur    = obtenirUtilisateurParId($utilisateurId);
$bd             = connexionBD();

// Tontines de l'utilisateur
$tontinesAdmin  = tontinesAdminDe($utilisateurId);
$tontinesMembre = tontinesMembreDe($utilisateurId);
$toutesTontines = array_merge($tontinesAdmin, $tontinesMembre);

usort($toutesTontines, static function (array $a, array $b): int {
    $ta = strtotime((string)($a['date_creation'] ?? '1970-01-01')) ?: 0;
    $tb = strtotime((string)($b['date_creation'] ?? '1970-01-01')) ?: 0;
    return $tb <=> $ta;
});

$nbAdmin     = count($tontinesAdmin);
$nbAdhesions = count($tontinesMembre);
$nbTotal     = count($toutesTontines);

// 1. Indicateurs financiers réels de l'utilisateur
$req = $bd->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE payeur_id = ? AND statut = 'paye'");
$req->execute([$utilisateurId]);
$totalCotise = (float)$req->fetchColumn();

$req = $bd->prepare("SELECT COALESCE(SUM(montant_recu), 0) FROM distributions WHERE beneficiaire_id = ? AND statut IN ('partiel', 'complet')");
$req->execute([$utilisateurId]);
$totalCagnottesRecues = (float)$req->fetchColumn();

$req = $bd->prepare("SELECT COUNT(*), COALESCE(SUM(montant), 0) FROM paiements WHERE payeur_id = ? AND statut = 'en_attente'");
$req->execute([$utilisateurId]);
$attenteRow = $req->fetch(PDO::FETCH_NUM);
$nbPaiementsEnAttente = (int)($attenteRow[0] ?? 0);
$montantEnAttente     = (float)($attenteRow[1] ?? 0);

$req = $bd->prepare("SELECT AVG(score_fiabilite) FROM membres_tontine WHERE utilisateur_id = ?");
$req->execute([$utilisateurId]);
$scoreMoyen = $req->fetchColumn();
$scoreMoyen = $scoreMoyen !== null ? round((float)$scoreMoyen, 1) : 100.0;

// 2. Données si le membre est également gestionnaire / propriétaire de tontines
$fondsCollectesAdmin = 0.0;
$membresActifsAdmin  = 0;
$urgencesAdmin       = 0;

if ($nbAdmin > 0) {
    $req = $bd->prepare("SELECT COALESCE(SUM(p.montant), 0) 
                         FROM paiements p 
                         JOIN tontines t ON t.id = p.tontine_id 
                         WHERE t.createur_id = ? AND p.statut = 'paye'");
    $req->execute([$utilisateurId]);
    $fondsCollectesAdmin = (float)$req->fetchColumn();

    $req = $bd->prepare("SELECT COUNT(DISTINCT m.utilisateur_id) 
                         FROM membres_tontine m 
                         JOIN tontines t ON t.id = m.tontine_id 
                         WHERE t.createur_id = ? AND m.statut = 'actif'");
    $req->execute([$utilisateurId]);
    $membresActifsAdmin = (int)$req->fetchColumn();

    $req = $bd->prepare("SELECT COUNT(*) 
                         FROM demandes_urgence du 
                         JOIN tontines t ON t.id = du.tontine_id 
                         WHERE t.createur_id = ? AND du.statut = 'en_attente'");
    $req->execute([$utilisateurId]);
    $urgencesAdmin = (int)$req->fetchColumn();
}

// 3. Historique sur les 6 derniers mois pour les courbes
$moisLabels           = [];
$moisCotisationsPerso = [];
$moisCagnottesPerso   = [];
$moisCollectesAdmin   = [];

$nomMoisFr = [
    '01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr',
    '05' => 'Mai', '06' => 'Juin', '07' => 'Juil', '08' => 'Août',
    '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'
];

for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime("-$i months");
    $cleMois   = date('Y-m', $timestamp);
    $mNum      = date('m', $timestamp);
    $annee     = date('y', $timestamp);
    $moisLabels[] = ($nomMoisFr[$mNum] ?? date('M', $timestamp)) . " '" . $annee;

    // Cotisations versées par l'utilisateur
    $req = $bd->prepare("SELECT COALESCE(SUM(montant), 0) 
                         FROM paiements 
                         WHERE payeur_id = ? 
                           AND DATE_FORMAT(date_paiement, '%Y-%m') = ? 
                           AND statut = 'paye'");
    $req->execute([$utilisateurId, $cleMois]);
    $moisCotisationsPerso[] = (float)$req->fetchColumn();

    // Cagnottes perçues par l'utilisateur
    $req = $bd->prepare("SELECT COALESCE(SUM(montant_recu), 0) 
                         FROM distributions 
                         WHERE beneficiaire_id = ? 
                           AND DATE_FORMAT(date_distribution, '%Y-%m') = ? 
                           AND statut IN ('partiel', 'complet')");
    $req->execute([$utilisateurId, $cleMois]);
    $moisCagnottesPerso[] = (float)$req->fetchColumn();

    // Si admin : fonds collectés dans ses tontines
    if ($nbAdmin > 0) {
        $req = $bd->prepare("SELECT COALESCE(SUM(p.montant), 0) 
                             FROM paiements p 
                             JOIN tontines t ON t.id = p.tontine_id 
                             WHERE t.createur_id = ? 
                               AND DATE_FORMAT(p.date_paiement, '%Y-%m') = ? 
                               AND p.statut = 'paye'");
        $req->execute([$utilisateurId, $cleMois]);
        $moisCollectesAdmin[] = (float)$req->fetchColumn();
    }
}

// 4. Répartition des modes de paiement
$req = $bd->prepare("SELECT mode_paiement, COUNT(*) as nb, COALESCE(SUM(montant), 0) as total 
                     FROM paiements 
                     WHERE payeur_id = ? AND statut = 'paye' 
                     GROUP BY mode_paiement");
$req->execute([$utilisateurId]);
$modesPaiementData = $req->fetchAll(PDO::FETCH_ASSOC);

$modesLabels  = ['Wave', 'Orange Money', 'Espèces'];
$modesValeurs = [0, 0, 0];

foreach ($modesPaiementData as $m) {
    if ($m['mode_paiement'] === 'wave') {
        $modesValeurs[0] += (float)$m['total'];
    } elseif ($m['mode_paiement'] === 'orange_money') {
        $modesValeurs[1] += (float)$m['total'];
    } elseif ($m['mode_paiement'] === 'especes') {
        $modesValeurs[2] += (float)$m['total'];
    }
}

// Si l'utilisateur n'a pas encore de paiements directs, vérifier s'il est admin pour afficher les flux de ses tontines
if (array_sum($modesValeurs) === 0.0 && $nbAdmin > 0) {
    $req = $bd->prepare("SELECT p.mode_paiement, COUNT(*) as nb, COALESCE(SUM(p.montant), 0) as total 
                         FROM paiements p 
                         JOIN tontines t ON t.id = p.tontine_id 
                         WHERE t.createur_id = ? AND p.statut = 'paye' 
                         GROUP BY p.mode_paiement");
    $req->execute([$utilisateurId]);
    $adminModes = $req->fetchAll(PDO::FETCH_ASSOC);
    foreach ($adminModes as $m) {
        if ($m['mode_paiement'] === 'wave') {
            $modesValeurs[0] += (float)$m['total'];
        } elseif ($m['mode_paiement'] === 'orange_money') {
            $modesValeurs[1] += (float)$m['total'];
        } elseif ($m['mode_paiement'] === 'especes') {
            $modesValeurs[2] += (float)$m['total'];
        }
    }
}

// 5. Dernières transactions réelles
$req = $bd->prepare("SELECT p.*, t.nom AS nom_tontine 
                     FROM paiements p 
                     JOIN tontines t ON t.id = p.tontine_id 
                     WHERE p.payeur_id = ? 
                     ORDER BY p.date_paiement DESC, p.id DESC 
                     LIMIT 5");
$req->execute([$utilisateurId]);
$derniersPaiements = $req->fetchAll(PDO::FETCH_ASSOC);

$titrePage = 'Tableau de bord';
require_once __DIR__ . '/../../gabarits/entete.php';
?>

<div class="page-entete page-entete--accueil">
    <div>
        <h1>Tableau de bord</h1>
        <p class="accueil-soustitre">
            Bonjour, <strong><?= htmlspecialchars($utilisateur['nom_complet'] ?? 'Membre') ?></strong> 👋 Suivez vos cotisations, cagnottes et tontines en temps réel.
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button type="button" class="btn btn-secondaire" onclick="ouvrirGuide('creer')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
            </svg>
            Guides d'utilisation
        </button>
        <a href="<?= APP_URL ?>/pages/tontines/creer.php" class="btn btn-principal">+ Créer une tontine</a>
    </div>
</div>

<section class="dashboard-premium accueil-dashboard">

    <!-- KPI / Statistiques Financières & Statut -->
    <div class="grille-stats accueil-stats">
        <div class="carte-stat">
            <div class="carte-stat-nombre"><?= formaterMontant($totalCotise) ?></div>
            <div class="carte-stat-label">Total cotisé versé</div>
        </div>
        <div class="carte-stat carte-stat-succes">
            <div class="carte-stat-nombre"><?= formaterMontant($totalCagnottesRecues) ?></div>
            <div class="carte-stat-label">Total cagnottes reçues</div>
        </div>
        <div class="carte-stat <?= $nbPaiementsEnAttente > 0 ? 'carte-stat-alerte' : '' ?>">
            <div class="carte-stat-nombre"><?= $nbPaiementsEnAttente ?></div>
            <div class="carte-stat-label">
                Paiements en attente <?= $montantEnAttente > 0 ? '(' . formaterMontant($montantEnAttente) . ')' : '' ?>
            </div>
        </div>
        <div class="carte-stat">
            <div class="carte-stat-nombre">
                <?= $scoreMoyen ?>%
            </div>
            <div class="carte-stat-label">
                Score de ponctualité moyen
            </div>
        </div>
        <?php if ($nbAdmin > 0): ?>
        <div class="carte-stat carte-stat-info">
            <div class="carte-stat-nombre"><?= formaterMontant($fondsCollectesAdmin) ?></div>
            <div class="carte-stat-label">Fonds gérés (<?= $nbAdmin ?> tontine<?= $nbAdmin > 1 ? 's' : '' ?> admin)</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Section Guides Pratiques Interactifs (Créer, Rejoindre, Urgence, Vote) -->
    <div class="section-entete" style="margin-bottom:14px;">
        <h2 style="font-size:18px; font-weight:700; color:var(--bleu-fonce); display:flex; align-items:center; gap:8px;">
            <span>📖</span> Guides et fonctionnalités clés
        </h2>
    </div>

    <div class="grille-guides-rapides">
        <div class="carte-guide-rapide">
            <div class="carte-guide-rapide__icone">✨</div>
            <div>
                <h3 class="carte-guide-rapide__titre">Créer une tontine</h3>
                <p class="carte-guide-rapide__desc">Fixez le montant, la périodicité (hebdomadaire, mensuelle) et l’ordre des bénéficiaires.</p>
            </div>
            <button type="button" class="carte-guide-rapide__btn" onclick="ouvrirGuide('creer')">
                Lire le guide &rarr;
            </button>
        </div>

        <div class="carte-guide-rapide">
            <div class="carte-guide-rapide__icone">🤝</div>
            <div>
                <h3 class="carte-guide-rapide__titre">Rejoindre un groupe</h3>
                <p class="carte-guide-rapide__desc">Utilisez un code ou un lien d'invitation sécurisé partagé par l’administrateur pour adhérer.</p>
            </div>
            <button type="button" class="carte-guide-rapide__btn" onclick="ouvrirGuide('rejoindre')">
                Lire le guide &rarr;
            </button>
        </div>

        <div class="carte-guide-rapide">
            <div class="carte-guide-rapide__icone">🚨</div>
            <div>
                <h3 class="carte-guide-rapide__titre">Demander une urgence</h3>
                <p class="carte-guide-rapide__desc">Besoin immédiat de fonds ? Sollicitez un échange anticipé de votre tour avec validation.</p>
            </div>
            <button type="button" class="carte-guide-rapide__btn" onclick="ouvrirGuide('urgence')">
                Lire le guide &rarr;
            </button>
        </div>

        <div class="carte-guide-rapide">
            <div class="carte-guide-rapide__icone">🗳️</div>
            <div>
                <h3 class="carte-guide-rapide__titre">Pétition & Vote</h3>
                <p class="carte-guide-rapide__desc">Déclenchez une consultation démocratique pour exclure un mauvais payeur ou changer les règles.</p>
            </div>
            <button type="button" class="carte-guide-rapide__btn" onclick="ouvrirGuide('vote')">
                Lire le guide &rarr;
            </button>
        </div>
    </div>

    <!-- Graphiques et Courbes Financières Réelles -->
    <div class="grille-graphiques-accueil">
        
        <!-- Courbe des Flux Financiers (6 Mois) -->
        <section class="section-tableau" style="margin-bottom:0;">
            <div class="section-entete">
                <h2>📈 Évolution financière (6 derniers mois)</h2>
                <span class="texte-secondaire" style="font-size:12px;">En FCFA</span>
            </div>
            <div style="padding: 10px 14px 18px;">
                <canvas id="chartFluxFinanciers" style="width:100%; max-height:280px;"></canvas>
            </div>
        </section>

        <!-- Répartition des Canaux de Paiement -->
        <section class="section-tableau" style="margin-bottom:0;">
            <div class="section-entete">
                <h2>🥧 Canaux de paiement</h2>
            </div>
            <div style="padding: 10px 14px 18px; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:280px;">
                <?php if (array_sum($modesValeurs) > 0): ?>
                    <canvas id="chartCanauxPaiement" style="width:100%; max-height:220px;"></canvas>
                <?php else: ?>
                    <div class="etat-vide" style="padding:20px 10px;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--bleu-moy);margin-bottom:10px">
                            <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                        </svg>
                        <p style="font-size:13px; margin-bottom:8px;">Aucun versement enregistré pour le moment.</p>
                        <span class="texte-secondaire" style="font-size:12px;">Le graphique affichera la proportion Wave, Orange Money et Espèces dès vos premiers paiements.</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <!-- Mes Tontines & Dernières Transactions -->
    <div class="grille-deux-colonnes" style="margin-top:24px;">

        <!-- Tableau des Tontines -->
        <section class="section-tableau">
            <div class="section-entete">
                <h2>Mes tontines (<?= $nbTotal ?>)</h2>
                <a href="<?= APP_URL ?>/pages/tontines/mes_tontines.php" class="lien-secondaire">Voir mes tontines</a>
            </div>
            <div class="table-conteneur">
                <table class="tableau-donnees">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Cotisation</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($toutesTontines, 0, 5) as $t): ?>
                        <tr>
                            <td>
                                <strong><a href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= (int)$t['id'] ?>" style="color:var(--bleu-fonce);"><?= htmlspecialchars($t['nom']) ?></a></strong>
                                <?php if ((int)$t['createur_id'] === $utilisateurId): ?>
                                    <span class="badge badge-info" style="font-size:10px; margin-left:4px;">Admin</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formaterMontant((float)$t['montant_cotisation']) ?></td>
                            <td><?php
                                $classeBadge = match ($t['statut']) {
                                    TONTINE_ACTIVE    => 'badge-succes',
                                    TONTINE_SUSPENDUE  => 'badge-alerte',
                                    TONTINE_FERMEE     => 'badge-danger',
                                    default            => 'badge-secondaire',
                                };
                            ?><span class="badge <?= $classeBadge ?>"><?= htmlspecialchars(libelleStatutTontine($t['statut'])) ?></span></td>
                            <td>
                                <a class="btn btn-principal btn-tres-petit" href="<?= APP_URL ?>/pages/tontines/voir.php?id=<?= (int)$t['id'] ?>">Consulter</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($toutesTontines)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="etat-vide">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" style="color:var(--bleu-moy);margin-bottom:8px">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M12 8v4l3 2"/>
                                    </svg>
                                    <p>Vous n’avez encore rejoint ou créé aucune tontine.</p>
                                    <a href="<?= APP_URL ?>/pages/tontines/creer.php" class="btn btn-principal btn-petit">+ Créer ma première tontine</a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Dernières Transactions -->
        <section class="section-tableau">
            <div class="section-entete">
                <h2>Dernières transactions</h2>
                <span class="texte-secondaire" style="font-size:12px;">Historique vérifié</span>
            </div>
            <div class="table-conteneur">
                <table class="tableau-donnees">
                    <thead>
                        <tr>
                            <th>Tontine</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($derniersPaiements as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nom_tontine']) ?></td>
                            <td><strong><?= formaterMontant((float)$p['montant']) ?></strong></td>
                            <td>
                                <?php
                                $modeNom = match ($p['mode_paiement']) {
                                    'wave'         => 'Wave',
                                    'orange_money' => 'Orange Money',
                                    'especes'      => 'Espèces',
                                    default        => ucfirst($p['mode_paiement'] ?? 'Autre')
                                };
                                ?>
                                <span class="badge badge-secondaire"><?= htmlspecialchars($modeNom) ?></span>
                            </td>
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
                        <tr>
                            <td colspan="4">
                                <div class="etat-vide" style="padding:26px 14px;">
                                    <p style="color:var(--gris-sec); margin:0;">Aucune transaction enregistrée récemment.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

</section>

<!-- Inclusion de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Graphique des flux financiers
    const ctxFlux = document.getElementById('chartFluxFinanciers');
    if (ctxFlux) {
        const datasets = [
            {
                label: 'Cotisations versées',
                data: <?= json_encode($moisCotisationsPerso) ?>,
                borderColor: '#c1602e',
                backgroundColor: 'rgba(193, 96, 46, 0.12)',
                borderWidth: 2.5,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#c1602e',
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Cagnottes reçues',
                data: <?= json_encode($moisCagnottesPerso) ?>,
                borderColor: '#e0a63e',
                backgroundColor: 'rgba(224, 166, 62, 0.12)',
                borderWidth: 2.5,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#e0a63e',
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ];

        <?php if ($nbAdmin > 0): ?>
        datasets.push({
            label: 'Collectes administrées',
            data: <?= json_encode($moisCollectesAdmin) ?>,
            borderColor: '#1d9e75',
            backgroundColor: 'rgba(29, 158, 117, 0.08)',
            borderWidth: 2,
            borderDash: [5, 5],
            tension: 0.35,
            fill: false,
            pointBackgroundColor: '#1d9e75',
            pointRadius: 3,
            pointHoverRadius: 5
        });
        <?php endif; ?>

        new Chart(ctxFlux.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($moisLabels) ?>,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#4a2e1f',
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '600' },
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: '#29150b',
                        titleColor: '#fdfbf7',
                        bodyColor: '#f7ead9',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ' : ' + Number(context.raw || 0).toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(227, 214, 196, 0.35)' },
                        ticks: { color: '#8c7a65', font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(227, 214, 196, 0.35)' },
                        ticks: {
                            color: '#8c7a65',
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                            callback: function(value) {
                                return value >= 1000 ? (value / 1000) + 'k' : value;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Graphique des canaux de paiement
    const ctxCanaux = document.getElementById('chartCanauxPaiement');
    <?php if (array_sum($modesValeurs) > 0): ?>
    if (ctxCanaux) {
        new Chart(ctxCanaux.getContext('2d'), {
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
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#4a2e1f',
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '600' },
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: '#29150b',
                        titleColor: '#fdfbf7',
                        bodyColor: '#f7ead9',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return context.label + ' : ' + Number(context.raw || 0).toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../gabarits/pied_de_page.php'; ?>