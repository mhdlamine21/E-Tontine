<?php


require_once __DIR__ . '/../configuration/constantes.php';
require_once __DIR__ . '/../configuration/env.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Charger le .env s'il n'a pas déjà été chargé (idempotent : ne surcharge jamais une variable déjà définie)
chargerEnvDepuisFichier(__DIR__ . '/../.env');

define('SMTP_HOST', getenv('SMTP_HOST') !== false ? getenv('SMTP_HOST') : 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') !== false ? (int)getenv('SMTP_PORT') : 587);
define('SMTP_USER', getenv('SMTP_USER') !== false ? getenv('SMTP_USER') : '');
define('SMTP_PASS', getenv('SMTP_PASS') !== false ? getenv('SMTP_PASS') : '');
define('SMTP_FROM', getenv('SMTP_FROM') !== false ? getenv('SMTP_FROM') : 'no-reply@e-tontine.local');
define('SMTP_FROM_NAME', 'E-Tontine');

function envoyerEmail(string $destinataire, string $nomDestinataire, string $sujet, string $corpsHtml): bool {
    if (SMTP_USER === '' || SMTP_PASS === '') {
        // Pas de configuration SMTP en environnement local/démo : on n'envoie rien,
        // mais on ne bloque jamais le flux applicatif (paiement, urgence, etc.).
        error_log('Email non envoyé (SMTP_USER/SMTP_PASS absents de .env) : ' . $sujet . ' -> ' . $destinataire);
        return false;
    }

    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Support local SSL/TLS sur Windows sans certificat racine installé
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Expéditeur (si SMTP_FROM est factice ou par défaut, utiliser le compte SMTP authentifié)
        $expediteur = (SMTP_FROM !== '' && filter_var(SMTP_FROM, FILTER_VALIDATE_EMAIL) && !str_ends_with(SMTP_FROM, '.local'))
            ? SMTP_FROM
            : SMTP_USER;
        $mail->setFrom($expediteur, SMTP_FROM_NAME);
        $mail->addAddress($destinataire, $nomDestinataire);
        
        // ✅ Correction de l'encodage UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'quoted-printable';
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $corpsHtml;
        $mail->AltBody = strip_tags($corpsHtml);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi email : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Email de réinitialisation de mot de passe
 */
function envoyerEmailReinitialisation(string $email, string $nom, string $lienReinitialisation): void {
    $sujet = "Réinitialisation de votre mot de passe E-Tontine";
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Réinitialisation de mot de passe</h1>
        <p>Bonjour " . htmlspecialchars($nom) . ",</p>
        <p>Vous avez demandé la réinitialisation de votre mot de passe E-Tontine.</p>
        <p><a href='" . $lienReinitialisation . "'>Choisir un nouveau mot de passe →</a></p>
        <p>Ce lien expire dans 1 heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email de bienvenue après inscription
 */
function envoyerEmailBienvenue(string $email, string $nom): void {
    $sujet = "Bienvenue sur E-Tontine !";
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Bienvenue " . htmlspecialchars($nom) . " !</h1>
        <p>Nous sommes ravis de vous accueillir sur <strong>E-Tontine</strong>, la plateforme de gestion de tontines moderne.</p>
        <p>Vous pouvez dès maintenant :</p>
        <ul>
            <li>Créer votre propre tontine</li>
            <li>Rejoindre des tontines via invitation</li>
            <li>Gérer vos cotisations et votre cagnotte</li>
        </ul>
        <p><a href='" . APP_URL . "/pages/tableau_de_bord/accueil.php'>Accéder à mon tableau de bord →</a></p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email d'invitation à rejoindre une tontine
 */
function envoyerEmailInvitation(string $email, string $nom, string $tontineNom, string $lienInvitation): void {
    $sujet = "Invitation à rejoindre la tontine " . $tontineNom;
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Invitation à rejoindre une tontine</h1>
        <p>Bonjour,</p>
        <p><strong>" . htmlspecialchars($nom) . "</strong> vous invite à rejoindre la tontine <strong>" . htmlspecialchars($tontineNom) . "</strong>.</p>
        <p>Cliquez sur le lien ci-dessous pour accepter l'invitation :</p>
        <p><a href='" . $lienInvitation . "'>" . $lienInvitation . "</a></p>
        <p>Ce lien expire dans 7 jours.</p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email de rappel de paiement
 */
function envoyerEmailRappelPaiement(string $email, string $nom, string $tontineNom, int $tontineId, int $cycleNumero, string $dateLimite, float $montant): void {
    $sujet = "Rappel : Paiement de cotisation dû";
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Rappel de paiement</h1>
        <p>Bonjour " . htmlspecialchars($nom) . ",</p>
        <p>Vous n'avez pas encore payé votre cotisation pour la tontine <strong>" . htmlspecialchars($tontineNom) . "</strong>.</p>
        <p><strong>Cycle n°" . $cycleNumero . "</strong><br>
        Montant : " . number_format($montant, 0, ',', ' ') . " FCFA<br>
        Date limite : " . formaterDate($dateLimite) . "</p>
        <p><a href='" . APP_URL . "/pages/cotisations/payer.php?tontine=" . $tontineId . "'>Payer maintenant →</a></p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email pour nouveau cycle (bénéficiaire)
 */
function envoyerEmailNouveauCycle(string $email, string $nom, string $tontineNom, int $cycleNumero, string $dateLimite, float $cagnotteTheorique): void {
    $sujet = "Nouveau cycle démarré - C'est votre tour !";
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Nouveau cycle de cotisation</h1>
        <p>Bonjour " . htmlspecialchars($nom) . ",</p>
        <p>Un nouveau cycle a démarré sur la tontine <strong>" . htmlspecialchars($tontineNom) . "</strong>.</p>
        <p><strong>C'est votre tour de recevoir la cagnotte !</strong></p>
        <p>Cycle n°" . $cycleNumero . "<br>
        Date limite des cotisations : " . formaterDate($dateLimite) . "<br>
        Cagnotte totale attendue : " . number_format($cagnotteTheorique, 0, ',', ' ') . " FCFA</p>
        <p>Les membres vont cotiser. Une fois la cagnotte complète, vous pourrez la retirer.</p>
        <p><a href='" . APP_URL . "/pages/cagnotte/recevoir.php'>Suivre la cagnotte →</a></p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email pour amende appliquée
 */
function envoyerEmailAmende(string $email, string $nom, string $tontineNom, int $tontineId, int $cycleId, float $montantAmende, string $motif, int $joursRetard): void {
    $sujet = "Amende appliquée sur votre tontine " . $tontineNom;
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Amende pour retard de paiement</h1>
        <p>Bonjour " . htmlspecialchars($nom) . ",</p>
        <p>Une amende vous a été appliquée sur la tontine <strong>" . htmlspecialchars($tontineNom) . "</strong>.</p>
        <p><strong>Détails :</strong><br>
        - Jours de retard : " . $joursRetard . " jour(s)<br>
        - Montant de l'amende : " . number_format($montantAmende, 0, ',', ' ') . " FCFA<br>
        - Motif : " . htmlspecialchars($motif) . "</p>
        <p>Vous devez payer votre cotisation + cette amende pour régulariser votre situation.</p>
        <p><a href='" . APP_URL . "/pages/cotisations/payer.php?tontine=" . $tontineId . "&cycle=" . $cycleId . "'>Payer maintenant →</a></p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email pour cycle distribué (nouveau cycle disponible)
 */
function envoyerEmailCycleDistribue(string $email, string $nom, string $tontineNom, int $tontineId, int $ancienCycle, int $nouveauCycle): void {
    $sujet = "Cycle terminé - Nouveau cycle disponible !";
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Cycle terminé sur " . htmlspecialchars($tontineNom) . "</h1>
        <p>Bonjour " . htmlspecialchars($nom) . ",</p>
        <p>Le cycle n°" . $ancienCycle . " est terminé et la cagnotte a été distribuée.</p>
        <p>Un nouveau cycle (n°" . $nouveauCycle . ") va bientôt démarrer.</p>
        <p><a href='" . APP_URL . "/pages/tontines/voir.php?id=" . $tontineId . "'>Voir la tontine →</a></p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email pour le nouvel administrateur élu
 */
function envoyerEmailNouvelAdmin(string $email, string $nom, int $tontineId): void {
    $sujet = "Félicitations ! Vous êtes le nouvel administrateur";
    $corps = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body>
        <h1>Félicitations " . htmlspecialchars($nom) . " !</h1>
        <p>Vous avez été élu <strong>nouvel administrateur</strong> de votre tontine suite au vote des membres.</p>
        <p>En tant qu'administrateur, vous pouvez désormais :</p>
        <ul>
            <li>Gérer les membres (ajouter, exclure)</li>
            <li>Démarrer les cycles de cotisation</li>
            <li>Valider les demandes d'urgence</li>
            <li>Modifier les paramètres de la tontine</li>
        </ul>
        <p><a href='" . APP_URL . "/pages/tontines/voir.php?id=" . $tontineId . "'>Accéder à votre tontine →</a></p>
        </body>
        </html>
    ";
    envoyerEmail($email, $nom, $sujet, $corps);
}

/**
 * Email pour une urgence validée
 */
function envoyerEmailUrgenceValidee(string $email, string $nom, string $tontineNom, int $tontineId, int $cycleId, string $mode): void {
    if ($mode === 'immediat') {
        $sujet = "Urgence validée - Vous pouvez retirer votre cagnotte immédiatement";
        $corps = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body>
            <h1>Urgence validée - Retrait immédiat</h1>
            <p>Bonjour " . htmlspecialchars($nom) . ",</p>
            <p>Votre demande d'urgence sur la tontine <strong>" . htmlspecialchars($tontineNom) . "</strong> a été <strong>acceptée</strong>.</p>
            <p>Vous pouvez dès maintenant retirer la cagnotte disponible (en totalité ou en partie).</p>
            <p><a href='" . APP_URL . "/pages/cagnotte/recevoir.php?tontine=" . $tontineId . "&cycle=" . $cycleId . "'>Recevoir ma cagnotte →</a></p>
            <p>Si vous ne retirez pas immédiatement, l'argent restera disponible jusqu'à la fin du cycle.</p>
            </body>
            </html>
        ";
    } else {
        $sujet = "Urgence validée - Vous serez le prochain bénéficiaire";
        $corps = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body>
            <h1>Urgence validée - Prochain cycle</h1>
            <p>Bonjour " . htmlspecialchars($nom) . ",</p>
            <p>Votre demande d'urgence sur la tontine <strong>" . htmlspecialchars($tontineNom) . "</strong> a été <strong>acceptée</strong>.</p>
            <p>Vous serez le <strong>prochain bénéficiaire</strong> du cycle de cotisation.</p>
            <p>Une fois que tous les membres auront cotisé et que la cagnotte sera complète, vous pourrez la retirer.</p>
            <p><a href='" . APP_URL . "/pages/tontines/voir.php?id=" . $tontineId . "'>Suivre l'évolution de la cagnotte →</a></p>
            </body>
            </html>
        ";
    }
    envoyerEmail($email, $nom, $sujet, $corps);
}