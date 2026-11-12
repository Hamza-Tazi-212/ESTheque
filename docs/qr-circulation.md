# Emprunt et retour par QR — étape 5

## Utilisation

L'espace gérant propose deux liens : **Suivi des retards** et **Scanner les QR**. La page `/gerant/scan` est réservée aux gérants et fonctionne avec la caméra du navigateur, sur ordinateur ou mobile.

1. Appuyer sur **Activer la caméra** et autoriser son utilisation dans le navigateur.
2. Présenter le QR d'un exemplaire et celui de la carte étudiante, dans l'ordre souhaité. Les deux identités reconnues s'affichent sur la page.
3. Dès que la paire est complète, l'application enregistre automatiquement l'opération, sans confirmation d'emprunt ou de retour.
4. Lire le résultat. Si un emprunt a été enregistré par erreur, **Annuler cet emprunt** libère l'exemplaire et la place de l'étudiant dans sa limite de prêts.
5. Utiliser **Nouveau scan** pour l'opération suivante. Avant tout enregistrement, **Recommencer les scans** permet de repartir si une mauvaise carte ou un mauvais exemplaire a été présenté.

Le lecteur se met en pause après la paire complète : les images successives des mêmes QR ne déclenchent pas une seconde opération. Le bouton de nouveau scan prépare une nouvelle paire ; il ne confirme pas l'opération précédente, qui est déjà enregistrée. Un retour enregistré ne peut pas être annulé avec le bouton d'annulation d'emprunt.

Le même bouton passe du vert **Activer la caméra** au rouge **Arrêter la caméra** lorsque la caméra est activée. Un second clic libère la caméra et rétablit le bouton vert. Le lecteur est également arrêté quand la page passe en arrière-plan ou est quittée. Le décodage est fait localement par `html5-qrcode` ; le serveur reçoit les identifiants textuels, jamais le flux vidéo. Aucun import d'image ni champ de saisie manuelle des QR n'est ajouté.

## Décision automatique

| État de l'exemplaire et des scans | Résultat |
| --- | --- |
| Disponible, carte étudiante valide, moins de trois prêts actifs | Nouvel emprunt, échéance calculée depuis `loan_duration_days`, statut `borrowed` |
| Disponible, étudiant ayant déjà trois prêts actifs | Refus avec message explicite ; aucun prêt n'est créé |
| Emprunté par l'étudiant scanné | Retour daté et statut `available`, même si cet étudiant est à sa limite de prêts |
| Emprunté par un autre étudiant | Refus sans modification |
| Deux cartes ou deux exemplaires | Refus : une carte et un exemplaire sont nécessaires |
| QR inconnu, carte non académique ou identifiant ambigu entre carte et exemplaire | Refus avec message explicite |
| Statut de l'exemplaire incohérent avec le prêt actif importé | Refus ; les données doivent être corrigées dans votre source |

La durée reste centralisée à 30 jours et la limite à 3 dans `config/library.php`. Les QR sont des identifiants opaques : aucun préfixe n'est exigé par le lecteur et une chaîne qui ressemble à une URL n'est pas ouverte. Le texte exact est conservé, avec une longueur maximale de 191 caractères. Les préfixes distincts conseillés dans le dictionnaire de données évitent les ambiguïtés entre cartes et exemplaires.

Les utilisateurs sont résolus via `UserDirectory`. Seules les cartes appartenant à un étudiant du domaine exact `usmba.ac.ma` sont acceptées. Les identifiants locaux stables doivent rester présents pour les relations des prêts, même lors du futur branchement d'une API.

## Transactions et requêtes répétées

Le service `CirculationService` réalise le prêt, le retour ou l'annulation dans une transaction. Après la réservation de la demande technique, il verrouille l'identifiant local de l'étudiant, puis l'exemplaire et les prêts concernés. La lecture des prêts actifs pour vérifier la limite est aussi une lecture verrouillée, afin de consulter l'état courant avec l'isolation MySQL `REPEATABLE READ`. La colonne unique `loans.active_copy_id` complète la protection contre plusieurs prêts actifs du même exemplaire.

