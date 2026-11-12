# Dictionnaire de données — EST Fès

## Conventions

MySQL 8.0.16+, moteur InnoDB, jeu de caractères `utf8mb4`. Les `id` sont des `BIGINT UNSIGNED` auto-incrémentés. Les dates sont stockées en UTC. `created_at` et `updated_at` sont des `TIMESTAMP` nullables gérés par Eloquent ; lors d'un import direct, renseigner ces deux dates avec `UTC_TIMESTAMP()`.

Les tables `users`, `books` et `copies` sont alimentées directement en base par vos soins. Aucune interface ni route de création, modification ou suppression de ces données de référence n'est prévue. `loans` est alimentée par le service de scan. La table technique `scan_operations` assure la reprise des requêtes de circulation et n'est pas à importer avec vos données.

## Relations

| Parent | Enfant | Relation |
| --- | --- | --- |
| `users.id` | `loans.student_id` | Un étudiant possède plusieurs emprunts, dont trois actifs au maximum |
| `books.id` | `copies.book_id` | Un ouvrage possède plusieurs exemplaires |
| `copies.id` | `loans.copy_id` | Plusieurs emprunts successifs, un seul actif |

Les clés étrangères métier utilisent `RESTRICT` à la suppression pour protéger les lignes référencées lors d'un import externe.

## `users` — étudiants et gérants

Une table unique garantit qu'un email correspond à un seul rôle. Il existe exactement deux valeurs de rôle, sans valeur par défaut : l'import doit choisir explicitement le rôle.

| Colonne | Type MySQL | Null | Description |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED, PK | Non | Identifiant local stable |
| `name` | VARCHAR(255) | Non | Nom complet |
| `email` | VARCHAR(255), UNIQUE | Non | Email académique `@usmba.ac.ma`, en minuscules, sans espaces périphériques |
| `role` | ENUM('student', 'manager') | Non | Étudiant ou gérant |
| `student_qr_code` | VARCHAR(191), UNIQUE | Oui | Carte de l'étudiant ; `NULL` pour les gérants |
| `created_at`, `updated_at` | TIMESTAMP | Oui | Dates techniques |

La nullabilité du QR permet les gérants sans carte. Votre import doit renseigner un QR pour chaque étudiant et `NULL` pour les gérants : le schéma ne contient pas de contrainte conditionnelle sur ces colonnes. Aucun mot de passe, jeton Google, profil modifiable ou rôle supplémentaire n'est stocké.

Le domaine académique est exactement `usmba.ac.ma`, par exemple `hamza.tazi5@usmba.ac.ma`. Il est défini une fois dans `config/library.php`. Le callback Google et le fournisseur de session vérifient ce domaine : un email extérieur reste refusé même s'il a été inséré en base. Les emails du bon domaine doivent aussi être présents dans l'annuaire. Aucun format supplémentaire n'est imposé à la partie avant `@`.

## `books` — ouvrages

| Colonne | Type MySQL | Null | Description |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED, PK | Non | Identifiant d'ouvrage |
| `title` | VARCHAR(255) | Non | Titre |
| `author` | VARCHAR(255) | Non | Auteur affiché |
| `summary` | TEXT | Non | Petit résumé, texte brut |
| `table_of_contents` | TEXT | Non | Petit sommaire, texte brut avec retours à la ligne |
| `cover_image` | VARCHAR(2048) | Non | URL HTTPS ou chemin public de la couverture/page de garde |
| `created_at`, `updated_at` | TIMESTAMP | Oui | Dates techniques |

Les images sont fournies par votre alimentation des données, sans interface de téléversement. Le résumé et le sommaire seront affichés comme texte échappé dans Blade.

## `copies` — exemplaires physiques

