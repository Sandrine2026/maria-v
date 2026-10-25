-- MARIA-V : sauvegarde des champs personnalisés de la convention.
-- Migration additive : ne supprime ni ne remplace aucune donnée existante.
USE `maria-v`;
ALTER TABLE contrats_aide
    ADD COLUMN document_data LONGTEXT NULL AFTER notes;
