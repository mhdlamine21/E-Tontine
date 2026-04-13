<?php
//  EXPORT - REÇU PDF D'UN PAIEMENT
//  Utilise TCPDF : composer require tecnickcom/tcpdf
//  Ou mPDF  : composer require mpdf/mpdf
//  Ici on génère du HTML propre à imprimer (solution sans lib externe)
require_once __DIR__ . '/../configuration/session.php';
require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../fonctions/paiement.php';
require_once __DIR__ . '/../fonctions/tontine.php';
require_once __DIR__ . '/../fonctions/aide.php';

exigerConnexion();
$paiementId    = obtenirGetInt('paiement');
$utilisateurId = idUtilisateurConnecte();

$paiement = obtenirPaiement($paiementId);
if (!$paiement || ((int)$paiement['payeur_id'] !== $utilisateurId && !estAdminDeTontine($utilisateurId, (int)$paiement['tontine_id']) && !estSuperAdmin())) {
    redirigerAvecMessage(APP_URL . '/pages/tableau_de_bord/accueil.php', 'erreur', 'Accès refusé.');
}

$tontine = obtenirTontine((int)$paiement['tontine_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu - <?= htmlspecialchars($paiement['reference']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; background: #f5f5f5; }
        .recu { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .recu-entete { background: #c1602e; color: #fff; padding: 24px 28px; }
        .recu-entete h1 { font-size: 22px; font-weight: 700; }
        .recu-entete p { font-size: 13px; opacity: .85; margin-top: 4px; }
        .recu-corps { padding: 28px; }
        .recu-ref { background: #f7ead9; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .recu-ref-titre { font-size: 11px; color: #c1602e; text-transform: uppercase; letter-spacing: .5px; }
        .recu-ref-valeur { font-size: 18px; font-weight: 700; color: #4a2e1f; margin-top: 2px; }
        .recu-lignes { border: 1px solid #e0e0e0; border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
        .recu-ligne { display: flex; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid #f0f0f0; }
        .recu-ligne:last-child { border-bottom: none; }
        .recu-ligne-label { color: #666; }
        .recu-ligne-valeur { font-weight: 500; text-align: right; }
        .recu-montant { background: #0F6E56; color: #fff; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .recu-montant-label { font-size: 13px; opacity: .85; }
        .recu-montant-valeur { font-size: 26px; font-weight: 700; }
        .recu-simulation { background: #fff8e1; border: 1px solid #ffc107; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #856404; margin-bottom: 20px; }
        .recu-pied { text-align: center; padding: 16px; border-top: 1px solid #f0f0f0; font-size: 11px; color: #999; }
        .badge-succes { background: #EAF3DE; color: #27500A; padding: 3px 8px; border-radius: 12px; font-size: 11px; }
        @media print {
            body { background: #fff; }
            .recu { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
            .btn-imprimer { display: none !important; }
        }
        .btn-actions { display: flex; gap: 8px; justify-content: center; padding: 16px; }
        .btn { padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-principal { background: #c1602e; color: #fff; }
        .btn-secondaire { background: #fff; color: #c1602e; border: 1px solid #c1602e; }
    </style>
</head>
<body>

<div class="recu">
    <div class="recu-entete">
        <h1>E-Tontine</h1>
        <p>Reçu de paiement officiel</p>
    </div>

    <div class="recu-corps">

        <div class="recu-ref">
            <div class="recu-ref-titre">Référence du paiement</div>
            <div class="recu-ref-valeur"><?= htmlspecialchars($paiement['reference']) ?></div>
        </div>

        <div class="recu-montant">
            <div class="recu-montant-label">Montant payé</div>
            <div class="recu-montant-valeur"><?= formaterMontant((float)$paiement['montant']) ?></div>
        </div>

        <div class="recu-lignes">
            <div class="recu-ligne">
                <span class="recu-ligne-label">Tontine</span>
                <span class="recu-ligne-valeur"><?= htmlspecialchars($tontine['nom']) ?></span>
            </div>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Cycle n°</span>
                <span class="recu-ligne-valeur"><?= $paiement['numero_cycle'] ?></span>
            </div>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Payé par</span>
                <span class="recu-ligne-valeur"><?= htmlspecialchars($paiement['nom_payeur']) ?></span>
            </div>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Pour le compte de</span>
                <span class="recu-ligne-valeur"><?= htmlspecialchars($paiement['nom_beneficiaire']) ?></span>
            </div>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Mode de paiement</span>
                <span class="recu-ligne-valeur"><?= libelleModePaiement($paiement['mode_paiement']) ?></span>
            </div>
            <?php if ($paiement['numero_telephone']): ?>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Numéro</span>
                <span class="recu-ligne-valeur"><?= htmlspecialchars($paiement['numero_telephone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Date et heure</span>
                <span class="recu-ligne-valeur"><?= formaterDateHeure($paiement['date_paiement']) ?></span>
            </div>
            <div class="recu-ligne">
                <span class="recu-ligne-label">Statut</span>
                <span class="recu-ligne-valeur"><span class="badge-succes">Confirmé</span></span>
            </div>
        </div>

        <div class="recu-simulation">
            Paiement simulé à des fins de démonstration. Aucun montant réel n'a été débité.
        </div>

    </div>

    <div class="btn-actions btn-imprimer">
        <button class="btn btn-principal" onclick="window.print()">Imprimer</button>
        <a href="javascript:history.back()" class="btn btn-secondaire">Retour</a>
    </div>

    <div class="recu-pied">
        <?= APP_NOM ?> &copy; <?= date('Y') ?> - Ce reçu fait foi de paiement au sein de votre tontine.
    </div>
</div>

</body>
</html>
