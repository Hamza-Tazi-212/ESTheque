# Lancement local avec XAMPP

## État du poste

Le module nommé « MySQL » dans XAMPP fournit MariaDB 10.4.32 sur `127.0.0.1:3306`. Nous utilisons ce serveur existant pour les essais locaux avec le pilote `mysql` de Laravel. Aucune deuxième installation de serveur n'est nécessaire pour ces essais. La cible de livraison reste Laravel 12 / MySQL ; la validation sur MySQL 8 demeure distincte des essais locaux MariaDB.

La base `est_fes_library_demo` est prête : sept migrations appliquées, quatre étudiants, un gérant, quatre ouvrages, douze exemplaires et six prêts. Les scénarios et les identifiants QR figurent dans le [jeu de démonstration](demo-data.md). Le fichier `.env` sélectionne cette base. Sa version précédente est conservée dans `.env.backup`, et l'ancienne base SQLite reste intacte. Ne pas relancer le seeder : il refuserait la base déjà remplie.

Laisser le module MySQL de XAMPP démarré. Le serveur PHP Laravel sera lancé sur le port 8000 ; il n'utilise pas le port Apache 8080 visible dans XAMPP.

## Créer le client Google

1. Ouvrir la [console Google Cloud](https://console.cloud.google.com/), créer ou sélectionner le projet de test « Bibliothèque EST Fès ».
2. Ouvrir **Google Auth Platform → Branding** (Présentation de la marque), puis **Get started / Commencer** si nécessaire. Renseigner le nom de l'application, l'email d'assistance et l'email de contact.
3. Dans **Audience**, utiliser **Externe** si le projet n'appartient pas à l'organisation Google Workspace de l'école. En mode test, ajouter `hamza.tazi5@usmba.ac.ma` dans les utilisateurs de test. Une audience interne est possible si le projet appartient à cette organisation et que le compte académique en fait partie.
4. Dans **Data Access / Accès aux données**, garder uniquement les accès d'identification `openid`, `email` et `profile`. L'application demande déjà ces scopes via Socialite. [Instructions Google sur le consentement et l'audience](https://developers.google.com/workspace/guides/configure-oauth-consent).
5. Dans **Clients → Créer un client**, sélectionner **Application Web** et donner un nom au client, par exemple « Bibliothèque locale ».
6. Ajouter cette **URI de redirection autorisée** exacte, puis créer le client :

   ```text
   http://localhost:8000/auth/google/callback
   ```

7. Copier l'identifiant client et le secret dans `.env` sur ce poste. Le flux côté serveur accepte une URI locale ; sa valeur doit correspondre exactement à celle enregistrée dans le client Google. [Instructions Google pour les clients Web](https://developers.google.com/identity/protocols/oauth2/web-server#creatingcred).

Les noms des menus peuvent être affichés en français ou en anglais. Le secret doit rester dans le fichier local, sans être envoyé dans le chat.

## Renseigner le fichier local

La connexion à la base est déjà configurée. Seules les deux valeurs Google ci-dessous restent à remplacer dans [.env](../.env) :

```dotenv
APP_URL=http://localhost:8000
GOOGLE_CLIENT_ID=votre_identifiant.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=votre_secret_client
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

Conserver `MAIL_MAILER=log` pendant les essais avec les adresses fictives. Aucun email réel n'est alors envoyé.

## Démarrer

Dans PowerShell, depuis le dossier du projet :

```powershell
php artisan config:clear
php artisan serve --host=localhost --port=8000
```

Ouvrir `http://localhost:8000` et choisir le compte `hamza.tazi5@usmba.ac.ma`. Ce compte est enregistré comme **étudiant** dans la démonstration. Une connexion réussie doit ouvrir `/etudiant` avec les quatre ouvrages. Google peut demander son consentement ou un choix de compte.

Un accès à l'espace gérant nécessite un vrai compte académique inscrit avec le rôle `manager`. Le compte fictif `nabil.amrani1@usmba.ac.ma` ne constitue pas un compte Google utilisable. Le [dictionnaire de données](database.md) décrit les colonnes à renseigner directement en base.

## Si la connexion échoue

| Message | Vérification |
| --- | --- |
| Configuration Google manquante | Remplir les deux valeurs Google dans `.env`, puis exécuter `php artisan config:clear` |
| `redirect_uri_mismatch` | Comparer l'URI complète : `http://localhost:8000/auth/google/callback` |
| Accès bloqué par Google | Vérifier l'audience, le compte de test et les restrictions de l'organisation scolaire |
| Accès refusé par l'application | Utiliser un compte Google vérifié du domaine `usmba.ac.ma`, présent dans `users` |
| Connexion base refusée | Vérifier que le module MySQL XAMPP est démarré sur le port 3306 |

Le [guide OAuth détaillé](google-oauth.md) décrit la configuration HTTPS. Les essais de caméra sur téléphone nécessitent une URL HTTPS accessible depuis le téléphone ; le `localhost` du téléphone ne désigne pas ce PC.
