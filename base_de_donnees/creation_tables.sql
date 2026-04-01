
DROP DATABASE IF EXISTS etontine;
CREATE DATABASE IF NOT EXISTS etontine
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE etontine;

-- Table des utilisateurs
CREATE TABLE utilisateurs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_complet     VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255)        NOT NULL,
    telephone       VARCHAR(20)         NOT NULL,
    role_global     ENUM('super_admin','utilisateur') NOT NULL DEFAULT 'utilisateur',
    est_bloque      TINYINT(1)          NOT NULL DEFAULT 0,
    jeton_invitation VARCHAR(64)        NULL,
    date_inscription DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME         NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des tontines
CREATE TABLE tontines (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    montant_cotisation DECIMAL(12,2)    NOT NULL,
    frequence       ENUM('hebdomadaire','mensuelle','trimestrielle') NOT NULL DEFAULT 'mensuelle',
    echeance_jour_semaine TINYINT UNSIGNED NULL DEFAULT NULL,
    echeance_jour_mois TINYINT UNSIGNED NULL DEFAULT NULL,
    nombre_max_membres SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    statut          ENUM('active','fermee','suspendue','brouillon') NOT NULL DEFAULT 'active',
    cycles_completes_ce_tour INT UNSIGNED NOT NULL DEFAULT 0,
    numero_tour_actuel       INT UNSIGNED NOT NULL DEFAULT 1,
    tour_complet_en_attente  TINYINT(1)   NOT NULL DEFAULT 0,
    createur_id     INT UNSIGNED        NOT NULL,
    amende_par_jour DECIMAL(10,2)       DEFAULT 0,
    amende_delai_grace INT              DEFAULT 0,
    amendes_activees TINYINT            DEFAULT 0,
    date_creation   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_fermeture  DATETIME            NULL,
    CONSTRAINT fk_tontine_createur FOREIGN KEY (createur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des membres des tontines
CREATE TABLE membres_tontine (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    utilisateur_id  INT UNSIGNED        NOT NULL,
    role            ENUM('admin','membre') NOT NULL DEFAULT 'membre',
    statut          ENUM('actif','retard','exclu') NOT NULL DEFAULT 'actif',
    ordre_tour      SMALLINT UNSIGNED   NULL,
    score_fiabilite DECIMAL(5,2)        NOT NULL DEFAULT 100.00,
    date_adhesion   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_membre_tontine (tontine_id, utilisateur_id),
    CONSTRAINT fk_mt_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_mt_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des cycles de cotisation
CREATE TABLE cycles_cotisation (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    numero_cycle    SMALLINT UNSIGNED   NOT NULL,
    date_debut      DATE                NOT NULL,
    date_limite     DATE                NOT NULL,
    date_distribution DATE              NULL,
    beneficiaire_id INT UNSIGNED        NULL,
    statut          ENUM('en_cours','clos','distribue') NOT NULL DEFAULT 'en_cours',
    cagnotte_collectee DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
    cagnotte_theorique DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_cycle_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_cycle_beneficiaire FOREIGN KEY (beneficiaire_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des paiements
CREATE TABLE paiements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    cycle_id        INT UNSIGNED        NOT NULL,
    payeur_id       INT UNSIGNED        NOT NULL,
    beneficiaire_id INT UNSIGNED        NOT NULL,
    montant         DECIMAL(12,2)       NOT NULL,
    type_paiement   ENUM('normal','pour_autrui') NOT NULL DEFAULT 'normal',
    mode_paiement   ENUM('wave','orange_money','especes') NOT NULL DEFAULT 'wave',
    numero_telephone VARCHAR(20)        NULL,
    statut          ENUM('paye','en_attente','retard') NOT NULL DEFAULT 'paye',
    reference       VARCHAR(64)         NULL,
    note            TEXT                NULL,
    date_paiement   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paiement_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_paiement_cycle FOREIGN KEY (cycle_id) REFERENCES cycles_cotisation(id) ON DELETE CASCADE,
    CONSTRAINT fk_paiement_payeur FOREIGN KEY (payeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_paiement_beneficiaire FOREIGN KEY (beneficiaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des amendes
CREATE TABLE amendes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    cycle_id        INT UNSIGNED        NOT NULL,
    membre_id       INT UNSIGNED        NOT NULL,
    montant         DECIMAL(10,2)       NOT NULL,
    type_calcul     ENUM('fixe','par_jour') DEFAULT 'fixe',
    jours_retard    INT                 DEFAULT 0,
    motif           TEXT                NULL,
    est_paye        TINYINT             DEFAULT 0,
    date_creation   DATETIME            NOT NULL,
    date_paiement   DATETIME            NULL,
    INDEX idx_tontine (tontine_id),
    INDEX idx_cycle (cycle_id),
    INDEX idx_membre (membre_id),
    CONSTRAINT fk_amende_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_amende_cycle FOREIGN KEY (cycle_id) REFERENCES cycles_cotisation(id) ON DELETE CASCADE,
    CONSTRAINT fk_amende_membre FOREIGN KEY (membre_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des distributions
CREATE TABLE distributions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    cycle_id        INT UNSIGNED        NOT NULL,
    beneficiaire_id INT UNSIGNED        NOT NULL,
    montant_prevu   DECIMAL(12,2)       NOT NULL,
    montant_recu    DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    montant_restant DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    choix_beneficiaire ENUM('attendre','recevoir_maintenant') NOT NULL DEFAULT 'attendre',
    statut          ENUM('en_attente','partiel','complet') NOT NULL DEFAULT 'en_attente',
    date_demande    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_distribution DATETIME          NULL,
    CONSTRAINT fk_distrib_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_distrib_cycle FOREIGN KEY (cycle_id) REFERENCES cycles_cotisation(id) ON DELETE CASCADE,
    CONSTRAINT fk_distrib_beneficiaire FOREIGN KEY (beneficiaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des paiements pour autrui
CREATE TABLE paiements_pour_autrui (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paiement_id     INT UNSIGNED        NOT NULL,
    payeur_id       INT UNSIGNED        NOT NULL,
    beneficiaire_id INT UNSIGNED        NOT NULL,
    tontine_id      INT UNSIGNED        NOT NULL,
    montant         DECIMAL(12,2)       NOT NULL,
    note            VARCHAR(255)        NULL,
    date_enregistrement DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ppa_paiement FOREIGN KEY (paiement_id) REFERENCES paiements(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppa_payeur FOREIGN KEY (payeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppa_beneficiaire FOREIGN KEY (beneficiaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_ppa_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des demandes d'urgence
CREATE TABLE demandes_urgence (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    demandeur_id    INT UNSIGNED        NOT NULL,
    motif           TEXT                NOT NULL,
    statut          ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
    mode_traitement ENUM('immediat','prochain_cycle') NULL,
    decision_admin_id INT UNSIGNED      NULL,
    date_demande    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_decision   DATETIME            NULL,
    commentaire_admin TEXT              NULL,
    CONSTRAINT fk_urgence_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_urgence_demandeur FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_urgence_admin FOREIGN KEY (decision_admin_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des pétitions
CREATE TABLE petitions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    initiateur_id   INT UNSIGNED        NOT NULL,
    motif           TEXT                NOT NULL,
    statut          ENUM('en_cours','vote_ouvert','acceptee','rejetee') NOT NULL DEFAULT 'en_cours',
    nombre_signatures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    seuil_signatures SMALLINT UNSIGNED  NOT NULL,
    date_creation   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_cloture    DATETIME            NULL,
    CONSTRAINT fk_petition_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_petition_initiateur FOREIGN KEY (initiateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des signatures de pétitions
CREATE TABLE signatures_petition (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    petition_id     INT UNSIGNED        NOT NULL,
    signataire_id   INT UNSIGNED        NOT NULL,
    date_signature  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_signature (petition_id, signataire_id),
    CONSTRAINT fk_sig_petition FOREIGN KEY (petition_id) REFERENCES petitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sig_signataire FOREIGN KEY (signataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des votes
CREATE TABLE votes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    petition_id     INT UNSIGNED        NOT NULL,
    statut          ENUM('ouvert','clos') NOT NULL DEFAULT 'ouvert',
    nombre_oui      SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    nombre_non      SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    seuil_validation DECIMAL(5,2)       NOT NULL DEFAULT 75.00,
    resultat        ENUM('admin_change','admin_maintenu') NULL,
    nouvel_admin_id INT UNSIGNED        NULL,
    date_ouverture  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_cloture    DATETIME            NULL,
    CONSTRAINT fk_vote_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_vote_petition FOREIGN KEY (petition_id) REFERENCES petitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_vote_nouvel_admin FOREIGN KEY (nouvel_admin_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des votes des membres
CREATE TABLE votes_membres (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vote_id         INT UNSIGNED        NOT NULL,
    votant_id       INT UNSIGNED        NOT NULL,
    choix           ENUM('oui','non')   NOT NULL,
    date_vote       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote_membre (vote_id, votant_id),
    CONSTRAINT fk_vm_vote FOREIGN KEY (vote_id) REFERENCES votes(id) ON DELETE CASCADE,
    CONSTRAINT fk_vm_votant FOREIGN KEY (votant_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des candidatures admin
CREATE TABLE candidatures_admin (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vote_id         INT UNSIGNED        NOT NULL,
    candidat_id     INT UNSIGNED        NOT NULL,
    message         TEXT                NULL,
    date_candidature DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_candidature (vote_id, candidat_id),
    CONSTRAINT fk_cand_vote FOREIGN KEY (vote_id) REFERENCES votes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cand_candidat FOREIGN KEY (candidat_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des notifications
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    destinataire_id INT UNSIGNED        NOT NULL,
    tontine_id      INT UNSIGNED        NULL,
    type            ENUM(
                        'paiement_confirme',
                        'retard_cotisation',
                        'paiement_pour_autrui',
                        'urgence_validee',
                        'urgence_refusee',
                        'vote_ouvert',
                        'admin_change',
                        'invitation_tontine',
                        'cagnotte_disponible',
                        'cagnotte_complete',
                        'amende',
                        'tour_complet',
                        'tontine_demarree'
                    ) NOT NULL,
    titre           VARCHAR(200)        NOT NULL,
    message         TEXT                NOT NULL,
    lien            VARCHAR(255)        NULL,
    est_lu          TINYINT(1)          NOT NULL DEFAULT 0,
    date_creation   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_destinataire FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des réinitialisations de mot de passe
CREATE TABLE IF NOT EXISTS reinitialisations_mdp (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED        NOT NULL,
    jeton           VARCHAR(64)         NOT NULL,
    date_expiration DATETIME            NOT NULL,
    utilise         TINYINT(1)          NOT NULL DEFAULT 0,
    date_creation   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_jeton (jeton),
    CONSTRAINT fk_reinit_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des invitations
CREATE TABLE invitations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tontine_id      INT UNSIGNED        NOT NULL,
    invite_par_id   INT UNSIGNED        NOT NULL,
    email_invite    VARCHAR(150)        NULL,
    jeton           VARCHAR(64)         NOT NULL UNIQUE,
    statut          ENUM('en_attente','acceptee','expiree') NOT NULL DEFAULT 'en_attente',
    date_envoi      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_expiration DATETIME            NOT NULL,
    CONSTRAINT fk_inv_tontine FOREIGN KEY (tontine_id) REFERENCES tontines(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_inviteur FOREIGN KEY (invite_par_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des journaux de paiement
CREATE TABLE journaux_paiement (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paiement_id     INT UNSIGNED        NULL,
    utilisateur_id  INT UNSIGNED        NOT NULL,
    montant         DECIMAL(12,2)       NOT NULL,
    mode            ENUM('wave','orange_money','especes') NOT NULL,
    numero_telephone VARCHAR(20)        NULL,
    statut_simulation ENUM('succes','echec','en_cours') NOT NULL DEFAULT 'en_cours',
    reference_externe VARCHAR(100)      NULL,
    reponse_api     TEXT                NULL,
    date_tentative  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_paiement FOREIGN KEY (paiement_id) REFERENCES paiements(id) ON DELETE SET NULL,
    CONSTRAINT fk_journal_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des logs système
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    utilisateur_id INT UNSIGNED NULL,
    details TEXT NULL,
    tontine_id INT UNSIGNED NULL,
    ip_adresse VARCHAR(45) NULL,
    date_creation DATETIME NOT NULL,
    INDEX idx_date (date_creation),
    INDEX idx_action (action),
    INDEX idx_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes pour les performances
CREATE INDEX idx_paiements_cycle ON paiements(cycle_id);
CREATE INDEX idx_paiements_payeur ON paiements(payeur_id);
CREATE INDEX idx_paiements_date ON paiements(date_paiement);
CREATE INDEX idx_cycles_tontine ON cycles_cotisation(tontine_id, statut);
CREATE INDEX idx_membres_statut ON membres_tontine(statut);
CREATE INDEX idx_notifs_destinataire ON notifications(destinataire_id, est_lu);
CREATE INDEX idx_distributions_statut ON distributions(statut);
CREATE INDEX idx_urgences_statut ON demandes_urgence(statut);

-- INSERTION DES UTILISATEURS

INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, telephone, role_global) VALUES
('Mouhamadou Lamine Niang', 'mouhamedlniang@gmail.com', 'admin123', '770000000', 'super_admin');

INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, telephone, role_global) VALUES
('Mouhameth Nguer', 'mouhameth.nguer@gmail.com', 'tontine123', '771000001', 'utilisateur'),
('Lorse Fall', 'lorse.fall@gmail.com', 'tontine123', '771000002', 'utilisateur'),
('Dame Niang', 'dame.niang@gmail.com', 'tontine123', '771000003', 'utilisateur'),
('Papa Mangoné Gueye', 'papamangone.gueye@gmail.com', 'tontine123', '771000004', 'utilisateur'),
('Mamadou Sy', 'mamadou.sy@gmail.com', 'tontine123', '771000005', 'utilisateur'),
('Serigne Modou Wadji', 'serignemodou.wadji@gmail.com', 'tontine123', '771000006', 'utilisateur'),
('Imam Ahmed Lamine Dia', 'imamahmed.dia@gmail.com', 'tontine123', '771000007', 'utilisateur');

INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, telephone, role_global) VALUES
('Abdou Diop', 'abdou.diop@gmail.com', 'tontine123', '771000008', 'utilisateur'),
('Aissatou Sow', 'aissatou.sow@gmail.com', 'tontine123', '771000009', 'utilisateur'),
('Babacar Seck', 'babacar.seck@gmail.com', 'tontine123', '771000010', 'utilisateur'),
('Coumba Diallo', 'coumba.diallo@gmail.com', 'tontine123', '771000011', 'utilisateur'),
('Daouda Ba', 'daouda.ba@gmail.com', 'tontine123', '771000012', 'utilisateur'),
('Fatou Ndiaye', 'fatou.ndiaye@gmail.com', 'tontine123', '771000013', 'utilisateur'),
('Ibrahima Fall', 'ibrahima.fall@gmail.com', 'tontine123', '771000014', 'utilisateur'),
('Khadija Gueye', 'khadija.gueye@gmail.com', 'tontine123', '771000015', 'utilisateur'),
('Mamadou Diarra', 'mamadou.diarra@gmail.com', 'tontine123', '771000016', 'utilisateur'),
('Ndeye Diop', 'ndeye.diop@gmail.com', 'tontine123', '771000017', 'utilisateur'),
('Ousmane Gaye', 'ousmane.gaye@gmail.com', 'tontine123', '771000018', 'utilisateur'),
('Rokhaya Sarr', 'rokhaya.sarr@gmail.com', 'tontine123', '771000019', 'utilisateur'),
('Souleymane Cissé', 'souleymane.cisse@gmail.com', 'tontine123', '771000020', 'utilisateur'),
('Adama Touré', 'adama.toure@gmail.com', 'tontine123', '771000021', 'utilisateur'),
('Binta Ndiaye', 'binta.ndiaye@gmail.com', 'tontine123', '771000022', 'utilisateur'),
('Cheikh Dieng', 'cheikh.dieng@gmail.com', 'tontine123', '771000023', 'utilisateur'),
('Dieynaba Sy', 'dieynaba.sy@gmail.com', 'tontine123', '771000024', 'utilisateur'),
('El Hadji Diop', 'elhadji.diop@gmail.com', 'tontine123', '771000025', 'utilisateur'),
('Fatima Ndiaye', 'fatima.ndiaye@gmail.com', 'tontine123', '771000026', 'utilisateur'),
('Gora Diop', 'gora.diop@gmail.com', 'tontine123', '771000027', 'utilisateur'),
('Haby Sall', 'haby.sall@gmail.com', 'tontine123', '771000028', 'utilisateur'),
('Idrissa Seck', 'idrissa.seck@gmail.com', 'tontine123', '771000029', 'utilisateur'),
('Jacqueline Mendy', 'jacqueline.mendy@gmail.com', 'tontine123', '771000030', 'utilisateur'),
('Karim Wade', 'karim.wade@gmail.com', 'tontine123', '771000031', 'utilisateur'),
('Lamine Ba', 'lamine.ba@gmail.com', 'tontine123', '771000032', 'utilisateur'),
('Mariama Diallo', 'mariama.diallo@gmail.com', 'tontine123', '771000033', 'utilisateur'),
('Ndiogou Niang', 'ndiogou.niang@gmail.com', 'tontine123', '771000034', 'utilisateur'),
('Omar Diop', 'omar.diop@gmail.com', 'tontine123', '771000035', 'utilisateur'),
('Penda Sarr', 'penda.sarr@gmail.com', 'tontine123', '771000036', 'utilisateur'),
('Ramatoulaye Gaye', 'ramatoulaye.gaye@gmail.com', 'tontine123', '771000037', 'utilisateur'),
('Sidy Diop', 'sidy.diop@gmail.com', 'tontine123', '771000038', 'utilisateur'),
('Tidiane Sy', 'tidiane.sy@gmail.com', 'tontine123', '771000039', 'utilisateur'),
('Yacine Dieng', 'yacine.dieng@gmail.com', 'tontine123', '771000040', 'utilisateur'),
('Zeynab Diop', 'zeynab.diop@gmail.com', 'tontine123', '771000041', 'utilisateur'),
('Alpha Ba', 'alpha.ba@gmail.com', 'tontine123', '771000042', 'utilisateur'),
('Baye Cissé', 'baye.cisse@gmail.com', 'tontine123', '771000043', 'utilisateur'),
('Diariétou Sall', 'diarietou.sall@gmail.com', 'tontine123', '771000044', 'utilisateur'),
('Moustapha Diop', 'moustapha.diop@gmail.com', 'tontine123', '771000045', 'utilisateur'),
('Ngoné Ndiaye', 'ngone.ndiaye@gmail.com', 'tontine123', '771000046', 'utilisateur'),
('Pape Diouf', 'pape.diouf@gmail.com', 'tontine123', '771000047', 'utilisateur'),
('Safiétou Diallo', 'safietou.diallo@gmail.com', 'tontine123', '771000048', 'utilisateur'),
('Thierno Ba', 'thierno.ba@gmail.com', 'tontine123', '771000049', 'utilisateur'),
('Yoro Diop', 'yoro.diop@gmail.com', 'tontine123', '771000050', 'utilisateur');

-- INSERTION DES TONTINES

INSERT INTO tontines (nom, description, montant_cotisation, frequence, echeance_jour_semaine, echeance_jour_mois, nombre_max_membres, createur_id, statut, amende_par_jour, amende_delai_grace, amendes_activees) VALUES
('Tontine Hebdo Mercredi', 'Cotisation chaque mercredi - Admin: Mouhameth Nguer', 5000, 'hebdomadaire', 3, NULL, 10, 2, 'active', 500, 3, 1),
('Tontine Mensuelle 15', 'Cotisation le 15 de chaque mois - Admin: Lorse Fall', 10000, 'mensuelle', NULL, 15, 12, 3, 'active', 1000, 5, 1),
('Tontine Trimestrielle', 'Cotisation tous les 3 mois le 1er - Admin: Dame Niang', 25000, 'trimestrielle', NULL, 1, 8, 4, 'active', 2000, 7, 1),
('Tontine Vendredi', 'Cotisation chaque vendredi - Admin: Papa Mangoné Gueye', 7500, 'hebdomadaire', 5, NULL, 8, 5, 'active', 750, 3, 1),
('Tontine Mensuelle 25', 'Cotisation le 25 de chaque mois - Admin: Mamadou Sy', 15000, 'mensuelle', NULL, 25, 6, 6, 'active', 1000, 5, 1),
('Grande Tontine Lundi', '20 membres maximum - Admin: Serigne Modou Wadji', 3000, 'hebdomadaire', 1, NULL, 20, 7, 'active', 300, 3, 1),
('Tontine Urgence', 'Pour situations urgentes - Admin: Imam Ahmed Lamine Dia', 20000, 'mensuelle', NULL, 10, 15, 8, 'active', 1500, 3, 1),
('Tontine Super Admin', 'Super admin - Mouhamed Niang', 10000, 'mensuelle', NULL, 5, 10, 1, 'active', 500, 3, 1);

-- AJOUT DES MEMBRES DANS LES TONTINES 

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(1, 2, 'admin', 1, 'actif'),
(1, 3, 'membre', 2, 'actif'),
(1, 4, 'membre', 3, 'actif'),
(1, 5, 'membre', 4, 'actif'),
(1, 6, 'membre', 5, 'actif'),
(1, 7, 'membre', 6, 'actif'),
(1, 8, 'membre', 7, 'actif'),
(1, 9, 'membre', 8, 'actif'),
(1, 10, 'membre', 9, 'actif'),
(1, 11, 'membre', 10, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(2, 3, 'admin', 1, 'actif'),
(2, 12, 'membre', 2, 'actif'),
(2, 13, 'membre', 3, 'actif'),
(2, 14, 'membre', 4, 'actif'),
(2, 15, 'membre', 5, 'actif'),
(2, 16, 'membre', 6, 'actif'),
(2, 17, 'membre', 7, 'actif'),
(2, 18, 'membre', 8, 'actif'),
(2, 19, 'membre', 9, 'actif'),
(2, 20, 'membre', 10, 'actif'),
(2, 21, 'membre', 11, 'actif'),
(2, 22, 'membre', 12, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(3, 4, 'admin', 1, 'actif'),
(3, 23, 'membre', 2, 'actif'),
(3, 24, 'membre', 3, 'actif'),
(3, 25, 'membre', 4, 'actif'),
(3, 26, 'membre', 5, 'actif'),
(3, 27, 'membre', 6, 'actif'),
(3, 28, 'membre', 7, 'actif'),
(3, 29, 'membre', 8, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(4, 5, 'admin', 1, 'actif'),
(4, 30, 'membre', 2, 'actif'),
(4, 31, 'membre', 3, 'actif'),
(4, 32, 'membre', 4, 'actif'),
(4, 33, 'membre', 5, 'actif'),
(4, 34, 'membre', 6, 'actif'),
(4, 35, 'membre', 7, 'actif'),
(4, 36, 'membre', 8, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(5, 6, 'admin', 1, 'actif'),
(5, 37, 'membre', 2, 'actif'),
(5, 38, 'membre', 3, 'actif'),
(5, 39, 'membre', 4, 'actif'),
(5, 40, 'membre', 5, 'actif'),
(5, 41, 'membre', 6, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(6, 7, 'admin', 1, 'actif'),
(6, 8, 'membre', 2, 'actif'),
(6, 9, 'membre', 3, 'actif'),
(6, 10, 'membre', 4, 'actif'),
(6, 11, 'membre', 5, 'actif'),
(6, 12, 'membre', 6, 'actif'),
(6, 13, 'membre', 7, 'actif'),
(6, 14, 'membre', 8, 'actif'),
(6, 15, 'membre', 9, 'actif'),
(6, 16, 'membre', 10, 'actif'),
(6, 17, 'membre', 11, 'actif'),
(6, 18, 'membre', 12, 'actif'),
(6, 19, 'membre', 13, 'actif'),
(6, 20, 'membre', 14, 'actif'),
(6, 21, 'membre', 15, 'actif'),
(6, 22, 'membre', 16, 'actif'),
(6, 23, 'membre', 17, 'actif'),
(6, 24, 'membre', 18, 'actif'),
(6, 25, 'membre', 19, 'actif'),
(6, 26, 'membre', 20, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(7, 8, 'admin', 1, 'actif'),
(7, 27, 'membre', 2, 'actif'),
(7, 28, 'membre', 3, 'actif'),
(7, 29, 'membre', 4, 'actif'),
(7, 30, 'membre', 5, 'actif'),
(7, 31, 'membre', 6, 'actif'),
(7, 32, 'membre', 7, 'actif'),
(7, 33, 'membre', 8, 'actif'),
(7, 34, 'membre', 9, 'actif'),
(7, 35, 'membre', 10, 'actif'),
(7, 36, 'membre', 11, 'actif'),
(7, 37, 'membre', 12, 'actif'),
(7, 38, 'membre', 13, 'actif'),
(7, 39, 'membre', 14, 'actif'),
(7, 40, 'membre', 15, 'actif');

INSERT INTO membres_tontine (tontine_id, utilisateur_id, role, ordre_tour, statut) VALUES
(8, 1, 'admin', 1, 'actif'),
(8, 41, 'membre', 2, 'actif'),
(8, 42, 'membre', 3, 'actif'),
(8, 43, 'membre', 4, 'actif'),
(8, 44, 'membre', 5, 'actif'),
(8, 45, 'membre', 6, 'actif'),
(8, 46, 'membre', 7, 'actif'),
(8, 47, 'membre', 8, 'actif'),
(8, 48, 'membre', 9, 'actif'),
(8, 49, 'membre', 10, 'actif');

-- INSERTION DES CYCLES DE COTISATION

INSERT INTO cycles_cotisation (tontine_id, numero_cycle, date_debut, date_limite, beneficiaire_id, cagnotte_theorique, cagnotte_collectee, statut) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 2, 50000, 30000, 'en_cours'),
(2, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 3, 120000, 80000, 'en_cours'),
(3, 1, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), 4, 200000, 200000, 'distribue'),
(4, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY), 5, 60000, 45000, 'en_cours'),
(5, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 6, 90000, 50000, 'en_cours'),
(6, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 7, 60000, 20000, 'en_cours'),
(7, 1, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 8, 300000, 250000, 'en_cours'),
(8, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY), 1, 100000, 30000, 'en_cours');

UPDATE cycles_cotisation SET date_distribution = DATE_SUB(NOW(), INTERVAL 5 DAY) WHERE id = 3;

-- INSERTION DES PAIEMENTS

INSERT INTO paiements (tontine_id, cycle_id, payeur_id, beneficiaire_id, montant, type_paiement, mode_paiement, numero_telephone, statut, reference, date_paiement) VALUES
(1, 1, 2, 2, 5000, 'normal', 'wave', '771000001', 'paye', 'REF-T1-001', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 1, 3, 2, 5000, 'normal', 'orange_money', '771000002', 'paye', 'REF-T1-002', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 4, 2, 5000, 'normal', 'wave', '771000003', 'paye', 'REF-T1-003', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 1, 3, 3, 10000, 'normal', 'wave', '771000001', 'paye', 'REF-T2-001', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 1, 12, 3, 10000, 'normal', 'orange_money', '771000011', 'paye', 'REF-T2-002', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 1, 13, 3, 10000, 'normal', 'wave', '771000012', 'paye', 'REF-T2-003', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- INSERTION DES NOTIFICATIONS

INSERT INTO notifications (destinataire_id, tontine_id, type, titre, message, lien, date_creation) VALUES
(2, 1, 'paiement_confirme', 'Paiement confirmé', 'Votre cotisation a été enregistrée avec succès.', '/pages/cotisations/historique.php?tontine=1', NOW()),
(3, 2, 'cagnotte_disponible', 'Cagnotte disponible', 'La cagnotte est partiellement disponible.', '/pages/cagnotte/etat.php?tontine=2', NOW());

-- INSERTION D'UNE DEMANDE D'URGENCE

INSERT INTO demandes_urgence (tontine_id, demandeur_id, motif, statut, date_demande) VALUES
(1, 6, 'Urgence médicale : besoin de fonds rapidement pour une hospitalisation.', 'en_attente', NOW());


-- INSERTION DE LA PÉTITION ET SIGNATURES 
-- 

INSERT INTO petitions (tontine_id, initiateur_id, motif, seuil_signatures, nombre_signatures, statut, date_creation) 
VALUES (1, 3, 'Proposition de changement d''administrateur pour meilleure gestion.', 3, 2, 'en_cours', DATE_SUB(NOW(), INTERVAL 2 DAY));

SET @petition_id = LAST_INSERT_ID();

INSERT INTO signatures_petition (petition_id, signataire_id, date_signature) 
VALUES 
(@petition_id, 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@petition_id, 4, DATE_SUB(NOW(), INTERVAL 1 DAY));