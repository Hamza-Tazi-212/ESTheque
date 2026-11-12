# Données temporaires de démonstration

Le seeder `DemoDataSeeder` prépare un jeu de données séparé des données réelles, avec votre adresse académique comme étudiant de test. Les autres noms et adresses sont inventés. Ces lignes ne créent pas de comptes Google et ne permettent pas de contourner la connexion Google.

## Comptes

| Nom dans la base de test | Email | Rôle | QR carte |
| --- | --- | --- | --- |
| Hamza Tazi — test | hamza.tazi5@usmba.ac.ma | Étudiant | `student:DEMO-001` |
| Salma El Alami — démo | salma.elalami2@usmba.ac.ma | Étudiant | `student:DEMO-002` |
| Youssef Bennani — démo | youssef.bennani3@usmba.ac.ma | Étudiant | `student:DEMO-003` |
| Aya Idrissi — démo | aya.idrissi4@usmba.ac.ma | Étudiant | `student:DEMO-004` |
| Nabil Amrani — gérant démo | nabil.amrani1@usmba.ac.ma | Gérant | Aucun |

Votre adresse permettra de vérifier la connexion à l'espace étudiant une fois Google OAuth configuré. Le gérant fictif sert aux tests des données et des droits ; une connexion réelle à cet espace nécessitera un vrai compte académique dont l'email est enregistré avec le rôle `manager`.

Le domaine accepté est exactement `usmba.ac.ma`. La partie avant `@` n'est pas soumise à une règle artificielle `prénom.nom + chiffre` : l'exemple fourni est accepté, de même que les autres adresses valides du domaine. Il faut également un email confirmé par Google et présent dans l'annuaire.

## Catalogue et emprunts

- Quatre ouvrages fictifs : algorithmique, bases de données relationnelles, programmation web et réseaux informatiques. Chacun a un résumé, un sommaire et une couverture SVG locale.
- Trois exemplaires par ouvrage, soit douze exemplaires : huit disponibles et quatre empruntés.
- Six emprunts au total : quatre actifs, un retourné et un annulé.
- Hamza possède trois prêts actifs : un retard léger, un retard moyen et un prêt encore dans le délai. Cela fournit aussi un cas de test pour le refus d'un quatrième prêt à l'étape QR.
- Salma possède un prêt en retard élevé. Youssef possède les deux prêts terminés. Aya n'a pas de prêt.

Les dates sont calculées au chargement depuis `config('library.loan_duration_days')`. Les retards de démonstration correspondent à une journée, un tiers de la durée plus une journée et deux tiers plus une journée. Ils évolueront ensuite avec le temps, comme de vrais emprunts.

Les identifiants d'exemplaire sont `copy:DEMO-ALG-01` à `03`, `copy:DEMO-BDD-01` à `03`, `copy:DEMO-WEB-01` à `03` et `copy:DEMO-RES-01` à `03`. Le premier exemplaire de chaque ouvrage est emprunté ; les autres sont disponibles.

Ces données permettent de consulter le [catalogue étudiant](student-catalogue.md), de tester le [dashboard et les emails de retard](manager-dashboard.md) en conservant `MAIL_MAILER=log`, puis d'essayer le [scan QR](qr-circulation.md) avec les identifiants ci-dessus.

## Chargement sur une base de test MySQL

Créer une base dédiée vide depuis votre outil MySQL :

```sql
CREATE DATABASE est_fes_library_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Dans `.env`, utiliser les paramètres de connexion de votre serveur MySQL, avec :

```dotenv
APP_ENV=local
DB_CONNECTION=mysql
DB_DATABASE=est_fes_library_demo
MAIL_MAILER=log
```

Conserver les identifiants Google décrits dans [la configuration OAuth](google-oauth.md). Le mode mail `log` conserve les essais d'emails dans les journaux.

Depuis le dossier du projet :

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
```

Le chargement est transactionnel et réservé aux environnements `local` et `testing`. Il refuse toute base contenant déjà des utilisateurs, ouvrages, exemplaires ou emprunts, y compris si le jeu de démonstration y a déjà été chargé. Il n'écrase et ne supprime aucune donnée existante.

`DatabaseSeeder` reste vide : `php artisan db:seed` sans le nom de classe ne charge jamais ces données. Aucun chargement n'est effectué au lancement de l'application.

Sur ce poste, la base `est_fes_library_demo` a été créée sur le serveur XAMPP existant (MariaDB 10.4.32, port 3306). Les migrations et ce seeder y ont déjà été exécutés. Le fichier `.env` pointe désormais vers cette base ; sa configuration antérieure est conservée dans `.env.backup`. Il ne faut pas relancer le seeder sur cette base déjà remplie. Le jeu est également vérifié automatiquement sur une base isolée en mémoire.

## Passage aux données réelles

Importer vos données réelles dans la base MySQL destinée à l'application, puis mettre `DB_DATABASE` à son nom dans `.env` et exécuter `php artisan config:clear`. Ne pas appeler `DemoDataSeeder` sur cette base. Les données fictives restent ainsi dans la base de démonstration, sans mélange avec l'import réel. Aucune interface d'import ou de gestion des utilisateurs/ouvrages n'est ajoutée.
