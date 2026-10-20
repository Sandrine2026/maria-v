MARIA-V — Version corrigée

INSTALLATION LOCALE (WampServer)
1. Copier le dossier maria-v dans C:\wamp64\www\maria-v.
2. Pour une installation déjà existante, NE PAS importer database/maria_v.sql : conserver la base et les données en place.
3. Vérifier config/database.php. Par défaut, MySQL utilise localhost, utilisateur root et mot de passe vide. Vous pouvez configurer MARIA_DB_HOST, MARIA_DB_NAME, MARIA_DB_USER et MARIA_DB_PASSWORD dans l’environnement.
4. Créer le premier compte administrateur via le mécanisme de création/réinitialisation déjà prévu dans votre installation. Ne pas utiliser un compte de démonstration en production.
5. Ouvrir http://localhost/maria-v/public/index.php.

PARCOURS D’AIDE
- Le formulaire de vérification enregistre le demandeur et la pièce d’identité.
- Le gestionnaire doit valider le dossier et marquer le résultat « eligible » pour permettre le formulaire « Faire ma demande d’aide ».
- Le demandeur utilise le numéro de vérification et l’e-mail correspondant, puis conserve le numéro de suivi de sa demande.

LANGUES
FR, EN, DE, ES et EL sont disponibles via le sélecteur de langue. Les libellés partagés et la page de demande d’aide sont traduits. Certains textes éditoriaux historiques des autres pages doivent encore être relus/traduits individuellement avant une publication multilingue officielle.

SECURITE ET DONNEES
Les documents d’identité déjà présents dans l’archive source n’ont pas été inclus dans cette archive corrigée afin de ne pas redistribuer de données personnelles. Le dossier uploads/identity est conservé avec une règle Apache interdisant l’accès direct. Les informations réelles de la fondation et les mentions légales doivent être complétées avant la mise en production.

AUCUN MODULE DE PAIEMENT N’EST PRESENT DANS CETTE VERSION.


MISE A JOUR DU PARCOURS (octobre 2026)
- Le formulaire de demande d'aide propose une liste déroulante de 25 pays (liste demandée pour la référence vidéo).
- Le suivi affiche le statut de vérification et, si une demande existe, son numéro et son statut administratif.
- Compatibilité avec la base existante : aucun champ n’est ajouté. Le pays de résidence est conservé dans le texte de description de la demande, car la table demandes_aide actuelle ne possède pas de colonne residence_pays.
- L’historique utilise les colonnes existantes demande_id et date_creation.
- Le statut d'éligibilité est attribué par l'administration après examen; le site ne déclare pas automatiquement une personne éligible avant validation.
- Aucun module de paiement n'est inclus.

NOTE IMPORTANTE POUR LA VERSION ACTUELLE
Cette archive corrige les deux incompatibilités identifiées avec le dump maria-v.sql transmis. Elle ne met pas encore en place une gestion persistante de contrat et d’apport : ces informations nécessitent un stockage dédié, et la base actuelle ne possède pas de table/colonnes prévues à cet effet. Aucune donnée de la base n’est modifiée par la préparation de cette archive.
