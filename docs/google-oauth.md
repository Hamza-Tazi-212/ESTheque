# Authentification Google — étape 2

## Parcours implémenté

Une visite sans session sur `/`, `/etudiant` ou `/gerant` redirige vers `/auth/google`, puis directement vers Google. Il n'y a pas de page de connexion locale. Socialite demande uniquement `openid`, `profile` et `email`, sans forcer une ressaisie ni une sélection de compte.

Google peut néanmoins afficher son écran de connexion, son choix de compte ou son consentement. Une session Google préexistante ne permet pas au site de supprimer ces écrans si Google les exige.

Le callback vérifie le paramètre OAuth `state` via Socialite, récupère l'email confirmé par Google, vérifie son domaine exact `usmba.ac.ma`, puis appelle `UserDirectory::findByEmail`. Par exemple, `hamza.tazi5@usmba.ac.ma` est une adresse académique valide. Seul un compte déjà présent dans votre annuaire peut ouvrir une session Laravel. Son nom et son rôle proviennent de votre base ; les informations du profil Google ne les remplacent pas. Aucun utilisateur n'est créé à la connexion et aucun jeton Google n'est stocké en base ou en session.

Le callback dirige vers `/`, qui choisit `/etudiant` ou `/gerant` selon le rôle de l'annuaire. Chaque interface contrôle également le rôle côté serveur. L'espace étudiant propose le [catalogue en consultation seule](student-catalogue.md) ; l'espace gérant propose le [suivi des retards](manager-dashboard.md).

## Configuration dans Google Cloud

