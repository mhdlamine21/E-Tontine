# E-Tontine - Plateforme Web de Gestion de Tontines

Application web complète de digitalisation et de gestion transparente de tontines rotatives traditionnelles (cotisations, tirage au sort des tours, distribution de cagnotte, demandes d'urgence, votes de gouvernance, exports et notifications).

---

## Contexte Académique

Ce projet a été conçu et réalisé dans le cadre du cours de **Développement Web 2** (Semestre 4 - Licence 2 Informatique, Année universitaire **2024-2025**) à l'**Université Iba Der Thiam de Thiès (UIDT)**.

L'objectif pédagogique était de concevoir de bout en bout une application web dynamique, robuste et sécurisée en **PHP** et **MySQL**, répondant à une problématique réelle d'inclusion financière et d'entraide communautaire.

---

## Auteurs et Équipe de Réalisation

Projet réalisé en équipe de 8 étudiants :

| N°  | Nom complet                                                                                 | Email                                                                 | Rôle dans le projet                                 |
| --- | ------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- | --------------------------------------------------- |
| 1   | [**Mouhamadou Lamine Niang**](mailto:mouhamedlniang@gmail.com)                              | [mouhamedlniang@gmail.com](mailto:mouhamedlniang@gmail.com)           | Chef de projet & Développement Backend / Sécurité   |
| 2   | [**Mouhameth NGUER**](mailto:mouhameth.nguer@univ-thies.sn)                                 | [mouhameth.nguer@univ-thies.sn](mailto:mouhameth.nguer@univ-thies.sn) | Développement Backend & Gestion des Cycles          |
| 3   | [**Dame NIANG**](mailto:dame.niang1@univ-thies.sn)                                         | [dame.niang1@univ-thies.sn](mailto:dame.niang1@univ-thies.sn)         | Gestion des Membres & Algorithme des Tours          |
| 4   | [**Papa Mangone GUEYE**](mailto:pmangone.gueye@univ-thies.sn)                               | [pmangone.gueye@univ-thies.sn](mailto:pmangone.gueye@univ-thies.sn)   | Module Cotisations, Paiements & Amendes             |
| 5   | [**Lorse FALL**](mailto:lorse.fall@univ-thies.sn)                                           | [lorse.fall@univ-thies.sn](mailto:lorse.fall@univ-thies.sn)           | Module Urgences & Système de Pétitions / Votes      |
| 6   | [**Imam Ahmadou Lam DIA**](mailto:ialamine.dia@univ-thies.sn)                               | [ialamine.dia@univ-thies.sn](mailto:ialamine.dia@univ-thies.sn)       | Module Notifications & Intégration PHPMailer        |
| 7   | [**Mamadou SY**](mailto:mamadou.sy7@univ-thies.sn)                                         | [mamadou.sy7@univ-thies.sn](mailto:mamadou.sy7@univ-thies.sn)         | Interface Utilisateur, CSS & Expérience Utilisateur |
| 8   | [**Serigne Modou WADJI**](mailto:smodou.wadji@univ-thies.sn)                               | [smodou.wadji@univ-thies.sn](mailto:smodou.wadji@univ-thies.sn)       | Tests, Validation & Documentation Technique         |

---

## Ce que Nous Avons Appris et Mis en Pratique en PHP

Ce projet nous a permis d'approfondir et de maîtriser de nombreuses notions fondamentales et avancées du langage PHP et du développement web moderne :

1. **Architecture et Modularité** :
   - Structuration claire du projet séparant la configuration (`configuration/`), la logique métier réutilisable (`fonctions/`), les gabarits d'interface (`gabarits/`), et les contrôleurs/vues applicatifs (`pages/`).
   - Gestion propre du routage et des redirections HTTP conditionnelles.

2. **Accès aux Données avec PDO (PHP Data Objects)** :
   - Connexion centralisée via une instance PDO unique (pattern Singleton/statique).
   - Utilisation systématique de requêtes préparées avec liaisons de paramètres (`prepare()`, `execute()`, `bindValue()`) pour une immunité complète contre les injections SQL.
   - Gestion des transactions financières ACID (`beginTransaction()`, `commit()`, `rollBack()`) garantissant la cohérence des opérations de paiement et de distribution de cagnotte.

3. **Sécurité Web Approfondie** :
   - **Hachage des mots de passe** : utilisation des fonctions natives `password_hash()` avec algorithme bcrypt et `password_verify()`.
   - **Protection contre les attaques CSRF** : génération de jetons cryptographiques aléatoires stockés en session et validation systématique sur chaque formulaire POST.
   - **Prévention des failles XSS** : assainissement et échappement systématique de toutes les données affichées via `htmlspecialchars()`.
   - **Contrôle d'accès basé sur les rôles (RBAC)** : vérification stricte des permissions selon le profil (Super Administrateur, Administrateur de tontine, Membre adhérent).
   - **Gestion sécurisée des sessions** : régénération de l'identifiant de session (`session_regenerate_id(true)`) lors de la connexion pour prévenir la fixation de session, paramétrage des cookies de session (`HttpOnly`, `SameSite=Strict`, `Secure`).

4. **Intégration d'Outils et Bibliothèques Modernes** :
   - Gestion des dépendances avec **Composer** et chargement automatique PSR-4 (`autoload.php`).
   - Intégration de **PHPMailer** pour l'envoi d'emails transactionnels (notifications d'événements, confirmation de paiement, réinitialisation de mot de passe) via le protocole SMTP chiffré (STARTTLS / TLS).
   - Configuration par variables d'environnement via fichier `.env` pour ne jamais exposer les identifiants sensibles dans le code source.

5. **Logique Métier et Algorithmique** :
   - Gestion dynamique des cycles de cotisation et calcul automatisé des dates d'échéance selon la fréquence (hebdomadaire, mensuelle, trimestrielle).
   - Algorithmes équitables de tirage au sort de l'ordre de passage des membres pour la distribution de la cagnotte.
   - Calcul des retards, des délais de grâce et application paramétrable des amendes journalières.
   - Système démocratique de pétition et de vote pour la destitution / réélection d'un administrateur de tontine.
   - Journalisation complète des actions d'audit (`logs`) pour la traçabilité.

---

## Configuration Locale et Base de Données

### 1. Prérequis

- **PHP** : 8.1 ou supérieur (testé et validé sous PHP 8.5)
- **Extensions PHP requises** : `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`
- **Serveur MySQL** : 5.7+ ou MySQL 8.x
- **Composer** (optionnel si le dossier `vendor/` est déjà inclus)

> **Important (Extension PDO MySQL)** :  
> Dans votre fichier `php.ini`, assurez-vous que la ligne `extension=pdo_mysql` est bien activée (sans point-virgule `;` devant).

### 2. Identifiants MySQL Locaux

Pour la machine de développement locale, la connexion MySQL utilise les paramètres par défaut :

- **Hôte** : `localhost`
- **Utilisateur** : `root`
- **Mot de passe** : *(vide ou votre mot de passe configuré)*
- **Base de données** : `etontine`

> **Note importante** : Configurez votre mot de passe de base de données dans le fichier `.env` à la racine du projet (copié depuis `.env.example`) :
>
> ```env
> DB_HOST=localhost
> DB_NAME=etontine
> DB_USER=root
> DB_PASSWORD=votre_mot_de_passe
> ```

### 3. Initialisation de la Base de Données

Le schéma complet et les jeux d'essai sont regroupés dans le fichier [creation_tables.sql](file:///c:/Users/DELL/Documents/Apprentissage/Developpement/Web/Dev%20web%202/php/Projet/E-tontine/base_de_donnees/creation_tables.sql).

Pour importer la base de données :

```powershell
# En ligne de commande MySQL :
mysql -u root -p -e "source base_de_donnees/creation_tables.sql"
```

Cette commande crée la base `etontine`, toutes les tables (19 tables relationnelles) ainsi que les utilisateurs de démonstration et les tontines préconfigurées.

---

## Configuration de PHPMailer (Envoi d'Emails)

L'application intègre **PHPMailer** pour l'envoi automatique d'emails :

- Email de bienvenue à l'inscription
- Réinitialisation de mot de passe par jeton temporaire sécurisé
- Notifications de cotisation, retard et disponibilité de cagnotte

### Configuration dans `.env` :

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=votre_adresse@gmail.com
SMTP_PASS=votre_mot_de_passe_application_16_caracteres
SMTP_FROM=votre_adresse@gmail.com
```

### Comment obtenir un Mot de Passe d'Application Google :

1. Connectez-vous à votre compte Google et allez dans **Sécurité**.
2. Activez la **Validation en deux étapes** si ce n'est pas déjà fait.
3. Recherchez **Mots de passe des applications** (App Passwords).
4. Créez un mot de passe pour l'application nommé par exemple `E-Tontine`.
5. Copiez la clé de 16 caractères générée et collez-la dans `SMTP_PASS` dans votre fichier `.env`.

### Tester l'envoi d'email :

Un script de test dédié en ligne de commande est mis à disposition :

```powershell
php test_mail.php destinataire@example.com
```

_(Si `SMTP_USER` ou `SMTP_PASS` n'est pas renseigné, l'application fonctionne normalement en local sans bloquer les paiements ni les inscriptions)._

---

## Démarrage de l'Application

### Méthode 1 : Serveur de développement PHP intégré (recommandé en local)

Depuis la racine du projet :

```powershell
php -S 127.0.0.1:8008 -t .
```

Ouvrez ensuite votre navigateur sur : **[http://localhost:8008](http://localhost:8008)**

### Méthode 2 : Déploiement Apache / XAMPP

1. Copiez le dossier du projet dans le répertoire web d'Apache :
   - Sous Windows XAMPP : `C:\xampp\htdocs\etontine\`
2. Assurez-vous que le fichier `.env` définit :
   ```env
   APP_URL=http://localhost/etontine
   ```
3. Ouvrez votre navigateur sur : **[http://localhost/etontine](http://localhost/etontine)**

---

## Comptes de Démonstration Préconfigurés

| Profil                     | Email                       | Mot de passe | Description                                                                       |
| -------------------------- | --------------------------- | ------------ | --------------------------------------------------------------------------------- |
| **Super Administrateur**   | `mouhamedlniang@gmail.com`  | `admin123`   | Gestion globale, supervision de toutes les tontines, utilisateurs et logs d'audit |
| **Administrateur Tontine** | `dame.niang@gmail.com`      | `tontine123` | Créateur et gestionnaire de tontines, validation des cycles et cagnottes          |
| **Membre Adhérent**        | `lorse.fall@gmail.com`      | `tontine123` | Membre cotisant, participation aux tours et aux votes                             |
| **Membre Adhérent**        | `mouhameth.nguer@gmail.com` | `tontine123` | Membre avec historique de paiements                                               |

---

## Fonctionnalités Clés de la Plateforme

- **Authentification & Profils** : inscription, connexion sécurisée avec protection anti-bruteforce, récupération de mot de passe par email.
- **Gestion des Tontines** : création avec règles personnalisées (montant, périodicité, jour d'échéance, nombre limite de participants, gestion des amendes).
- **Démarrage et Ordre des Tours** : tirage au sort automatique et équitable de l'ordre de passage une fois le quota de membres atteint.
- **Cotisations et Cagnottes** : paiement simulé (Wave, Orange Money, Espèces), reçu de paiement téléchargeable, attribution et déblocage de cagnotte au bénéficiaire.
- **Gestion des Tours Complets** : détection de fin de cycle complet lorsque chaque membre a perçu son tour, avec option de relance d'un nouveau tour ou de clôture.
- **Entraide & Urgences** : demande d'aide financière prioritaire soumise à l'arbitrage de l'administrateur.
- **Démocratie et Gouvernance** : lancement de pétition de destitution avec signature numérique et session de vote pour l'élection d'un nouvel administrateur.
- **Tableau de Bord Super Admin** : statistiques globales, gestion des comptes (blocage/déblocage), vue sur toutes les tontines et journalisation des logs système.