Le verrou sur `users` ne lit que l'identifiant nécessaire à la relation locale ; les noms, rôles, emails et cartes continuent de provenir de l'annuaire abstrait. Une erreur d'écriture annule ensemble les changements de prêt, de statut et de demande.

Chaque demande possède un UUID généré dans le navigateur. La nouvelle table technique `scan_operations` conserve son gérant, l'empreinte de la paire ou de l'annulation, le statut HTTP et le résultat. Rejouer la même demande retourne le même résultat sans transformer un emprunt en retour, ni un retour en nouvel emprunt. Réutiliser cette clé pour un autre contenu ou un autre gérant est refusé. Les refus métier sont également conservés ; une nouvelle tentative volontaire après correction demande de nouveaux scans.

Si la connexion coupe avant réception du résultat, la page conserve la demande et propose **Vérifier l'opération**, avec le même UUID. Elle bloque le démarrage d'une nouvelle paire tant que le résultat est incertain. La demande est conservée en mémoire et, lorsque le navigateur le permet, dans le stockage de l'onglet, séparément pour chaque gérant. Un rechargement peut alors reprendre sa vérification. Si la session a expiré, la reconnexion Google reste nécessaire.

Deux scans volontairement distincts ont des UUID distincts : après un emprunt, rescanner la même paire lors d'une nouvelle opération enregistre donc bien un retour. Aucun délai minimal de retour n'est imposé. Aucun historique de scans n'est exposé dans l'interface et aucune tâche automatique de purge n'est ajoutée.

## Mise à jour de la disponibilité et des retards

Un retour ou une annulation met à jour `loans` et `copies` dans la même transaction. L'exemplaire redevient disponible dans le catalogue à la consultation suivante. Le prêt cesse immédiatement d'être sélectionné par `Loan::overdue()` : il disparaît des rapports rechargés et des emails de retard qui ne sont pas encore traités. Il n'y a pas d'actualisation périodique du navigateur ni de notification supplémentaire.

Annuler un ancien prêt déjà retourné ou annulé ne peut pas libérer un exemplaire emprunté depuis par quelqu'un d'autre. Une annulation rejouée avec son UUID original restitue seulement le résultat historique de cette demande, sans toucher au prêt suivant.

## Installation et caméra

Sur la base MySQL configurée pour le projet, appliquer la nouvelle migration technique puis compiler :

```powershell
composer install
npm ci
php artisan migrate
npm run build
php artisan serve --host=localhost --port=8000
```

L'ancienne base SQLite du socle ne doit pas servir de base applicative ; les instructions de nouvelle installation MySQL figurent dans le [README](../README.md). Sur ce poste, `.env` utilise maintenant la base `est_fes_library_demo` du serveur XAMPP ; voir le [lancement local](local-launch.md). L'ancienne base SQLite n'a pas été modifiée.

L'accès caméra nécessite un contexte sécurisé : HTTPS sur votre domaine, ou `http://localhost` sur l'appareil qui ouvre la page. Une adresse HTTP du réseau local, telle que celle d'un ordinateur ouverte depuis un téléphone, ne constitue pas `localhost` pour ce téléphone. Pour l'essai mobile, utiliser une URL HTTPS accessible depuis le téléphone, avec l'URI de callback correspondante enregistrée dans [Google OAuth](google-oauth.md).

Le code privilégie la caméra arrière lorsqu'elle existe et laisse le navigateur choisir une caméra disponible sur ordinateur. Les refus d'autorisation, les sessions expirées et les indisponibilités de caméra ou de réseau sont affichés en français. Aucun accès direct de démonstration contournant Google n'est ajouté.

## Scénarios avec les données temporaires

Charger le [jeu de démonstration](demo-data.md) dans une base de test vide, puis utiliser ses valeurs pour vos QR de test :

