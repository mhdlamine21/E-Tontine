--  MIGRATION - Gestion des tours complets
--  À exécuter une seule fois sur la base existante.
--
--  Contexte : un « tour » = chaque membre actif a reçu la cagnotte une
--  fois (le bénéficiaire passe en dernier dans l'ordre après avoir reçu,
--  mécanisme déjà présent dans mettreMembreEnDernierOrdre()). Ces colonnes
--  permettent de détecter automatiquement la fin d'un tour complet et de
--  demander explicitement à l'administrateur : nouveau tour (avec un
--  nouveau tirage au sort de l'ordre) ou fermeture de la tontine.

ALTER TABLE tontines
    ADD COLUMN cycles_completes_ce_tour INT UNSIGNED NOT NULL DEFAULT 0 AFTER statut,
    ADD COLUMN numero_tour_actuel       INT UNSIGNED NOT NULL DEFAULT 1 AFTER cycles_completes_ce_tour,
    ADD COLUMN tour_complet_en_attente  TINYINT(1)   NOT NULL DEFAULT 0 AFTER numero_tour_actuel;

-- Nouveau type de notification pour prévenir l'admin (et les membres) qu'un
-- tour complet vient de se terminer et qu'une décision est attendue.
ALTER TABLE notifications
    MODIFY COLUMN type ENUM(
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
    ) NOT NULL;