| Colonne | Type MySQL | Null | Description |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED, PK | Non | Identifiant d'exemplaire |
| `book_id` | BIGINT UNSIGNED, FK → books.id | Non | Ouvrage associé |
| `qr_code` | VARCHAR(191), UNIQUE | Non | Identifiant encodé sur l'exemplaire |
| `status` | ENUM('available', 'borrowed') | Non | Disponible ou emprunté ; défaut `available` |
| `created_at`, `updated_at` | TIMESTAMP | Oui | Dates techniques |

Index composé `(book_id, status)` pour la disponibilité. Aucun autre statut n'est introduit.

Importer un exemplaire neuf avec `status = 'available'`. Si vous alimentez des prêts existants, leurs statuts et les lignes `loans` doivent être insérés dans une transaction cohérente. Le service de circulation maintiendra ensuite le statut et le prêt actif ensemble ; le schéma seul ne synchronise pas les deux tables.

## `loans` — emprunts, retours et annulations

| Colonne | Type MySQL | Null | Description |
| --- | --- | --- | --- |
| `id` | BIGINT UNSIGNED, PK | Non | Identifiant d'emprunt |
| `student_id` | BIGINT UNSIGNED, FK → users.id | Non | Étudiant emprunteur |
| `copy_id` | BIGINT UNSIGNED, FK → copies.id | Non | Exemplaire prêté |
| `borrowed_at` | DATETIME | Non | Date et heure du prêt |
| `due_at` | DATETIME | Non | Échéance calculée depuis la durée configurée |
| `returned_at` | DATETIME | Oui | Retour ; `NULL` tant que non retourné |
| `cancelled_at` | DATETIME | Oui | Annulation d'un prêt erroné ; `NULL` sinon |
| `active_copy_id` | BIGINT UNSIGNED, STORED, UNIQUE | Oui | `copy_id` si actif, sinon `NULL` ; calculée, ne jamais fournir à l'insertion |
| `created_at`, `updated_at` | TIMESTAMP | Oui | Dates techniques |

Un prêt est actif quand `returned_at IS NULL AND cancelled_at IS NULL`. Retour et annulation sont exclusifs dans le service métier. La valeur générée est :

```sql
CASE
    WHEN returned_at IS NULL AND cancelled_at IS NULL THEN copy_id
    ELSE NULL
END
```

L'index unique interdit deux prêts actifs d'un même exemplaire et accepte plusieurs lignes passées avec une clé `NULL`. Les index `(student_id, returned_at, cancelled_at)` et `(returned_at, cancelled_at, due_at)` servent au comptage des prêts actifs et à la recherche des retards.

La clé étrangère vérifie l'existence de l'utilisateur, pas son rôle. Le contrôle du rôle étudiant, la limite de trois prêts et les dates sont assurés par `CirculationService`, avec transactions et lectures verrouillées. Les opérations sont réservées aux routes QR du gérant.

## `scan_operations` — reprise technique des requêtes de circulation

Cette table ne crée pas de page d'historique ni d'interface supplémentaire. Elle évite qu'une même requête renvoyée par le navigateur inverse l'opération déjà enregistrée. Elle est écrite dans la même transaction que l'emprunt, le retour ou l'annulation.

| Colonne | Type MySQL | Null | Description |
| --- | --- | --- | --- |
| `id` | CHAR(36), PK | Non | UUID de la demande, généré par le navigateur |
| `manager_id` | BIGINT UNSIGNED, FK → users.id | Non | Gérant à l'origine de la demande ; suppression RESTRICT |
| `fingerprint` | CHAR(64) | Non | Empreinte SHA-256 du contenu et du type d'opération |
| `http_status` | SMALLINT UNSIGNED | Oui | Code HTTP de la réponse enregistrée |
| `response` | JSON | Oui | Résultat ou refus métier à restituer lors d'une reprise |
| `created_at`, `updated_at` | TIMESTAMP | Oui | Dates techniques |