1. Ouvrir la [console Google Cloud](https://console.cloud.google.com/) avec le compte qui gère le projet de l'école, puis créer ou sélectionner le projet concerné.
2. Dans **Google Auth Platform**, configurer la présentation de l'application et les informations de contact demandées par Google.
3. Configurer l'audience. Utiliser une audience interne seulement si le projet et les comptes de l'école appartiennent à l'organisation Google Workspace concernée. Sinon, configurer une audience externe et ajouter les comptes de test nécessaires pendant la phase de test.
4. Dans les clients OAuth, créer un client de type **Application Web**.
5. Enregistrer exactement cette URI de redirection pour un lancement local sur le port 8000 :

   ```text
   http://localhost:8000/auth/google/callback
   ```

6. Copier l'identifiant client et le secret dans votre fichier `.env` local. Ne pas les inscrire dans un fichier suivi avec le code ou les transmettre dans le chat.

Ce flux OAuth côté serveur n'utilise pas de widget Google JavaScript ; l'URI de redirection autorisée est le réglage à renseigner pour ce parcours. Il n'est pas nécessaire d'activer une API Gmail, Contacts ou Drive.

## Configuration Laravel

Les migrations de l'étape 1 doivent avoir été exécutées sur une nouvelle base MySQL et les comptes académiques autorisés doivent être présents dans `users`. Voir le [dictionnaire de données](database.md).

Avant votre import réel, vous pouvez charger les [données temporaires](demo-data.md), qui incluent votre adresse `hamza.tazi5@usmba.ac.ma` avec le rôle étudiant et des comptes fictifs pour les autres scénarios.

Configurer `.env` :

```dotenv
APP_URL=http://localhost:8000
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr

GOOGLE_CLIENT_ID=votre_identifiant.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=votre_secret_client
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

SESSION_DRIVER=database
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=false
```

Sur un nouveau poste :

```powershell
composer install
npm ci
npm run build
php artisan config:clear
php artisan serve --host=localhost --port=8000
```

Visiter `http://localhost:8000`. Utiliser le même nom d'hôte partout : `localhost` et `127.0.0.1` ne partagent pas les mêmes cookies. Le protocole, l'hôte, le port et le chemin de l'URI doivent correspondre au client Google. Pour recompiler automatiquement Tailwind pendant le développement, lancer `npm run dev` dans un second terminal à la place du build ponctuel.

Pour un hébergement réel, configurer `APP_URL` et `GOOGLE_REDIRECT_URI` avec votre domaine HTTPS, enregistrer cette URI exacte dans Google, activer `SESSION_SECURE_COOKIE=true`, utiliser `APP_DEBUG=false` et garder le document root du serveur sur `public/`. Ne pas activer le cookie sécurisé pour un essai local en HTTP.

## Authentification indépendante du stockage

`config/auth.php` utilise le fournisseur `directory`. `DirectoryUserProvider::retrieveById` recharge l'identité via `UserDirectory` à chaque nouvelle requête. `DirectoryUser` implémente le contrat Laravel `Authenticatable` sans dépendre d'Eloquent. Seul l'identifiant local est conservé dans la session ; le rôle est relu dans l'annuaire.

Une suppression d'utilisateur, un changement de rôle ou le remplacement de son email par une adresse hors de `usmba.ac.ma` prend effet à la requête suivante. Une identité absente ou extérieure au domaine doit repasser par Google ; une identité qui n'a plus le rôle gérant reçoit un refus sur `/gerant`. Les pages protégées utilisent `Cache-Control: no-store, private`.

Le fournisseur refuse l'authentification par mot de passe et par jeton persistant. Remplacer la liaison de `UserDirectory` permettra de brancher l'API prévue sans réécrire le callback ou la session, sous réserve de conserver les identifiants locaux stables décrits dans l'architecture.

## Réponses prévues

| Situation | Résultat |
| --- | --- |
| Email Google confirmé et inscrit comme étudiant | Session puis `/etudiant` |
| Email Google confirmé et inscrit comme gérant | Session puis `/gerant` |
| Email absent de l'annuaire | HTTP 403 avec message explicite |
| Email absent, invalide ou non confirmé par Google | HTTP 403 |
| Email hors du domaine exact `usmba.ac.ma`, même inscrit en base | HTTP 403 |
| Étudiant sur une route gérant, ou inversement | HTTP 403 |
| Connexion Google refusée ou annulée | HTTP 403, sans boucle automatique |
| `state` absent, expiré ou différent | HTTP 400 ; recommencer depuis l'application |
| Configuration OAuth incomplète | HTTP 503 avec message de configuration manquante |
| Erreur HTTP ou réseau lors de l'échange Google | HTTP 503 avec message d'indisponibilité |

En cas de `redirect_uri_mismatch`, comparer la valeur exacte de `GOOGLE_REDIRECT_URI` à l'URI enregistrée dans Google, puis exécuter `php artisan config:clear`. En cas de connexion expirée, recommencer depuis `/` dans un seul onglet en conservant les cookies de session.

## Vérification

```powershell
php vendor/phpunit/phpunit/phpunit
npm run build
```

Les tests utilisent le vrai fournisseur Google de Socialite avec un client HTTP simulé, et une base SQLite en mémoire. Ils couvrent les états OAuth, les deux rôles, les accès refusés, la rotation de session, les changements dans l'annuaire et l'absence de création automatique d'utilisateurs. Aucun compte Google réel n'est sollicité par ces tests.

La suite couvre le domaine académique, le jeu de démonstration, le catalogue étudiant, le dashboard et les emails. Les résultats actuels sont indiqués dans la [documentation du dashboard](manager-dashboard.md). Le frontend est compilé dans `public/build` ; ce dossier ignoré par Git doit être généré à chaque installation depuis les sources.

L'essai de bout en bout avec Google doit être réalisé après configuration de vos identifiants OAuth, avec des comptes académiques déjà présents dans votre nouvelle base MySQL.

## Références

- [Laravel 12 — Socialite](https://laravel.com/docs/12.x/socialite)
- [Laravel 12 — Fournisseurs d'authentification](https://laravel.com/docs/12.x/authentication#adding-custom-user-providers)
- [Google — Configuration OAuth d'une application serveur](https://developers.google.com/identity/protocols/oauth2/web-server#creatingcred)
- [Google — OpenID Connect et email confirmé](https://developers.google.com/identity/openid-connect/openid-connect)
