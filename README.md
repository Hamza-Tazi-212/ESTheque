# Bibliothèque — EST Fès

Application Laravel 12 / MySQL, avec Blade et Tailwind CSS. Le développement suit cinq étapes, chacune soumise à validation.

1. Architecture et base de données : étape validée. Migrations, modèles, annuaire utilisateurs interchangeable et configuration métier.
2. Authentification Google : étape validée. Socialite, emails `@usmba.ac.ma`, sessions via l'annuaire et protection des deux rôles.
3. Catalogue étudiant : étape validée. Recherche, lignes extensibles, disponibilité et fiche avec couverture, sommaire et résumé.
4. Dashboard gérant : étape validée. Retards, graphiques Chart.js, fiches étudiant et emails en file, chaque jour à 08:00 au Maroc.
5. **Étape actuelle : scan caméra implémenté.** Emprunt/retour automatique avec deux QR, limite de trois prêts actifs, refus si autre emprunteur, annulation et protection contre les requêtes répétées.

Les cinq étapes sont implémentées. L'entrée redirige automatiquement vers Google en l'absence de session. L'espace étudiant propose le catalogue en consultation seule. L'espace gérant propose le suivi des retards, les fiches des étudiants concernés et le scan par caméra. Les essais avec vos identifiants OAuth, votre MySQL et votre caméra restent à réaliser après configuration.

## Documentation

- [Architecture et responsabilités](docs/architecture.md)
- [Description des tables, colonnes et insertions SQL](docs/database.md)
- [Installation et configuration Google OAuth](docs/google-oauth.md)
- [Lancement local sur votre serveur XAMPP](docs/local-launch.md)
- [Données temporaires et commande de chargement](docs/demo-data.md)
- [Catalogue étudiant et vérification de l'étape 3](docs/student-catalogue.md)
- [Dashboard, configuration SMTP, worker et planificateur](docs/manager-dashboard.md)
- [Scan caméra, règles de circulation et essais de l'étape 5](docs/qr-circulation.md)

## Installation de l'étape 1

Prérequis : PHP 8.2 ou supérieur, Composer et MySQL 8.0.16 ou supérieur, avec les extensions Laravel dont `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `dom` et `xml`. `pdo_sqlite` sert aux tests isolés. Node.js et npm sont nécessaires pour compiler Tailwind et les scripts avec Vite.

```powershell
composer install
```

Pour une nouvelle installation seulement, créer `.env` à partir de `.env.example` sans écraser un fichier existant :

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Dans MySQL, créer une base vide :

```sql
CREATE DATABASE est_fes_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Renseigner les paramètres de connexion dans `.env` :

```dotenv
APP_NAME="Bibliothèque EST Fès"
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=est_fes_library
DB_USERNAME=votre_utilisateur_mysql
DB_PASSWORD=votre_mot_de_passe_mysql
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Appliquer les migrations sur cette base vide :

```powershell
php artisan config:clear
php artisan migrate
```

Poursuivre avec la [configuration Google OAuth et la compilation du frontend](docs/google-oauth.md) avant de lancer l'application.

Le dossier initial contenait déjà une base SQLite avec les migrations standard appliquées. Elle n'est pas modifiée par cette livraison. La migration initiale `users` a été adaptée au schéma final sans mot de passe : utiliser une **nouvelle base MySQL** pour ce projet. Exécuter `migrate` sur l'ancienne base SQLite ne mettrait pas à jour la migration déjà enregistrée. Aucun `migrate:fresh` n'est nécessaire sur vos données existantes.

Les données de référence sont insérées directement en base par l'EST Fès. `DatabaseSeeder` est volontairement vide ; aucune donnée d'exemple n'est créée lors d'une installation.

Pour les essais avant votre import réel, un seeder facultatif prépare quatre étudiants (dont `hamza.tazi5@usmba.ac.ma`), un gérant fictif, quatre ouvrages, douze exemplaires et six emprunts. Voir le [jeu de démonstration](docs/demo-data.md) pour le charger explicitement dans une base de test vide avec `php artisan db:seed --class=DemoDataSeeder`.

## Vérification

```powershell
php vendor/phpunit/phpunit/phpunit
npm test
npm run build
```

`phpunit.xml` force une base SQLite `:memory:` et neutralise `DB_URL`. Les tests ne se connectent pas à la base de travail. Ils vérifient les identifiants uniques, les deux rôles, les relations, l'interdiction de deux emprunts actifs du même exemplaire et l'abstraction utilisateurs.

L'appel direct à PHPUnit évite le sous-processus de `php artisan test`, qui échoue dans le bac à sable sur ce poste Windows avec le chemin accentué du projet. Le cache des résultats PHPUnit est désactivé pour éviter le même problème de chemin à l'écriture du cache. Les tests d'authentification simulent les réponses Google et ne nécessitent pas de vrais identifiants OAuth.

Le serveur XAMPP local est MariaDB 10.4.32, accessible sur le port 3306. La base dédiée `est_fes_library_demo` a été créée, les sept migrations ont été appliquées et `DemoDataSeeder` a été chargé. Le fichier `.env` utilise désormais cette base avec le pilote Laravel `mysql` ; la configuration antérieure est conservée dans `.env.backup`. L'ancienne base SQLite reste intacte. Les paramètres Google sont à renseigner suivant le [guide de lancement local](docs/local-launch.md).

Résultats : **130 tests PHP, 586 assertions, 15 tests JavaScript**, et compilation Vite réussie. Un contrôle transactionnel sur MariaDB a également vérifié la recherche, les retards, l'emprunt, le retour, l'annulation, la reprise d'une requête, la limite de trois prêts et le refus d'un autre emprunteur ; ses écritures ont été annulées après vérification. Les transactions concurrentes, l'exécution sur MySQL 8, la caméra physique et les connexions Google/SMTP réelles restent à vérifier. Pour la caméra sur téléphone, utiliser une URL HTTPS ; `localhost` reste utilisable sur l'appareil qui héberge le navigateur.

La configuration Google OAuth est décrite dans [sa documentation dédiée](docs/google-oauth.md). Le [guide du dashboard](docs/manager-dashboard.md) décrit le worker et le planificateur pour l'envoi quotidien à 08:00 au Maroc. Le transport de mail reste `log` par défaut ; aucun email réel n'est envoyé tant qu'un transport et les processus d'envoi ne sont pas configurés et lancés. Aucune dépendance hors de la stack demandée n'est ajoutée.

## Import Open Library

Dans le tableau de bord gestionnaire, ouvrez **Ajouter un ouvrage**. Saisissez un ISBN ou recherchez un titre ou un auteur, puis choisissez une édition. Vérifiez la fiche et cliquez sur **Enregistrer l’ouvrage**. La saisie manuelle reste disponible. Une notice enregistrée sans exemplaire apparaît comme indisponible dans le catalogue étudiant.

L’installation doit avoir appliqué les migrations Laravel, notamment `2026_11_11_000001_add_bibliographic_metadata_to_books`. Configurez `OPEN_LIBRARY_CONTACT_EMAIL` avec une adresse réellement suivie par l’établissement. Utilisez un cache Laravel partagé entre les processus web sur un hébergement multiprocessus afin de mutualiser le cache et la limitation des requêtes. Les recherches passent par Open Library. Les étudiants consultent les notices enregistrées dans ESThèque et leur navigateur charge les couvertures d’édition, lorsqu’elles existent.

Les tests simulant les réponses d’Open Library se lancent avec `php artisan test --filter=OpenLibraryImportTest`.
