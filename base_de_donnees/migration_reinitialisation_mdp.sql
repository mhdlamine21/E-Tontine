--  MIGRATION - Réinitialisation de mot de passe
--  À exécuter une seule fois sur la base existante (ALTER non nécessaire,
--  simple ajout de table).
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
