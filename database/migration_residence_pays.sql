USE `maria-v`;
ALTER TABLE demandes_aide ADD COLUMN residence_pays VARCHAR(100) NOT NULL DEFAULT '' AFTER type_aide_id;
