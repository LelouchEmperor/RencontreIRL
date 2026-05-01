# AGENTS.md — RencontreIRL

## Projet

RencontreIRL est une application web PHP de rencontre sociale autour de sorties/événements.

Objectif du projet :
- permettre aux utilisateurs de créer un compte ;
- compléter leur profil ;
- créer ou rejoindre des sorties ;
- échanger par messages privés ;
- recevoir des notifications ;
- signaler des utilisateurs ou contenus ;
- gérer certains signalements côté admin.

Le projet est développé en solo. Les explications et commentaires doivent être en français.

---

## Stack technique

- PHP 8.2
- MySQL/MariaDB via phpMyAdmin
- Serveur local : XAMPP
- Dépendances Composer
- PHPMailer utilisé pour l’envoi d’emails
- Frontend simple : HTML, CSS, JavaScript vanilla
- Pas de framework PHP pour le moment

---

## Structure du projet

```txt
RencontreIRL/
├── AGENTS.md
├── composer.json
├── composer.lock
├── vendor/
├── app/
│   ├── pages/
│   ├── actions/
│   ├── auth/
│   ├── api/
│   ├── admin/
│   └── services/
├── config/
├── includes/
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── fonts/
│   └── uploads/
└── storage/
    └── logs/
Rôle des dossiers
public/ : fichiers accessibles publiquement depuis le navigateur.
public/assets/ : CSS, JS, polices, images statiques.
public/uploads/ : fichiers uploadés par les utilisateurs.
app/pages/ : pages visibles par l’utilisateur.
app/actions/ : scripts de traitement POST ou actions métier.
app/auth/ : connexion, inscription, déconnexion, vérification email.
app/api/ : endpoints utilisés par JavaScript/AJAX.
app/admin/ : pages ou actions réservées aux administrateurs.
app/services/ : logique métier réutilisable.
config/ : configuration, connexion BDD, CSRF, mailer, upload, geocode.
includes/ : composants PHP partagés comme header/footer.
storage/logs/ : logs applicatifs.
Base de données

Tables actuelles :

users
sorties
participations
likes_sorties
messages
notifications
photos_profil
prompts
reponses_prompts
reports
Tables connues
users

Colonnes visibles :

id
prenom
email
email_verifie
token_verification
mot_de_passe
google_id
ville
date_naissance
code_postal
bio
photo
created_at
latitude
longitude
is_admin
account_status

Règles :

utiliser password_hash() pour créer les mots de passe ;
utiliser password_verify() pour vérifier les mots de passe ;
ne jamais stocker de mot de passe en clair ;
vérifier account_status avant d’autoriser certaines actions ;
vérifier is_admin avant l’accès admin.
sorties

Colonnes visibles :

id
user_id
titre
activite
ville
adresse
description
date_sortie
places_total
places_restantes
created_at
latitude
longitude

Règles :

user_id représente le créateur de la sortie ;
ne jamais laisser un utilisateur modifier/supprimer une sortie qui ne lui appartient pas, sauf admin ;
maintenir la cohérence entre places_total, places_restantes et participations.
participations

Colonnes visibles :

id
sortie_id
user_id
created_at

Règles :

empêcher les doublons (sortie_id, user_id) ;
vérifier les places disponibles avant inscription ;
décrémenter/incrémenter places_restantes de manière cohérente.
likes_sorties

Colonnes visibles :

id
user_id
sortie_id
created_at

Règles :

empêcher les doublons (user_id, sortie_id).
messages

Colonnes visibles :

id
sortie_id
expediteur_id
destinataire_id
contenu
lu
created_at

Règles :

un utilisateur ne peut lire que ses conversations ;
échapper le contenu à l’affichage avec htmlspecialchars() ;
utiliser des requêtes préparées.
notifications

Colonnes visibles :

id
user_id
type
message
lu
lien
created_at

Règles :

une notification appartient à un utilisateur ;
un utilisateur ne peut marquer comme lue que ses propres notifications.
photos_profil

Colonnes visibles :

id
user_id
nom_fichier
ordre
created_at

Règles :

valider strictement les uploads ;
autoriser seulement JPG, PNG, WebP ;
renommer les fichiers uploadés ;
ne jamais faire confiance au nom original du fichier.
prompts

Colonnes visibles :

id
question

Rôle :

questions de profil utilisées pour enrichir les profils utilisateurs.
reponses_prompts

Colonnes visibles :

id
user_id
prompt_id
reponse
created_at

Règles :

une réponse appartient à un utilisateur ;
échapper les réponses à l’affichage.
reports

Colonnes visibles :

id
reporter_id
target_type
target_id
reason
details
status
created_at
reviewed_at
reviewed_by

Règles :

reporter_id est l’utilisateur qui signale ;
target_type peut représenter user, sortie, message, etc. ;
seuls les admins peuvent modifier status, reviewed_at, reviewed_by.
Conventions de code
PHP procédural accepté pour l’instant.
Éviter d’introduire un framework sans demande explicite.
Préférer une architecture simple mais propre.
Utiliser PDO pour la base de données.
Utiliser des requêtes préparées pour toutes les entrées utilisateur.
Utiliser declare(strict_types=1); dans les nouveaux fichiers PHP si possible.
Garder des noms de fichiers clairs en kebab-case.
Garder les variables en français ou cohérentes avec le code existant.
Commentaires en français.
Ne pas sur-commenter le code évident.
Séparer affichage, action et logique métier dès que possible.
Ne pas mélanger HTML massif et logique SQL complexe dans un même fichier si évitable.
Sécurité obligatoire

Ce projet manipule des profils, messages, photos et signalements. La sécurité est prioritaire.

SQL
Toujours utiliser PDO avec requêtes préparées.
Ne jamais concaténer une entrée utilisateur dans une requête SQL.
Valider et caster les IDs avec (int).

Références : OWASP recommande les requêtes paramétrées contre l’injection SQL, et PHP recommande PDO::prepare() pour lier les entrées utilisateur.

Authentification
Utiliser password_hash() pour créer un hash.
Utiliser password_verify() pour vérifier un mot de passe.
Ne jamais comparer un mot de passe manuellement.
Ne jamais stocker un mot de passe en clair.
Régénérer l’ID de session après connexion avec session_regenerate_id(true).
Sessions
Vérifier l’utilisateur connecté avant chaque action protégée.
Ne jamais faire confiance à un user_id reçu en POST/GET pour identifier l’utilisateur courant.
Utiliser l’ID stocké en session.
Protéger les pages admin avec is_admin.
CSRF
Tous les formulaires POST sensibles doivent utiliser un token CSRF.
Cela concerne notamment :
connexion ;
inscription ;
modification profil ;
création/modification/suppression de sortie ;
participation/quitter une sortie ;
upload photo ;
signalement ;
suppression ;
actions admin.
XSS
Toujours échapper les données affichées avec htmlspecialchars($value, ENT_QUOTES, 'UTF-8').
Ne jamais afficher directement :
prénom ;
bio ;
messages ;
descriptions de sorties ;
réponses aux prompts ;
détails de signalement.
Uploads
Vérifier l’extension et le MIME type.
Limiter la taille des fichiers.
Renommer les fichiers avec un nom généré.
Stocker les uploads dans public/uploads/.
Ne jamais exécuter de fichier uploadé.
Refuser .php, .phtml, .svg sauf validation spécifique.
Emails
Utiliser PHPMailer via Composer.
Ne pas stocker les identifiants SMTP en dur dans le code.
Préférer un fichier local non versionné ou des variables d’environnement.
Ne jamais commiter de secret.
Règles de modification

Avant toute modification importante :

Comprendre le flux existant.
Identifier les fichiers impactés.
Préserver les routes existantes.
Mettre à jour les require, include, liens, redirections et formulaires.
Vérifier les chemins relatifs après déplacement.

Ne jamais modifier :

vendor/
composer.lock, sauf changement réel de dépendance
fichiers sensibles de configuration contenant des secrets
données SQL sans expliquer l’impact

Ne jamais supprimer une fonctionnalité existante sans le signaler.

Chemins et includes

Utiliser __DIR__ pour les chemins PHP.

Exemple :

require_once __DIR__ . '/../../config/db.php';

Éviter les chemins fragiles du type :

require_once '../config/db.php';

sauf si le contexte est parfaitement maîtrisé.

Commandes utiles

Installation des dépendances :

composer install

Serveur local XAMPP :

http://localhost/SITE_RENCONTRE/RencontreIRL/public/

Alternative avec serveur PHP intégré :

php -S localhost:8000 -t public
Style attendu des réponses Codex

Quand une tâche est demandée :

Expliquer brièvement l’approche.
Lister les fichiers modifiés.
Fournir le code complet ou un diff clair.
Signaler les impacts sécurité.
Signaler les tests manuels à faire.

Réponses en français.

Objectif d’évolution

Améliorer progressivement le projet vers une structure plus maintenable, sans tout réécrire d’un coup.

Priorités :

sécuriser l’existant ;
nettoyer les chemins ;
centraliser les fonctions répétées ;
améliorer la structure pages/actions/services ;
renforcer la base de données avec index, clés étrangères et contraintes uniques ;
améliorer l’expérience utilisateur ;
préparer une architecture plus proche MVC si nécessaire.
À éviter
Réécriture complète non demandée.
Introduction d’un framework sans accord.
JavaScript lourd inutile.
Dépendances inutiles.
Code magique difficile à comprendre.
Changement massif de structure sans migration progressive.
Stockage de secrets dans Git.

Les règles sécurité sont basées sur les pratiques OWASP pour les requêtes paramétrées, la gestion des sessions et le stockage des mots de passe, ainsi que sur les fonctions natives PHP `password_hash()`, `password_verify()` et `PDO::prepare()`. PHPMailer est bien adapté ici car il supporte l’envoi SMTP via Composer. :contentReference[oaicite:0]{index=0}
::contentReference[oaicite:1]{index=1}