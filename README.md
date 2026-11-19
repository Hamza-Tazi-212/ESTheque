# ESThèque — Bibliothèque de l’EST Fès

Application de gestion de bibliothèque universitaire avec deux espaces, Étudiant et Gestionnaire. Le catalogue, les exemplaires physiques et les emprunts sont conservés dans la base de données ESThèque.

**Application en ligne** — https://biblio-estfes-demo.alwaysdata.net/

La connexion utilise Google. Le compte doit être enregistré dans l’annuaire de l’application. Le rôle enregistré détermine l’espace accessible.

## Fonctionnalités actuelles

### Espace étudiant

- Catalogue local avec pagination de 10 ouvrages, recherche et suggestions.
- Filtres de disponibilité et de domaine, avec un menu Domaine sur mobile.
- Couvertures Open Library et illustration locale lorsque la couverture est absente.
- Détails de l’ouvrage dans une fenêtre modale.
- Disponibilité calculée à partir des exemplaires et prêts enregistrés.
- Profil Google et déconnexion, avec un menu de profil compact sur mobile.

### Espace gestionnaire

- Tableau de bord des prêts actifs, retards et échéances.
- Distinction entre le nombre d’exemplaires en retard et le nombre d’étudiants concernés.
- Filtres des prêts et informations sur les emprunteurs.
- Relances individuelles et groupées par e-mail, via une file de traitement.
- Scanner de QR d’exemplaire et de carte étudiante pour l’emprunt et le retour.
- Limite configurée de 3 prêts actifs et durée de prêt de 30 jours.
- Arrêt de la caméra après une opération et redémarrage explicite pour le scan suivant.

Les autorisations sont contrôlées côté serveur. Le rôle provient de l’annuaire ESThèque.

## Open Library et inventaire

Open Library est le fournisseur externe de notices bibliographiques et de couvertures. Les étudiants recherchent les ouvrages déjà enregistrés dans ESThèque.

L’import des notices depuis une liste ISBN se lance avec cette commande.

```sh
php artisan library:import-stock-books --file=/chemin/stock-isbn.txt
```

Sans option, la commande lit `storage/app/private/stock-isbn.txt`. Le fichier contient un ISBN par ligne. La commande vérifie les ISBN, évite les doublons et traite au maximum 20 nouvelles notices par exécution. Une liste vide ne crée aucune notice.

Les ISBN utilisés pour la présentation correspondent à des livres réels. Les exemplaires et prêts de démonstration représentent un inventaire de présentation. Leur présence ne certifie pas le stock physique de l’établissement.

L’import de métadonnées crée une notice. L’ajout d’exemplaires physiques est une opération distincte. La disponibilité dépend de ces exemplaires et des prêts actifs.

Des routes de saisie et de prévisualisation d’ouvrages existent aussi dans le code. Le catalogue de présentation utilise l’import ISBN.

## Installation

Prérequis — PHP 8.2 ou supérieur, Composer, MySQL ou MariaDB compatible avec les migrations, Node.js et npm. Les tests PHP utilisent SQLite en mémoire avec `pdo_sqlite`.

```sh
composer install
npm ci
```

Créer `.env` à partir de `.env.example` pour une nouvelle installation, puis configurer la base, l’URL de l’application et les paramètres Google OAuth.

```sh
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Le serveur web doit exposer le dossier `public/`. Utiliser HTTPS pour la caméra sur téléphone. L’URI de retour Google doit correspondre exactement à `/auth/google/callback` sur le domaine configuré.

Les adresses académiques et les deux exceptions personnelles déjà admises par le serveur sont prises en charge. Le rôle enregistré reste nécessaire pour autoriser chaque espace. Le serveur de présentation comporte aussi un réglage spécifique aux comptes de démonstration. Le dépôt conserve leur restriction locale à localhost.

Les comptes et leurs rôles se gèrent dans l’annuaire de l’installation. Les données et changements de rôles du serveur hébergé sont conservés dans sa base. Une installation depuis GitHub nécessite son propre annuaire.

## E-mails et tâches de fond

Configurer le transport SMTP, l’adresse d’envoi et la file de traitement dans l’environnement de déploiement.

```sh
php artisan queue:work
php artisan schedule:run
```

Le worker traite les messages en attente. Le planificateur Laravel doit être appelé chaque minute. La commande quotidienne `library:send-overdue-reminders` est programmée à 08:00 dans le fuseau `Africa/Casablanca`. Sur Alwaysdata, les tâches se configurent depuis l’administration de l’hébergement.

L’import périodique ISBN nécessite une tâche dédiée exécutant `library:import-stock-books`. Cette commande ne figure pas dans le planning Laravel du dépôt.

## Vérification

```sh
php vendor/phpunit/phpunit/phpunit
npm test
npm run build
```

Les tests PHP simulent notamment Google, Open Library et les e-mails. Leur configuration utilise une base SQLite en mémoire. Les essais d’envoi et de circulation sur une installation réelle doivent utiliser des comptes et exemplaires de test.

Lors du contrôle du 19 novembre 2026, l’application hébergée répondait et ses routes gestionnaire exigeaient une session et le rôle Gestionnaire. SMTP et la file de données étaient configurés. Les 15 tests JavaScript du scanner ont réussi. La syntaxe des fichiers PHP de connexion synchronisés a été vérifiée.

La copie isolée a passé 139 tests PHP (709 assertions), les 15 tests JavaScript et la compilation Vite. La caméra physique et la réception d’un nouvel e-mail n’ont pas été testées lors de ce contrôle.

## Historique

Les tags permettent de retrouver les états publiés précédemment.

| Tag | État |
| --- | --- |
| `prototype-2026-11-09` | Plus ancien instantané retrouvé, correspondance exacte avec V1 non confirmée |
| `V2` | Deuxième état sauvegardé |
| `V3` | Troisième état sauvegardé |
| `VersionFinale` | État final sauvegardé le 12 novembre 2026 |

La branche `main` accueille les mises à jour ultérieures.

## Documentation complémentaire

- [Architecture](docs/architecture.md)
- [Base de données](docs/database.md)
- [Google OAuth](docs/google-oauth.md)
- [Lancement local](docs/local-launch.md)
- [Démonstration](docs/demo-data.md)
- [Catalogue étudiant](docs/student-catalogue.md)
- [Tableau de bord et relances](docs/manager-dashboard.md)
- [Scanner et circulation](docs/qr-circulation.md)

Ces guides historiques complètent le code. Le présent README décrit l’état vérifié lors de la mise à jour du 19 novembre 2026.
