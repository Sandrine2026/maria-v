CREATE DATABASE IF NOT EXISTS `maria-v` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `maria-v`;

CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE, mot_de_passe VARCHAR(255) NOT NULL,
 role ENUM('lecteur','gestionnaire','super_admin') NOT NULL DEFAULT 'lecteur',
 deux_facteurs TINYINT(1) NOT NULL DEFAULT 0, tentatives_echouees INT NOT NULL DEFAULT 0,
 bloque_jusqu_a DATETIME NULL, actif TINYINT(1) NOT NULL DEFAULT 1,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandeurs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(100) NOT NULL,
 prenom VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL,
 telephone VARCHAR(30) NOT NULL, date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_demandeurs_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS criteres_eligibilite (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, libelle VARCHAR(255) NOT NULL,
 description TEXT NULL, actif TINYINT(1) NOT NULL DEFAULT 1,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS types_aide (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(150) NOT NULL UNIQUE,
 description TEXT NULL, actif TINYINT(1) NOT NULL DEFAULT 1,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO types_aide (nom, description) VALUES
 ('Santé','Aide liée aux besoins de santé'),
 ('Éducation','Aide liée à la scolarité et à la formation'),
 ('Alimentation','Aide liée aux besoins alimentaires'),
 ('Logement','Aide liée au logement et à l’hébergement'),
 ('Autre besoin social','Autre besoin social à examiner');

CREATE TABLE IF NOT EXISTS verifications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, numero_dossier VARCHAR(80) NOT NULL UNIQUE,
 demandeur_id INT UNSIGNED NOT NULL, fichier_identite VARCHAR(255) NOT NULL,
 statut ENUM('reçue','en_cours','validee','refusee') NOT NULL DEFAULT 'reçue',
 resultat ENUM('en_attente','eligible','non_eligible') NOT NULL DEFAULT 'en_attente',
 motif_refus TEXT NULL, date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_traitement DATETIME NULL, INDEX idx_verifications_statut(statut),
 CONSTRAINT fk_verifications_demandeur FOREIGN KEY (demandeur_id) REFERENCES demandeurs(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demandes_aide (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, numero_suivi VARCHAR(80) NOT NULL UNIQUE,
 demandeur_id INT UNSIGNED NOT NULL, verification_id INT UNSIGNED NOT NULL,
 type_aide_id INT UNSIGNED NOT NULL, residence_pays VARCHAR(100) NOT NULL, situation_familiale TEXT NOT NULL,
 besoin_estime VARCHAR(500) NULL, description TEXT NOT NULL, email VARCHAR(150) NOT NULL,
 telephone VARCHAR(30) NOT NULL, consentement TINYINT(1) NOT NULL DEFAULT 0,
 statut ENUM('reçue','en_cours_examen','complement_demande','acceptee','refusee') NOT NULL DEFAULT 'reçue',
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_demandes_statut(statut), INDEX idx_demandes_email(email),
 CONSTRAINT fk_demandes_demandeur FOREIGN KEY (demandeur_id) REFERENCES demandeurs(id),
 CONSTRAINT fk_demandes_verification FOREIGN KEY (verification_id) REFERENCES verifications(id),
 CONSTRAINT fk_demandes_type FOREIGN KEY (type_aide_id) REFERENCES types_aide(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historique_statuts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, demande_aide_id INT UNSIGNED NOT NULL,
 ancien_statut VARCHAR(40) NULL, nouveau_statut VARCHAR(40) NOT NULL,
 commentaire TEXT NULL, admin_id INT UNSIGNED NULL,
 date_changement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_historique_demande FOREIGN KEY (demande_aide_id) REFERENCES demandes_aide(id) ON DELETE CASCADE,
 CONSTRAINT fk_historique_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pieces_jointes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, demande_aide_id INT UNSIGNED NOT NULL,
 nom_original VARCHAR(255) NOT NULL, nom_stocke VARCHAR(255) NOT NULL,
 type_mime VARCHAR(120) NOT NULL, taille INT UNSIGNED NOT NULL,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_piece_demande FOREIGN KEY (demande_aide_id) REFERENCES demandes_aide(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS articles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, titre VARCHAR(255) NOT NULL,
 contenu MEDIUMTEXT NOT NULL, image VARCHAR(255) NULL, auteur_id INT UNSIGNED NULL,
 publie TINYINT(1) NOT NULL DEFAULT 0, date_publication DATETIME NULL,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_modification DATETIME NULL,
 CONSTRAINT fk_articles_auteur FOREIGN KEY (auteur_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS programmes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, titre VARCHAR(255) NOT NULL,
 description TEXT NOT NULL, image VARCHAR(255) NULL, actif TINYINT(1) NOT NULL DEFAULT 1,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS temoignages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(150) NOT NULL,
 contenu TEXT NOT NULL, publie TINYINT(1) NOT NULL DEFAULT 0,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS partenaires (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(180) NOT NULL,
 logo VARCHAR(255) NULL, url VARCHAR(255) NULL, actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS email_templates (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(100) NOT NULL UNIQUE,
 sujet VARCHAR(255) NOT NULL, contenu MEDIUMTEXT NOT NULL,
 date_modification DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS email_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, destinataire VARCHAR(190) NOT NULL,
 sujet VARCHAR(255) NOT NULL, statut ENUM('en_attente','envoye','echec') NOT NULL DEFAULT 'en_attente',
 erreur TEXT NULL, date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS contacts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(100) NOT NULL,
 prenom VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, telephone VARCHAR(30) NULL,
 sujet VARCHAR(255) NOT NULL, message TEXT NOT NULL,
 statut ENUM('nouveau','lu','traite') NOT NULL DEFAULT 'nouveau',
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, date_traitement DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS journal_activite (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED NULL,
 action VARCHAR(150) NOT NULL, cible VARCHAR(150) NULL, details TEXT NULL,
 adresse_ip VARCHAR(45) NULL, date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_journal_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
