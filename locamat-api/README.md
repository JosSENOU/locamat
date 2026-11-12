<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

## Base de données MySQL (XAMPP)

Créez la base `locamat` dans phpMyAdmin, puis configurez la connexion dans `.env` avec `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=locamat` et vos identifiants MySQL. La configuration fournie utilise `root` sans mot de passe, comme dans une installation XAMPP locale par défaut.

Après toute modification de `.env`, exécutez `php artisan config:clear`, puis `php artisan migrate`. Les migrations reprennent les tables existantes `utilisateurs`, `materiels` et `locations` de `locamat` sans les vider et migrent les rôles, catégories et photos existants vers leurs tables dédiées.

## Authentification API

L'API utilise Sanctum et des jetons Bearer. Routes :

- `POST /api/register` avec `name`, `email`, `password`, `password_confirmation`, un `telephone` facultatif pour un locataire et obligatoire pour un propriétaire, et `role` (`locataire` ou `proprietaire`) ou `roles` (tableau des rôles autorisés). Le téléphone est saisi après sélection du pays : au Bénin, saisissez le numéro national `01 XX XX XX XX`; les autres pays utilisent leur indicatif international. Le rôle `locataire` est attribué par défaut et l'inscription publique ne peut pas créer d'administrateur. Un même compte peut être à la fois locataire et propriétaire.
- `POST /api/login` avec `email` et `password`.
- Tout nouveau mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial, sans espace (64 caractères maximum). Ces règles s'appliquent à l'inscription, à la réinitialisation et au changement de mot de passe ; les comptes existants conservent la possibilité de se connecter avec leur mot de passe actuel.
- Après inscription, un lien signé est envoyé à l’adresse fournie. Le compte ne peut se connecter qu’après confirmation. `POST /api/email/verification-notification` permet de demander un nouveau lien ; `GET /api/email/verify/{id}/{hash}` valide le lien signé et redirige vers `FRONTEND_URL`.
- Un locataire connecté peut ajouter le rôle propriétaire avec `POST /api/profile/become-owner`, en renseignant son téléphone et sa ville. Il conserve son rôle locataire.
- Après trois mots de passe incorrects, le compte est bloqué et l’utilisateur peut envoyer un message à l’assistance avec `POST /api/account-lock/support` (`email`, `message`). Les administrateurs reçoivent la demande par e-mail, l’utilisateur reçoit un accusé de réception, et le blocage prend fin automatiquement au plus tard 12 heures après son activation. Un administrateur peut également le lever plus tôt.
- `POST /api/forgot-password` avec `email` envoie un code à 6 chiffres par e-mail. Il expire après 10 minutes. `POST /api/verify-reset-code` attend `email` et `code` et retourne un jeton temporaire uniquement si le code est valide ; après 5 tentatives incorrectes, demandez un nouveau code. `POST /api/reset-password` attend ensuite `email`, le `token` retourné, `password` et `password_confirmation`.
- `POST /api/change-password` (authentifié) attend `current_password`, `password` et `password_confirmation`.
- Un locataire peut transmettre un signalement depuis les détails d’une de ses locations. Le signalement est visible par l’administration dans `/admin/reclamations`, qui peut le suivre et répondre au locataire.
- Configurez `FRONTEND_URL` pour diriger les liens vers l'application web. Pour Gmail/Google Workspace, configurez dans `locamat-api/.env` `MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME` (adresse expéditrice), `MAIL_PASSWORD` (mot de passe d'application Google) et `MAIL_FROM_ADDRESS` (la même adresse). Le secret OAuth Google n'est pas un mot de passe SMTP. Exécutez ensuite `php artisan config:clear`. Le transport local `log` écrit les e-mails dans les journaux au lieu de les distribuer.
- `GET /api/user` pour obtenir le compte connecté.
- `POST /api/logout` pour révoquer le jeton utilisé.
- `PUT /api/profile` pour modifier le nom et le téléphone du compte connecté. Pour un locataire ou un propriétaire, le même endpoint accepte `photo` (JPG, PNG ou WEBP, 2 Mo maximum) en multipart/form-data avec `_method=PUT`, ou `remove_photo=true` pour la supprimer. Les photos sont stockées dans `storage/app/public/profiles` ; lancez `php artisan storage:link` pour les servir. L’API interdit les photos de profil aux comptes administrateurs.

Les nouveaux mots de passe sont stockés avec le hachage sécurisé pris en charge par Laravel, jamais en MD5. Les anciens comptes avec un mot de passe MD5 sont mis à niveau automatiquement après une connexion réussie.

Pour les routes privées, envoyer `Authorization: Bearer <token>`. Protéger une route par rôle avec le middleware `role`, par exemple `role:admin`.

