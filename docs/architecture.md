# Architecture — EST Fès

## Organisation

Le projet conserve le socle Laravel 12 existant. Les étapes 1 à 4 validées implémentent le schéma, l'annuaire, Google, le catalogue et les retards avec emails quotidiens. L'étape 5 ajoute le scan par caméra et les transactions de circulation. Les cinq étapes sont maintenant implémentées, avec essais réels à réaliser après configuration.

```text
app/
  Auth/DirectoryUserProvider.php          Sessions Laravel via l'annuaire
  Contracts/UserDirectory.php              Contrat de lecture des utilisateurs
  Data/DirectoryUser.php                  Identité indépendante d'Eloquent
  Enums/UserRole.php                      student / manager
  Enums/CopyStatus.php                    available / borrowed
  Models/User.php                         Persistance interne de l'annuaire
  Models/Book.php                         Ouvrage et ses exemplaires
  Models/Copy.php                         Exemplaire, ouvrage et emprunts
  Models/Loan.php                         Dates et exemplaire d'un emprunt
  Repositories/EloquentUserDirectory.php  Adaptateur MySQL de l'annuaire
  Support/AcademicEmail.php               Normalisation et contrôle du domaine académique
  Providers/AppServiceProvider.php        Liaison du contrat à son adaptateur
  Http/Controllers/Auth/GoogleAuthController.php  Redirection et callback Google
  Http/Controllers/HomeController.php     Redirection par rôle
  Http/Controllers/Student/CatalogueController.php  Catalogue et fiche d'ouvrage
  Http/Controllers/Manager/OverdueController.php   Dashboard et fiche de retard
  Http/Controllers/Manager/ScanController.php      Page de scan et endpoints JSON
  Http/Requests/CatalogueSearchRequest.php Recherche validée
  Http/Middleware/RequireRole.php         Protection des deux interfaces
  Services/OverduePolicy.php              Calcul partagé des retards
  Services/OverdueReport.php              Rapports depuis prêts et annuaire
  Services/CirculationService.php         Identification, prêt, retour, annulation et reprise
  Models/ScanOperation.php                Résultats techniques des demandes UUID
  Console/Commands/QueueOverdueReminders.php  Mise en file des emails
  Jobs/SendOverdueReminder.php            Relecture et envoi par étudiant
  Mail/OverdueReminder.php                Email Laravel Mail
config/library.php                       Durée, limite, fuseau et horaire quotidien
config/auth.php                          Fournisseur directory, sans mot de passe
resources/views/                         Layout, erreurs, catalogue, dashboard, fiches et email
resources/js/manager-charts.js           Graphiques Chart.js
resources/js/qr-scanner.js               Caméra et échanges serveur
resources/js/scan-session.js             Paire, blocage des lectures et reprise
routes/console.php                       Envoi quotidien à 08:00 au Maroc
database/migrations/                     Schéma métier et infrastructure Laravel
database/seeders/DatabaseSeeder.php       Vide : données fournies directement en base
database/seeders/DemoDataSeeder.php       Jeu facultatif sur base de test vide
tests/Feature/                           Contraintes et annuaire
docs/                                    Architecture et dictionnaire de données
```

## Accès aux utilisateurs

Les données utilisateurs sont lues via `UserDirectory` : recherche par identifiant, par lot d'identifiants, par email et par QR de carte étudiante. L'adaptateur Eloquent est la seule classe applicative qui interroge `User`. Les objets `DirectoryUser` exposent l'identifiant stable, le nom, l'email, le rôle et l'identifiant QR ; ils n'héritent pas d'Eloquent.

Pour brancher une API, une classe `ApiUserDirectory` implémentera le même contrat puis remplacera la liaison dans `AppServiceProvider`. Aucun client HTTP ou endpoint API n'est ajouté aujourd'hui. Les identifiants doivent rester stables et correspondre aux utilisateurs référencés par les emprunts locaux. Si l'API devient la source principale, les lignes locales référencées par les clés étrangères devront être conservées/synchronisées par votre alimentation des données.

Le fournisseur `DirectoryUserProvider` utilise aussi cet annuaire pour recharger l'identité en session à chaque requête. Les emails sont comparés après suppression des espaces périphériques et conversion en minuscules ; l'import doit stocker les emails sous cette forme. Le domaine académique exact `usmba.ac.ma` est centralisé dans `config/library.php` et vérifié au callback comme au rechargement de session via `AcademicEmail`.

## Authentification implémentée — étape 2

L'entrée redirige vers Google en l'absence de session Laravel. Le callback Socialite vérifie `state` et l'email confirmé par Google, recherche l'email dans l'annuaire puis ouvre une session uniquement pour un utilisateur déjà présent. Le rôle provient de la base, jamais du navigateur. Les middlewares protègent les routes étudiant et gérant côté serveur. Aucune création automatique d'utilisateur ni authentification par mot de passe n'est exposée. Voir les [instructions Google OAuth](google-oauth.md).

Une session Google déjà ouverte peut permettre à Google de terminer l'identification sans ressaisie. Le site doit néanmoins effectuer l'aller-retour OAuth ; Google peut imposer une sélection de compte ou un consentement selon l'état du compte. L'application ne peut pas supprimer ces contrôles Google.

