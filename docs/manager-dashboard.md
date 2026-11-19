# Dashboard gérant et emails de retard — étape 4

> Guide historique. Pour l’implémentation actuelle des e-mails, les deux files et le déploiement Alwaysdata, consulter le [guide des relances automatiques](relances-automatiques.md).

## Parcours gérant

Après connexion Google avec un email académique enregistré comme gérant, `/gerant` affiche le suivi des retards. Le dashboard est conçu pour l'ordinateur et son contenu reste lisible sur mobile ; les graphiques s'empilent et le tableau peut défiler horizontalement.

Les trois indicateurs sont le nombre d'étudiants en retard, le nombre d'exemplaires en retard et le retard maximal. Deux graphiques Chart.js représentent les étudiants par niveau de retard et les exemplaires par niveau. Un étudiant est classé une seule fois, selon son retard le plus élevé ; chacun de ses exemplaires est classé séparément dans le second graphique.

Le tableau présente les étudiants du retard le plus élevé au moins élevé. Cliquer sur le nom ouvre `/gerant/retards/etudiants/{id}` : nom, email académique et exemplaires actuellement en retard avec leur titre, auteur, identifiant QR, date d'emprunt, échéance, nombre de jours et niveau de retard. Aucun historique supplémentaire n'est exposé. Si l'étudiant n'a plus de prêt en retard, sa fiche de retard renvoie HTTP 404 et il n'apparaît plus dans le dashboard.

Les routes sont protégées par `auth` et `role:manager`. Aucun accès étudiant aux statistiques ou aux fiches n'est permis. La consultation du dashboard ne déclenche aucun email.

## Calcul partagé des retards

`Loan::overdue()` sélectionne uniquement les prêts avec `returned_at IS NULL`, `cancelled_at IS NULL` et `due_at < maintenant`. Les dates sont stockées en UTC. Leur affichage et la planification utilisent `Africa/Casablanca`, défini dans `config/library.php`.

`OverduePolicy` est la seule source des calculs de retard pour le dashboard et les emails :

- L'échéance exacte ne constitue pas encore un retard.
- Chaque journée de 24 heures entamée après l'échéance compte pour une journée de retard.
- Jaune, retard léger : nombre de jours inférieur ou égal à un tiers de `loan_duration_days`.
- Orange, retard moyen : au-delà du premier tiers et jusqu'aux deux tiers inclus.
- Rouge, retard élevé : au-delà des deux tiers.

Avec le paramètre unique actuel de 30 jours, cela donne 1–10 jours, 11–20 jours et 21 jours ou plus. Aucun seuil de durée n'est recopié dans les vues, dans le JavaScript ou dans le modèle d'email. Une modification du paramètre est couverte par les tests avec une durée de 60 jours.

`OverdueReport` prépare les données partagées à partir des prêts et de l'interface `UserDirectory`. La recherche groupée `findByIds` évite une lecture utilisateur par ligne. Cette opération devra aussi être implémentée dans un futur adaptateur API ; elle retourne une table d'identités `DirectoryUser` indexée par identifiant local stable. Les identités absentes, extérieures à `usmba.ac.ma` ou qui ne sont plus étudiantes sont exclues des rapports et des envois.

La consultation suivante recalcule les statistiques et la liste. Les prêts retournés ou annulés disparaissent donc sans tâche de nettoyage. Le [traitement QR](qr-circulation.md) de l'étape 5 modifie les prêts et statuts dans la même transaction.

## Emails automatiques

Vous avez choisi un envoi **chaque jour à 08:00, heure du Maroc**. La planification est déclarée dans `routes/console.php` avec le fuseau `Africa/Casablanca` ; elle n'utilise pas de décalage UTC fixe.

Le parcours d'envoi est :

1. La commande `library:send-overdue-reminders` parcourt les étudiants ayant des prêts actifs en retard, par groupes de 200 identifiants.
2. Elle vérifie les identités dans l'annuaire et place une tâche `SendOverdueReminder` par étudiant admissible dans la file Laravel.
3. À son exécution, chaque tâche recharge l'identité et les prêts. Elle ne transmet pas d'adresse email ou de liste de prêts périmée depuis le moment de la planification.
4. Un seul email Laravel Mail regroupe les exemplaires encore en retard de cet étudiant. Si tous ont été retournés ou annulés avant le traitement, aucun email n'est envoyé. Si une partie seulement a été retournée, l'email contient uniquement les prêts restants.

Le message indique le titre, l'auteur, l'identifiant d'exemplaire, l'échéance, les jours et le niveau de retard, et demande de rapporter les exemplaires à la bibliothèque. Il n'ajoute aucune pénalité ni autre notification. Les noms et données importées sont échappés dans le HTML.

Le planificateur utilise `withoutOverlapping` et les tâches `ShouldBeUnique` évitent deux tâches simultanément en attente pour le même étudiant. Cette unicité n'est pas un journal des emails déjà envoyés : relancer volontairement la commande après la fin du traitement peut produire un nouvel envoi. L'automatisation normale repose sur un seul planificateur, à l'horaire convenu.

Un échec de transport peut être réessayé trois fois, avec attente entre les tentatives. Une tâche définitivement échouée est conservée dans la table technique Laravel `failed_jobs`. Aucun écran de gestion des envois n'est ajouté.

## Configuration pour les essais

