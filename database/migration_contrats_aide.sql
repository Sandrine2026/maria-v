-- MARIA-V : table complémentaire pour les contrats et le suivi administratif de l'apport.
-- Cette migration crée uniquement une nouvelle table. Elle ne modifie ni ne supprime
-- aucune table ou donnée existante. Vérifier le nom de la base avant exécution.
USE `maria-v`;

CREATE TABLE IF NOT EXISTS contrats_aide (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    demande_id INT UNSIGNED NOT NULL,
    numero_contrat VARCHAR(80) NOT NULL,
    montant_aide DECIMAL(12,2) NOT NULL,
    pourcentage_apport DECIMAL(5,2) NOT NULL,
    montant_apport DECIMAL(12,2) NOT NULL,
    statut_contrat ENUM('prepare','envoye','signe','cloture') NOT NULL DEFAULT 'prepare',
    statut_apport ENUM('non_du','en_attente','partiel','recu','dispense') NOT NULL DEFAULT 'non_du',
    montant_apport_recu DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    admin_id INT UNSIGNED NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contrats_demande (demande_id),
    UNIQUE KEY uq_contrats_numero (numero_contrat),
    KEY idx_contrats_statut (statut_contrat),
    KEY idx_contrats_apport (statut_apport)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