Le statut et la réponse sont remplis avant le commit ; une erreur technique provoque le rollback, y compris de la ligne de demande. L'identifiant unique et le verrou sur la ligne sérialisent les reprises de la même demande. Une clé réutilisée pour un autre contenu ou un autre gérant est refusée. Aucune purge automatique n'est ajoutée. Lors d'une maintenance manuelle des utilisateurs, cette relation doit être prise en compte, comme les relations des prêts.

## Identifiants QR

Les QR contiennent des identifiants opaques, pas les noms ou emails. Les valeurs sont uniques dans leur table. Pour éviter une reconnaissance ambiguë, utiliser des espaces distincts, par exemple `student:EST-0001` pour une carte et `copy:EX-0001` pour un exemplaire. Les valeurs des deux tables ne doivent pas se chevaucher. Le préfixe n'est pas imposé par SQL ; aucun QR n'est généré par l'application.

## Exemples d'insertion directe

Exemples documentaires avec des adresses inventées, à remplacer par vos données réelles. La présence du domaine académique ne crée pas de compte Google. Un [jeu de démonstration complet](demo-data.md) est également disponible sur demande explicite via son seeder.

```sql
START TRANSACTION;

INSERT INTO users (name, email, role, student_qr_code, created_at, updated_at)
VALUES ('Étudiant exemple', 'etudiant.exemple1@usmba.ac.ma', 'student', 'student:EST-0001', UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO users (name, email, role, student_qr_code, created_at, updated_at)
VALUES ('Gérant exemple', 'gerant.exemple1@usmba.ac.ma', 'manager', NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO books (title, author, summary, table_of_contents, cover_image, created_at, updated_at)
VALUES ('Algorithmique', 'Auteur exemple', 'Introduction aux algorithmes.', '1. Bases\n2. Structures de données', '/covers/algorithmique.jpg', UTC_TIMESTAMP(), UTC_TIMESTAMP());

SET @book_id = LAST_INSERT_ID();

INSERT INTO copies (book_id, qr_code, status, created_at, updated_at)
VALUES (@book_id, 'copy:EX-0001', 'available', UTC_TIMESTAMP(), UTC_TIMESTAMP());

COMMIT;
```

## Tables techniques Laravel

Ces tables proviennent du socle et servent aux sessions, au cache et à la file d'emails.

| Table | Colonnes et utilisation |
| --- | --- |
| `migrations` | `id` INT PK, `migration` VARCHAR, `batch` INT ; migrations appliquées |
| `sessions` | `id` VARCHAR PK, `user_id` BIGINT nullable indexé, `ip_address` VARCHAR(45) nullable, `user_agent` TEXT nullable, `payload` LONGTEXT, `last_activity` INT indexé ; session. Pas de clé étrangère sur `user_id`, conformément au socle |
| `cache` | `key` VARCHAR PK, `value` MEDIUMTEXT, `expiration` INT ; cache |
| `cache_locks` | `key` VARCHAR PK, `owner` VARCHAR, `expiration` INT ; verrous |
| `jobs` | `id` BIGINT PK, `queue` VARCHAR indexé, `payload` LONGTEXT, `attempts` TINYINT UNSIGNED, `reserved_at` INT UNSIGNED nullable, `available_at` et `created_at` INT UNSIGNED ; tâches |
| `job_batches` | `id` VARCHAR PK, `name` VARCHAR, `total_jobs`, `pending_jobs`, `failed_jobs` INT, `failed_job_ids` LONGTEXT, `options` MEDIUMTEXT nullable, `cancelled_at` et `finished_at` INT nullable, `created_at` INT ; lots standard du socle |
| `failed_jobs` | `id` BIGINT PK, `uuid` VARCHAR UNIQUE, `connection` et `queue` TEXT, `payload` et `exception` LONGTEXT, `failed_at` TIMESTAMP avec défaut courant ; échecs |

Sauf mention contraire, les colonnes techniques sont non nullables et les VARCHAR ont une longueur de 255. Ne pas alimenter ces tables depuis votre import métier.