La base MySQL doit contenir les migrations de l'étape 1, notamment `jobs`, `failed_jobs`, `cache` et `cache_locks`. Aucune nouvelle migration n'est nécessaire pour cette étape.

Pour les données fictives, conserver :

```dotenv
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
```

Le transport `log` écrit les emails dans les journaux Laravel et n'envoie rien aux adresses fictives. Le [jeu de démonstration](demo-data.md) contient deux étudiants en retard et trois exemplaires en retard : Hamza a un retard léger et un moyen ; Salma a un retard élevé. Les jours évoluent ensuite avec le temps.

Le gérant du jeu est une identité fictive. Une connexion Google réelle au dashboard demande un vrai compte académique déjà présent avec le rôle `manager`. Aucun accès de démonstration contournant Google n'est ajouté.

## Lancement local

Après configuration de MySQL et [Google OAuth](google-oauth.md), compiler le frontend puis lancer l'application :

```powershell
npm ci
npm run build
php artisan config:clear
php artisan serve --host=localhost --port=8000
```

Dans un second terminal, lancer le worker qui traite la file :

```powershell
php artisan queue:work database --queue=library-reminders,default --sleep=3 --tries=3 --timeout=60
```

Dans un troisième terminal, lancer le planificateur Laravel :

```powershell
php artisan schedule:work
```

Vérifier l'horaire sans déclencher d'envoi :

```powershell
php artisan schedule:list --timezone=Africa/Casablanca
```

Pour essayer immédiatement le parcours d'envoi avec `MAIL_MAILER=log`, la commande suivante place les tâches dans la file. Le worker doit tourner pour produire les messages dans les journaux :

```powershell
php artisan library:send-overdue-reminders
```

Ces commandes de lancement sont des instructions pour votre environnement. Aucun worker persistant, planificateur système ou envoi réel n'a été démarré par cette livraison. La configuration initiale de l'application pointe encore vers l'ancienne base SQLite ; sélectionner la nouvelle base MySQL avant le lancement de ces processus.

## Envoi SMTP réel et hébergement

Remplacer le transport `log` avec les paramètres fournis par le serveur de mail de l'école. Exemple à adapter :

```dotenv
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=serveur_smtp_de_l_ecole
MAIL_PORT=587
MAIL_USERNAME=identifiant_smtp
MAIL_PASSWORD=secret_smtp
MAIL_FROM_ADDRESS=adresse_expediteur_autorisee
MAIL_FROM_NAME="Bibliothèque EST Fès"
```

Les paramètres de connexion et le protocole doivent correspondre au serveur fourni. Après une modification de `.env`, exécuter `php artisan config:clear` puis redémarrer le worker pour qu'il recharge sa configuration. Utiliser les données réelles avant l'activation du transport SMTP.

Sur un serveur Linux, maintenir le worker avec le gestionnaire de processus du serveur et exécuter le planificateur une fois par minute avec une unique entrée cron, en remplaçant le chemin :

```cron
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

Le planificateur Laravel choisit lui-même 08:00 dans le fuseau du Maroc. Sous Windows, `schedule:work` peut être utilisé pendant le développement, ou `schedule:run` peut être lancé chaque minute par le Planificateur de tâches du système. Aucun autre service de file, plugin ou fournisseur externe n'est requis par le code.

## Fichiers principaux

| Fichier | Responsabilité |
| --- | --- |
| `app/Services/OverduePolicy.php` | Jours de retard et seuils dérivés de la durée |
| `app/Services/OverdueReport.php` | Statistiques, graphiques et fiches via l'annuaire |
| `app/Enums/OverdueLevel.php` | Niveaux, libellés et couleurs des graphiques |
| `app/Http/Controllers/Manager/OverdueController.php` | Deux routes gérant de consultation |
| `app/Console/Commands/QueueOverdueReminders.php` | Mise en file des étudiants concernés |
| `app/Jobs/SendOverdueReminder.php` | Relecture avant envoi et traitement différé |
| `app/Mail/OverdueReminder.php` | Email Laravel Mail |
| `resources/views/manager/` | Dashboard et fiche étudiant |
| `resources/views/mail/overdue-reminder.blade.php` | Contenu de l'email |
| `resources/js/manager-charts.js` | Deux graphiques Chart.js chargés seulement pour le dashboard |
| `routes/console.php` | Planification quotidienne |

## Vérification

```powershell
php vendor/phpunit/phpunit/phpunit
npm run build
```

Résultat de cette livraison : **104 tests réussis, 415 assertions**, et compilation Vite réussie. Les nouveaux tests couvrent les limites des niveaux, les statistiques sans double comptage d'étudiant, les droits, le contenu des fiches et des emails, les retours intervenus avant l'envoi, l'unicité des tâches en attente, un lot de plus de 200 étudiants et le traitement d'une tâche sérialisée dans la file en base.

Les tests utilisent SQLite en mémoire et des emails simulés ; aucun email réel n'a été transmis. Le rendu des graphiques dans un navigateur et la connexion SMTP réelle restent à vérifier dans votre environnement, ainsi que l'exécution sur MySQL 8.

Références : [Laravel Mail](https://laravel.com/docs/12.x/mail), [planification Laravel](https://laravel.com/docs/12.x/scheduling), [intégration Chart.js](https://www.chartjs.org/docs/latest/getting-started/integration.html).