## Catalogue implémenté — étape 3

Contrôleur de consultation, vues Blade responsive et Tailwind : liste compacte extensible, recherche titre/auteur et page de détails avec couverture, sommaire et résumé. Les ouvrages sans exemplaire disponible restent visibles avec « Indisponible », conformément à votre choix. La disponibilité est calculée depuis les exemplaires `available` à chaque consultation, sans cache. L'expansion utilise les éléments HTML natifs `details` et `summary` ; aucun package JavaScript supplémentaire n'est ajouté. Aucune route d'écriture du catalogue n'est exposée. Voir le [parcours et les vérifications du catalogue](student-catalogue.md).

## Dashboard et emails implémentés — étape 4

Routes réservées aux gérants : dashboard sur ordinateur, statistiques de retard, deux graphiques Chart.js et détails de chaque étudiant concerné avec ses exemplaires en retard. Les rapports sont calculés depuis les emprunts actifs et leur échéance, sans conserver de couleur ou compteur périmé en base. Un étudiant est compté une seule fois selon son retard maximal ; les exemplaires sont comptés individuellement. Les écrans de scan de l'étape 5 resteront utilisables sur ordinateur et mobile.

Vous avez choisi un envoi quotidien à 08:00, fuseau `Africa/Casablanca`. La commande planifiée place un job unique en attente par étudiant concerné dans la file Laravel. Le job recharge l'identité et les prêts au traitement puis transmet un email Laravel Mail regroupé. Un retour ou une annulation intervenu avant son traitement est pris en compte. Le lancement des workers, du planificateur et la configuration SMTP sont décrits dans le [guide du dashboard](manager-dashboard.md). Aucune autre notification n'est créée.

## Cohérence des prêts implémentée — étape 5

`CirculationService` traite les deux identifiants QR dans une transaction. Il réserve d'abord la demande UUID, puis verrouille l'identifiant local de l'étudiant, l'exemplaire et les prêts concernés, dans cet ordre. La lecture des prêts actifs est verrouillée afin de consulter l'état courant sous MySQL, même après une première lecture ayant établi un snapshot. L'identité de l'étudiant est obtenue via l'annuaire ; le verrou local ne constitue pas une autre source de données utilisateur.

- Exemplaire disponible : vérifier le rôle étudiant et la limite de prêts, créer l'emprunt et passer l'exemplaire à `borrowed`.
- Exemplaire emprunté par cet étudiant : dater le retour et repasser l'exemplaire à `available`.
- Exemplaire emprunté par un autre étudiant : refuser sans modifier les données.
- Annulation d'un emprunt erroné encore actif : dater `cancelled_at` et libérer l'exemplaire dans une même transaction. Un retour déjà enregistré ne sera pas annulé par cette action.

La colonne générée et unique `active_copy_id` protège aussi contre deux emprunts actifs du même exemplaire. L'annulation conserve la ligne technique nécessaire pour distinguer un prêt annulé d'un retour ; aucune page d'historique n'est prévue.

Le navigateur suspend les détections dès la paire de QR complète. Le résultat de chaque demande est conservé dans `scan_operations` : rejouer son UUID restitue le résultat sans inverser le prêt/retour. Une nouvelle opération volontaire demande « Nouveau scan » et reçoit un nouvel UUID. Une demande réseau incertaine reste bloquée jusqu'à vérification ; la reprise dans le stockage d'onglet est séparée par gérant. Aucun écran d'historique ni nouvelle automatisation n'est ajouté. Voir le [guide du scan](qr-circulation.md).

## Paramètres et retards

`config/library.php` contient un seul paramètre de durée, `loan_duration_days = 30`, et la limite `max_active_loans = 3`. Les tiers de durée sont calculés depuis ce paramètre dans `OverduePolicy`, partagé par le dashboard et les emails, sans duplication dans les vues ou le JavaScript. Le fuseau d'affichage et de planification ainsi que l'horaire accepté y sont aussi centralisés.

L'échéance sera fixée à la création : `due_at = borrowed_at + durée configurée`. Elle est conservée pour ne pas déplacer les échéances existantes lors d'un éventuel changement futur de configuration. Les dates métier sont stockées en UTC ; l'affichage utilisera `Africa/Casablanca`.

À l'échéance exacte, le prêt n'est pas encore en retard. Dès que `maintenant > due_at`, le nombre de jours de retard est arrondi au jour supérieur : les premières 24 heures entamées valent un jour de retard. Pour 30 jours : jaune jusqu'à `durée / 3` inclus (1–10), orange jusqu'à `2 × durée / 3` inclus (11–20), rouge au-delà (21 et plus). Un emprunt retourné ou annulé est exclu immédiatement des retards.

## Références techniques

Les migrations, colonnes générées et clés étrangères utilisent les mécanismes de [Laravel 12](https://laravel.com/docs/12.x/migrations). La liaison de l'annuaire suit l'[injection d'interfaces du conteneur Laravel](https://laravel.com/docs/12.x/container). Aucun framework ou service d'hébergement supplémentaire n'est introduit.