Les mots de passe restent hachés avec le mécanisme sécurisé de Laravel (bcrypt/Argon configuré dans l'application) ; ils ne sont pas stockés en MD5.

## Administration

Les routes `/api/admin/*` requièrent un compte administrateur authentifié. Le tableau de bord présente les statistiques de la plateforme, dont les revenus réalisés ; les comptes, matériels et locations sont consultables par recherche et pagination. L’administration peut désactiver/réactiver un compte et révoquer ses jetons, ainsi que masquer/réafficher un matériel via `disponible`. Le rôle d'un compte ne peut jamais être modifié depuis l'application. Les suppressions sont logiques et restent restaurables par le super-administrateur. Un administrateur ne peut pas désactiver son propre compte, et le dernier administrateur actif est protégé.

- `GET /api/admin/stats`
- `GET /api/admin/utilisateurs?q=&role=&is_active=&page=`
- `PATCH /api/admin/utilisateurs/{user}/statut` avec `is_active`
- `GET /api/admin/materiels?q=&disponible=&page=`
- `PATCH /api/admin/materiels/{materiel}/disponibilite` avec `disponible`
- `GET /api/admin/locations?q=&statut=&page=`

## Super-administration et historique

Créez le compte de contrôle depuis le terminal de l'API ; le mot de passe est demandé sans être affiché et doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial :

```powershell
php artisan super-admin:creer superadmin@locamat.bj --name="Super-administrateur"
```

Le compte de développement `superadmin@locamat.bj` (mot de passe `Password@123`) est fourni uniquement par `SuperAdminSeeder` hors production. Ne réutilisez pas ce mot de passe en production. La connexion super-administrateur est distincte (`POST /api/super-admin/login`).

Le super-administrateur consulte l'historique global, filtre les actions par utilisateur, consulte la fiche d'activité de chaque utilisateur et restaure les comptes, matériels ou catégories supprimés par erreur. La comptabilité et les revenus restent dans le tableau de bord administrateur (`/admin`), pas dans l'espace super-administrateur.

## Rappels de location et planificateur Windows

Les rappels de remise, de retour et de retard apparaissent dans l'application (cloche de notifications), une seule fois par location et par personne, au moment où l'utilisateur ouvre l'application : ils ne dépendent ni du planificateur ni de l'allumage de l'ordinateur à une heure précise, et n'envoient pas d'e-mail.

Seule la purge quotidienne de la corbeille utilise encore le planificateur Laravel (`schedule:run` chaque minute). Elle est facultative : sans elle, les éléments supprimés restent simplement dans la corbeille. Depuis PowerShell, dans le dossier `locamat-api`, pour l'activer :

```powershell
.\scripts\installer-planificateur-windows.ps1
```

Le script utilise le PHP actuellement dans le `PATH` et crée une tâche sous le compte Windows courant (session ouverte nécessaire). Vérifiez la programmation avec `php artisan schedule:list` et dans le Planificateur de tâches Windows (tâche `LocaMat Laravel Scheduler`).

## Module propriétaire : matériel

Toutes les routes ci-dessous nécessitent un jeton Bearer et le rôle `proprietaire` :

- `GET /api/proprietaire/materiels` liste uniquement les matériels du propriétaire et inclut `locations_count`.
- `POST /api/materiels` crée un matériel.
- `PUT /api/materiels/{id}` modifie un matériel appartenant au propriétaire.
- `DELETE /api/materiels/{id}` supprime un matériel sans location associée.
- `PATCH /api/materiels/{id}/disponibilite` attend `{"disponible": true}` ou `false`.

La création et la modification attendent `nom`, `categorie_id` (ou `categorie` pour compatibilité), `prix_journalier`, `quantite` et `etats_exemplaires` : une valeur (`neuf`, `bon` ou `usé`) obligatoire pour chaque exemplaire déclaré. Le locataire voit l’état de chacun sur la fiche du matériel. Le propriétaire peut modifier la quantité totale depuis le formulaire pour déclarer un achat supplémentaire ; l’état des nouveaux exemplaires est alors demandé. `quantite_disponible` indique le stock restant après déduction des locations confirmées, y compris celles dont la date de début est future. Une location confirmée consomme un exemplaire jusqu'à la clôture effective après confirmation du retour par le propriétaire, même si la date prévue est dépassée. Le catalogue masque automatiquement les matériels dont tous les exemplaires sont associés à des locations confirmées. `GET /api/categories` liste les catégories. Une publication nécessite au moins une photo ; envoyez de une à trois images dans le tableau multipart `photos[]` (JPG, PNG ou WEBP, 2 Mo maximum par image). Lors d'une modification, les photos existantes sont conservées si aucune nouvelle image n'est envoyée ; leur remplacement nécessite également au moins une image. La réponse contient `photos` (liste des chemins) et conserve `photo` comme alias vers la première image.

Un matériel avec une location en attente ou confirmée qui se termine aujourd'hui ou plus tard ne peut pas être supprimé. Une location confirmée échue mais pas encore clôturée empêche également sa suppression. L'historique est conservé, conformément à la clé étrangère.

## Module locataire : catalogue

Ces routes sont publiques :

- `GET /api/materiels?q=&categorie_id=&prix_min=&prix_max=&page=` recherche les matériels disponibles, avec filtres facultatifs et pagination de 12 résultats. Le filtre `categorie` par nom reste pris en charge pour compatibilité.
- `GET /api/categories` retourne les catégories enregistrées.
- `GET /api/materiels/{materiel}` retourne le détail public d'un matériel.
- `GET /api/materiels/{materiel}/periodes-reservees` retourne les périodes où tous les exemplaires sont déjà réservés, à partir d'aujourd'hui. Une location échue non clôturée peut renvoyer une période ouverte avec `date_fin: null`.

Les demandes de location sont accessibles aux locataires authentifiés. Dans `GET /api/locataire/locations`, le téléphone du propriétaire est masqué tant que la demande n'est pas confirmée ou terminée.

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Module locataire : locations

Les demandes se font sur `POST /api/locations` avec `materiel_id`, `date_debut` (`YYYY-MM-DD`), `date_fin` et un `message` facultatif. Une demande créée est `en_attente`. Le serveur calcule le nombre de jours inclusifs et fige le prix total au tarif journalier courant.

- `GET /api/locataire/locations` liste les demandes du locataire connecté.
- `PATCH /api/locations/{location}/annuler` annule uniquement sa propre demande encore en attente.
- `POST /api/locations/{location}/prolongation` permet au locataire de proposer une nouvelle date de retour avant la date limite. Chaque jour ajouté est facturé au prix journalier majoré de 10 %. Le propriétaire reçoit la demande, vérifie la disponibilité puis l'accepte ou la refuse ; l'extension et son montant ne s'appliquent qu'après acceptation.
- `GET /api/proprietaire/locations` liste les demandes portant sur ses matériels.
- `PATCH /api/proprietaire/locations/{location}/confirmer` confirme une demande en attente si les dates sont encore libres.
- `PATCH /api/proprietaire/locations/{location}/refuser` refuse une demande en attente ; `motif_refus` est facultatif.
- `PATCH /api/proprietaire/locations/{location}/terminer` est l'unique action de confirmation du retour : le propriétaire vérifie le matériel, confirme sa réception et clôture la location.

Seules les locations `confirmee` bloquent des dates ; les bornes de période sont inclusives. Si une location confirmée dépasse sa date de fin sans être clôturée, son exemplaire reste indisponible. Une pénalité est due pour chaque jour après la date limite, au tarif journalier majoré de 10 % ; son montant est arrêté lors de la confirmation du retour par le propriétaire. Les réponses de location exposent `penalite_jours`, `penalite_montant` et `montant_total_a_payer` (location et pénalité). Les opérations de création, de confirmation et d'approbation d'une prolongation verrouillent le matériel dans une transaction afin de refaire le contrôle des chevauchements avant l’écriture.

## Module propriétaire : demandes et tableau de bord

- `GET /api/proprietaire/demandes?statut=en_attente` liste les demandes paginées par 10 ; le filtre `statut` est facultatif (`en_attente`, `confirmee`, `refusee`, `annulee`, `terminee`).
- `PATCH /api/locations/{location}/confirmer` confirme une demande.
- `PATCH /api/locations/{location}/refuser` refuse une demande ; `motif_refus` est facultatif.
- `PATCH /api/locations/{location}/terminer` confirme la réception du matériel et termine la location uniquement après le signalement de restitution par le locataire.
- `GET /api/proprietaire/stats` retourne `demandes_en_attente`, `locations_en_cours` et `revenus_mois`.

Les statistiques ne portent que sur les matériels du propriétaire connecté. Une location en cours est confirmée et a commencé ; elle reste comptée si sa date de fin est dépassée jusqu'à la confirmation effective du retour. Les revenus du mois totalisent les locations terminées durant le mois courant ; `terminee_at` enregistre le moment où le propriétaire marque la restitution comme terminée.

## Validation et réponses API

Les validations des actions sont définies dans des FormRequests. Les réponses des utilisateurs, matériels et locations passent par des API Resources : le mot de passe n'est jamais sérialisé et le téléphone du propriétaire reste masqué tant qu'une location n'est pas confirmée ou terminée.

La langue par défaut et la langue de repli sont le français (`APP_LOCALE=fr`). Une erreur de validation renvoie le statut `422` et le format :

```json
{
  "message": "La date de début doit être aujourd’hui ou une date ultérieure.",
  "errors": {
    "date_debut": ["La date de début doit être aujourd’hui ou une date ultérieure."]
  }
}
```

Les autres erreurs API gardent le même contrat avec `message` et un objet `errors` vide. Les réponses paginées des ressources utilisent `data`, `links` et `meta`.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
