# Catalogue étudiant — étape 3

## Parcours

Après une connexion Google autorisée, l'étudiant arrive sur `/etudiant`. Le catalogue affiche les ouvrages par titre, sous forme de lignes compactes : titre, auteur et badge de disponibilité. Un ouvrage sans exemplaire disponible reste visible avec la mention **Indisponible**, conformément à votre choix.

Cliquer sur une ligne, ou l'activer au clavier, ouvre son aperçu : court extrait du résumé, nombre d'exemplaires disponibles et lien **Plus de détails**. Les éléments HTML natifs `details` et `summary` fournissent cette expansion sans bibliothèque JavaScript supplémentaire. Toutes les lignes sont repliées à l'ouverture du catalogue.

La fiche `/etudiant/ouvrages/{id}` présente le titre, l'auteur, la couverture, la disponibilité, le résumé et le sommaire. Les retours à la ligne du texte importé sont conservés. Si la couverture est absente ou ne peut pas être chargée, un emplacement « Couverture indisponible » reste affiché.

La liste et la fiche rappellent que l'étudiant doit se présenter à la bibliothèque pour emprunter. Aucune réservation, saisie d'emprunt, gestion de profil ou gestion de données de référence n'est proposée.

## Recherche

Le formulaire `GET /etudiant?q=...` recherche une expression dans le titre ou l'auteur. Les espaces périphériques sont retirés ; une recherche vide affiche le catalogue. Les caractères `%`, `_` et `!` sont recherchés littéralement et ne peuvent pas élargir la recherche en tant que jokers SQL. Les valeurs sont transmises par paramètres SQL liés.

La saisie est limitée à 255 caractères et doit être une chaîne. Une saisie invalide revient au catalogue avec un message en français. Un résultat vide affiche « Aucun ouvrage trouvé » ; une base sans ouvrages affiche un message distinct. Le lien « Effacer la recherche » revient à la liste complète.

Le terme de recherche est conservé dans le lien vers une fiche, puis dans le lien de retour au catalogue. Aucun filtre ou choix de tri supplémentaire n'est ajouté.

## Disponibilité

Le scope `Book::withAvailability()` calcule deux nombres depuis les exemplaires : le total et ceux dont `status = available`. Les lignes de la liste sont lues avec leurs compteurs dans une seule requête de catalogue, sans requête par ouvrage. Une fiche recharge également les compteurs.

- Au moins un exemplaire disponible : badge **Disponible**.
- Aucun exemplaire disponible, y compris un ouvrage sans exemplaires : badge **Indisponible**.

Il n'y a pas de cache de disponibilité. Une modification du statut en base est visible à la consultation suivante. Le traitement transactionnel des retours et emprunts reste prévu à l'étape 5 ; aucune actualisation périodique ni service supplémentaire n'est ajouté.

## Accès et données importées

Les deux routes sont protégées par `auth` et `role:student`. Un visiteur est redirigé vers Google ; un gérant ne peut pas accéder aux interfaces réservées aux étudiants. Les actions d'écriture sur les URL de catalogue ne sont pas routées. Un identifiant d'ouvrage inexistant renvoie HTTP 404.

Les champs importés et le terme recherché sont échappés par Blade. Pour une couverture, fournir une URL HTTPS ou un chemin public commençant par `/`, par exemple `/demo/cover.svg`. Les autres protocoles, les URL relatives au protocole et les chemins avec des antislashs ne sont pas utilisés comme sources d'image.

## Fichiers

| Fichier | Responsabilité |
| --- | --- |
| `app/Http/Controllers/Student/CatalogueController.php` | Liste, recherche et fiche |
| `app/Http/Requests/CatalogueSearchRequest.php` | Validation de la recherche |
| `app/Models/Book.php` | Compteurs de disponibilité et source de couverture |
| `resources/views/student/index.blade.php` | Catalogue responsive et lignes extensibles |
| `resources/views/student/show.blade.php` | Fiche de l'ouvrage |
| `resources/views/components/book-availability.blade.php` | Badge partagé entre la liste et la fiche |
| `resources/js/app.js` | Emplacement de remplacement en cas d'échec d'image |
| `tests/Feature/StudentCatalogueTest.php` | Recherche, disponibilité, HTML, droits et consultation sans écriture |

## Essai local

Configurer la base MySQL et [Google OAuth](google-oauth.md). Pour utiliser les ouvrages temporaires avec votre compte étudiant `hamza.tazi5@usmba.ac.ma`, suivre les [instructions du jeu de démonstration](demo-data.md) sur une base MySQL de test vide. L'ancienne base SQLite du socle n'est pas modifiée automatiquement.

Depuis le projet :

```powershell
npm run build
php artisan serve --host=localhost --port=8000
```

Ouvrir `http://localhost:8000`, se connecter avec le compte académique enregistré, rechercher un ouvrage, ouvrir son aperçu puis sa fiche. Le formulaire se place verticalement sur mobile et horizontalement à partir des tailles intermédiaires ; la fiche passe d'une colonne à deux colonnes sur ordinateur.

## Vérification de la livraison

```powershell
php vendor/phpunit/phpunit/phpunit
npm run build
```

Résultat : **74 tests réussis, 303 assertions**, compilation Vite réussie. Les tests examinent les réponses HTTP et le HTML rendu sur SQLite en mémoire, avec les réponses Google simulées dans la suite d'authentification. Ils vérifient aussi que les consultations ne modifient ni les utilisateurs, ni les ouvrages, ni les exemplaires, ni les emprunts.

Le rendu n'a pas été inspecté dans un navigateur automatisé, aucun outil de contrôle de navigateur n'étant disponible dans cette session. La connexion réelle Google et l'exécution sur MySQL restent à vérifier avec vos identifiants et votre base configurés.

Les compteurs utilisent les [agrégats de relations Eloquent de Laravel 12](https://laravel.com/docs/12.x/eloquent-relationships#counting-related-models), et la recherche utilise une [Form Request Laravel](https://laravel.com/docs/12.x/validation#form-request-validation). Aucune dépendance n'est ajoutée.
