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

Après toute modification de `.env`, exécutez `php artisan config:clear`, puis `php artisan migrate`. Les migrations reprennent les tables existantes `utilisateurs`, `materiels` et `locations` de `locamat` sans les vider.

## Authentification API

L'API utilise Sanctum et des jetons Bearer. Routes :

- `POST /api/register` avec `name`, `email`, `password`, `password_confirmation`, un `telephone` facultatif et un `role` facultatif (`locataire` par défaut, ou `proprietaire`). L'inscription publique ne peut pas créer d'administrateur.
- `POST /api/login` avec `email` et `password`.
- `GET /api/user` pour obtenir le compte connecté.
- `POST /api/logout` pour révoquer le jeton utilisé.
- `PUT /api/profile` pour modifier le nom et le téléphone du compte connecté. Pour un locataire ou un propriétaire, le même endpoint accepte `photo` (JPG, PNG ou WEBP, 2 Mo maximum) en multipart/form-data avec `_method=PUT`, ou `remove_photo=true` pour la supprimer. Les photos sont stockées dans `storage/app/public/profiles` ; lancez `php artisan storage:link` pour les servir. L’API interdit les photos de profil aux comptes administrateurs.

Pour les routes privées, envoyer `Authorization: Bearer <token>`. Protéger une route par rôle avec le middleware `role`, par exemple `role:admin`.

## Administration

Les routes `/api/admin/*` requièrent un compte administrateur authentifié. Le tableau de bord agrège les statistiques de la plateforme ; les comptes, matériels et locations sont consultables par recherche et pagination. L’administration peut changer le rôle d’un compte, le désactiver/réactiver et révoquer ses jetons, ainsi que masquer/réafficher un matériel via `disponible`. Aucune suppression définitive n’est exposée. Un administrateur ne peut pas modifier son propre rôle ni désactiver son compte, et le dernier administrateur actif est protégé.

- `GET /api/admin/stats`
- `GET /api/admin/utilisateurs?q=&role=&is_active=&page=`
- `PATCH /api/admin/utilisateurs/{user}/role` avec `role`
- `PATCH /api/admin/utilisateurs/{user}/statut` avec `is_active`
- `GET /api/admin/materiels?q=&disponible=&page=`
- `PATCH /api/admin/materiels/{materiel}/disponibilite` avec `disponible`
- `GET /api/admin/locations?q=&statut=&page=`

## Module propriétaire : matériel

Toutes les routes ci-dessous nécessitent un jeton Bearer et le rôle `proprietaire` :

- `GET /api/proprietaire/materiels` liste uniquement les matériels du propriétaire et inclut `locations_count`.
- `POST /api/materiels` crée un matériel.
- `PUT /api/materiels/{id}` modifie un matériel appartenant au propriétaire.
- `DELETE /api/materiels/{id}` supprime un matériel sans location associée.
- `PATCH /api/materiels/{id}/disponibilite` attend `{"disponible": true}` ou `false`.

La création et la modification attendent `nom`, `categorie`, `prix_journalier` et `etat` (`neuf`, `bon` ou `usé`). La photo est facultative ; envoyez-la en multipart/form-data au champ `photo` (jpg, png ou webp, 2 Mo maximum). Les images sont enregistrées dans `storage/app/public/materiels` et leur chemin est retourné dans `photo`.

Un matériel avec une location en attente ou confirmée qui se termine aujourd'hui ou plus tard ne peut pas être supprimé. L'historique est également conservé, conformément à la clé étrangère.

## Module locataire : catalogue

Ces routes sont publiques :

- `GET /api/materiels?q=&categorie=&prix_min=&prix_max=&page=` recherche les matériels disponibles, avec filtres facultatifs et pagination de 12 résultats.
- `GET /api/materiels/{materiel}` retourne le détail public d'un matériel.
- `GET /api/materiels/{materiel}/periodes-reservees` retourne les périodes confirmées dont la date de fin est aujourd'hui ou ultérieure.

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
- `GET /api/proprietaire/locations` liste les demandes portant sur ses matériels.
- `PATCH /api/proprietaire/locations/{location}/confirmer` confirme une demande en attente si les dates sont encore libres.
- `PATCH /api/proprietaire/locations/{location}/refuser` refuse une demande en attente ; `motif_refus` est facultatif.
- `PATCH /api/proprietaire/locations/{location}/terminer` termine une location confirmée après restitution.

Seules les locations `confirmee` bloquent des dates ; les bornes de période sont inclusives. Les opérations de création et de confirmation verrouillent le matériel dans une transaction afin de refaire le contrôle des chevauchements avant l’écriture.

## Module propriétaire : demandes et tableau de bord

- `GET /api/proprietaire/demandes?statut=en_attente` liste les demandes paginées par 10 ; le filtre `statut` est facultatif (`en_attente`, `confirmee`, `refusee`, `annulee`, `terminee`).
- `PATCH /api/locations/{location}/confirmer` confirme une demande.
- `PATCH /api/locations/{location}/refuser` refuse une demande ; `motif_refus` est facultatif.
- `PATCH /api/locations/{location}/terminer` termine une location confirmée après restitution.
- `GET /api/proprietaire/stats` retourne `demandes_en_attente`, `locations_en_cours` et `revenus_mois`.

Les statistiques ne portent que sur les matériels du propriétaire connecté. Une location en cours est confirmée et inclut la date du jour dans sa période. Les revenus du mois totalisent les locations terminées durant le mois courant ; `terminee_at` enregistre le moment où le propriétaire marque la restitution comme terminée.

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