- **Emprunt simple** : carte `student:DEMO-004` (Aya) et exemplaire disponible `copy:DEMO-ALG-02`.
- **Retour** : après « Nouveau scan », rescanner ces deux mêmes QR.
- **Quatrième exemplaire refusé** : carte `student:DEMO-001` (Hamza, trois prêts actifs) et un exemplaire disponible.
- **Mauvais étudiant** : carte d'Aya avec `copy:DEMO-RES-01`, emprunté par Salma.
- **Annulation** : enregistrer un nouvel emprunt pour Aya puis utiliser son bouton d'annulation.
- **Retard retiré du dashboard** : rendre `copy:DEMO-RES-01` avec `student:DEMO-002`, puis recharger le dashboard ; Salma n'y apparaît plus.

Ces essais nécessitent une connexion avec un vrai compte Google académique enregistré comme gérant. Les adresses fictives du jeu ne créent pas des comptes Google. Conserver `MAIL_MAILER=log` pendant les essais du jeu fictif.

Deux images PNG prêtes à afficher ou imprimer sont disponibles : [QR de l'exemplaire ALG-02](../public/demo/qr-exemplaire-alg-02.png) et [QR de la carte d'Aya](../public/demo/qr-carte-aya.png). Leurs contenus exacts ont été vérifiés par décodage des fichiers générés, avec la bibliothèque ZXing déjà incluse dans `html5-qrcode`. Ces fichiers sont des supports de test ; aucune interface de génération de QR n'est ajoutée.

## Routes et fichiers

| Route | Usage |
| --- | --- |
| `GET /gerant/scan` | Page de scan |
| `POST /gerant/scan/identifier` | Identifier une lecture sans modifier les prêts |
| `POST /gerant/scan/enregistrer` | Enregistrer la paire avec `request_id` et `qr_codes` |
| `POST /gerant/emprunts/{loan}/annuler` | Annuler un prêt actif avec `request_id` |

Toutes ces routes sont protégées par la session et le rôle gérant ; les POST utilisent la protection CSRF Laravel. Les étudiants sont refusés côté serveur.

Les principaux fichiers sont `app/Services/CirculationService.php`, `app/Http/Controllers/Manager/ScanController.php`, les Form Requests de circulation, `resources/views/manager/scan.blade.php`, `resources/js/qr-scanner.js` et `resources/js/scan-session.js`. Le module de caméra est chargé seulement sur la page de scan, via Vite. `html5-qrcode` est la seule dépendance ajoutée à cette étape ; elle répond à la bibliothèque de scan demandée.

## Vérification

```powershell
php vendor/phpunit/phpunit/phpunit
npm test
npm run build
```

Résultat : **130 tests PHP réussis, 586 assertions**, et **15 tests JavaScript réussis**. La compilation Vite produit un module QR distinct. Les tests PHP utilisent SQLite en mémoire et couvrent les opérations, les limites, les refus, l'annulation, les reprises réseau par UUID, la disponibilité, les retards, les rôles, CSRF et le rollback des écritures. Les tests JavaScript utilisent le runner natif de Node, sans dépendance de test supplémentaire, et vérifient la gestion de paire, les images répétées, les UUID, la reprise d'une demande et le cycle de caméra. Un passage en arrière-plan pendant l'attente de l'autorisation arrête le lecteur dès son ouverture ; plusieurs clics ou événements d'arrêt ne peuvent pas ouvrir ou arrêter simultanément le même lecteur.

Les opérations de circulation ont aussi été vérifiées dans une transaction annulée sur la base MariaDB locale : les données de démonstration sont restées intactes. Les essais de caméra physique, du rendu dans un navigateur et des transactions concurrentes sur MySQL 8 restent à effectuer. La suite SQLite ne valide pas les verrous MySQL. Aucun email réel n'a été envoyé lors des tests.

Références : [API html5-qrcode](https://scanapp.org/html5-qrcode-docs/docs/apis/classes/Html5Qrcode), [accès caméra du navigateur](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia), [transactions Laravel](https://laravel.com/docs/12.x/database#database-transactions), [verrous de lecture Laravel](https://laravel.com/docs/12.x/queries#pessimistic-locking).
