# Build the COMPLETE frontend of LocaMat , a French-language web app for peer-to-peer equipment...

Build the COMPLETE frontend of "LocaMat", a French-language web app for peer-to-peer equipment rental (computers, cameras, projectors, sound systems...). The backend already exists: a Laravel REST API (Sanctum Bearer tokens). Do NOT create any backend, do NOT use Supabase / Lovable Cloud / edge functions / mock data. Every feature must call the real Laravel API described below. All UI copy in French (correct accents), prices in FCFA.

## Stack and architecture
- React + TypeScript + Vite, Tailwind, shadcn/ui, React Router, TanStack Query (data fetching, caching, invalidation), react-hook-form + zod (forms), sonner (toasts), lucide-react icons, date-fns (fr locale), shadcn Calendar (react-day-picker).
- Clean structure: src/lib/api.ts (fetch wrapper), src/lib/format.ts, src/lib/constants.ts, src/types/api.ts, src/hooks/ (useAuth, queries/mutations per resource), src/components/, src/pages/.
- API base URL from `import.meta.env.VITE_API_URL`, default `http://localhost:8000/api`. Create `.env.example` with VITE_API_URL. The "API origin" (for photos) is VITE_API_URL without the trailing `/api`.

## API client rules (src/lib/api.ts)
- Always send `Accept: application/json`. JSON bodies with `Content-Type: application/json`; FormData for photo uploads (do not set Content-Type manually).
- Token stored in localStorage (`locamat_token`), sent as `Authorization: Bearer <token>`. On app load, if a token exists call `GET /user` to hydrate the auth context (show a full-page loader meanwhile). On any 401 from an authenticated call: clear token, redirect to /connexion with a toast "Session expirée".
- Error format from the API (always): `{ "message": string, "errors": { field: string[] } | {} }`. Statuses: 422 validation/business rule, 403 forbidden, 404, 429 (throttle: show "Trop de tentatives, réessayez dans une minute"). Build an ApiError class; map `errors` to react-hook-form field errors via setError; otherwise show `message` in a toast. API messages are already in French: display them as is.
- Dates: the API returns dates as ISO datetimes (e.g. "2026-11-05T00:00:00.000000Z"). ALWAYS take only the first 10 characters (YYYY-MM-DD) and parse as a local date to avoid timezone shifts. Send dates to the API as `YYYY-MM-DD`.
- Money: `prix_journalier` and `prix_total` are decimal strings like "15000.00". Format with Intl.NumberFormat('fr-FR') + " FCFA" (no decimals when .00).
- Photos: `photo` is a relative path like "materiels/abc.jpg" or null. URL = `${API_ORIGIN}/storage/${photo}`. Use a nice placeholder (icon on muted background) when null or when the image fails to load.

## API contract
Auth
- POST /register {name, email, password, password_confirmation, telephone?, role: "proprietaire"|"locataire"} -> 201 {user, token}
- POST /login {email, password} -> {user, token} (wrong credentials: 422 "Identifiants incorrects.")
- POST /logout (auth) ; GET /user (auth) -> user
- user = {id, name, email, role: "proprietaire"|"locataire"|"admin", telephone|null, photo|null}
- PUT /profile {name, telephone} (auth) -> user.
- For locataire/proprietaire profiles only, PUT /profile accepts an optional multipart `photo` (jpg/png/webp, max 2 Mo) or `remove_photo=true`; administrators cannot manage profile photos.

Public catalogue
- GET /materiels?q=&categorie=&prix_min=&prix_max=&page= -> paginated Laravel resource: {data: Materiel[], links, meta:{current_page,last_page,total,per_page,...}}, 12 per page, only available items. Validation: prix_max must be >= prix_min.
- GET /materiels/{id} -> Materiel (includes proprietaire:{id,name}); note it may return an item with disponible=false: then show an "Indisponible" badge and disable the request form.
- GET /materiels/{id}/periodes-reservees -> [{date_debut, date_fin}] confirmed periods (inclusive bounds) ending today or later.
- Materiel = {id, proprietaire_id, nom, categorie, description|null, photo|null, prix_journalier, etat: "neuf"|"bon"|"usé", disponible: boolean, locations_count?, proprietaire?:{id,name}, created_at, updated_at}
- There is no categories endpoint: use a fixed list in constants.ts: Informatique, Photo, Vidéo, Vidéoprojection, Sonorisation, Éclairage, Accessoires, Autre. The `categorie` field is a free string equal-matched by the catalogue filter, so always use values from this list.

Owner (role proprietaire only; 403 otherwise)
- GET /proprietaire/materiels -> Materiel[] (with locations_count)
- POST /materiels (multipart/form-data): nom*, categorie*, description, prix_journalier* (number >= 0, max 2 decimals), etat* (neuf|bon|usé), disponible (send "1"/"0"), photo (jpg/png/webp, max 2048 KB) -> 201 Materiel
- UPDATE a materiel: the route is PUT /materiels/{id}, but PHP cannot parse multipart PUT, so ALWAYS send a POST to /materiels/{id} with FormData containing `_method=PUT` (Laravel method spoofing) when updating (with or without a new photo). If the user did not pick a new photo, do not send the photo field.
- DELETE /materiels/{id} -> 204; 422 with a French message if it has current/upcoming or past rentals (show the message in a toast; offer the "Indisponible" toggle as an alternative in that message area).
- PATCH /materiels/{id}/disponibilite {disponible: boolean} -> Materiel
- GET /proprietaire/demandes?statut=&page= -> paginated {data: Location[], links, meta} (10 per page); statut in en_attente|confirmee|refusee|annulee|terminee
- GET /proprietaire/stats -> {demandes_en_attente: number, locations_en_cours: number, revenus_mois: "12000.00"}
- PATCH /proprietaire/locations/{id}/confirmer -> Location (422 "Ces dates chevauchent une location déjà confirmée." if overlap)
- PATCH /proprietaire/locations/{id}/refuser {motif_refus?: string} -> Location
- PATCH /proprietaire/locations/{id}/terminer -> Location (only from confirmee)

Renter (role locataire only)
- GET /locataire/locations -> Location[] (plain array, not paginated)
- POST /locations {materiel_id, date_debut, date_fin, message?} -> 201 Location (errors: unavailable item, own item 403, overlap 422, start date in the past)
- PATCH /locations/{id}/annuler -> Location (only while en_attente)
- Location = {id, materiel_id, locataire_id, date_debut, date_fin, nb_jours, prix_total, statut, message|null, motif_refus|null, terminee_at|null, materiel?: Materiel & {proprietaire?: {id, name, telephone|null}}, locataire?: {id,name,email,role,telephone}, created_at, updated_at}
- statut lifecycle: en_attente -> confirmee | refusee | annulee ; confirmee -> terminee. Labels/colors: en_attente "En attente" (amber), confirmee "Confirmée" (green), refusee "Refusée" (red), annulee "Annulée" (gray), terminee "Terminée" (blue). Create a reusable StatusBadge.
- The owner's phone (materiel.proprietaire.telephone) is null until the rental is confirmee or terminee: the renter UI must show the owner's contact only then, and otherwise say "Les coordonnées du propriétaire seront visibles une fois la demande confirmée."

## Business rules to enforce client-side too (the API remains the source of truth)
- Start date cannot be before today; end date >= start date. Number of days = inclusive count (start and end included). Total = days x price per day, shown live BEFORE submitting. The price is frozen at request time (mention it in a small help text).
- Pending requests do not block dates; only confirmed ones do. In the calendar, disable days inside the periodes-reservees and past days; the range selection must not cross a booked period (show an inline error if it does).
- An owner cannot rent their own item: on detail page, if user.id === materiel.proprietaire_id show an info banner instead of the form. An owner-role account cannot send requests at all: show "Connectez-vous avec un compte locataire pour réserver".
- Unavailable item: form disabled with explanation.
- Role-based access: route guards. Anonymous -> /connexion?redirect=<path>. Wrong role -> friendly 403 page with a button to go back to the right home. Navbar adapts to role.
- If a user with role admin logs in, show an administrator dashboard with account, listing, and rental management.

## Pages / routes (all responsive: mobile-first, tablet, desktop)
Public
- `/` Home = catalogue: hero with headline + search bar (q), category chips, then results grid of cards (photo, name, category, état badge, price/day, owner name). Filters: keyword, category select, price min/max (with validation prix_max >= prix_min), sync filters + page in the URL query string, "Réinitialiser" button, pagination controls, result count, skeleton cards while loading, polished empty state ("Aucun matériel ne correspond..."). Filters in a side panel on desktop and a Sheet/Drawer on mobile. Debounce the search input.
- `/materiels/:id` Detail: image, name, category, état, description, owner name, price/day, availability badge, "Périodes déjà réservées" list + calendar showing booked days, and the rental request card (sticky on desktop): date range picker, live days + total, optional message textarea, submit button. If not logged in: CTA "Se connecter pour réserver". On success: toast + redirect to /mes-locations.
- `/connexion` and `/inscription`: elegant split layout. Registration: name, email, telephone (optional), role chosen with two big selectable cards ("Je loue du matériel" = locataire / "Je propose mon matériel" = proprietaire), password + confirmation (min 8, show/hide toggle). After login/register redirect: proprietaire -> /proprietaire, locataire -> redirect param or /.
Locataire
- `/mes-locations`: list of all their requests as cards (equipment photo/name, dates, nb_jours, prix_total, StatusBadge, message). Filter tabs by status with counts. Refusée shows the motif_refus. En attente shows "Annuler la demande" with a confirmation AlertDialog. Confirmée/terminée shows owner contact (name, phone with tel: link and a WhatsApp button). Click opens a detail Dialog/Sheet with full info. Empty state with CTA to the catalogue.
Propriétaire (layout with sidebar on desktop / bottom or hamburger nav on mobile)
- `/proprietaire` Dashboard: 3 stat cards (Demandes en attente, Locations en cours, Revenus du mois in FCFA), plus a "Dernières demandes en attente" quick list with confirm/refuse actions and a shortcut "Ajouter un matériel".
- `/proprietaire/materiels`: list/table of own items (photo, name, category, état, price/day, locations_count, availability Switch that calls PATCH /disponibilite with optimistic update, edit and delete buttons with AlertDialog confirmation). Empty state with CTA.
- `/proprietaire/materiels/nouveau` and `/proprietaire/materiels/:id/modifier`: one shared form (nom, catégorie select, description, prix par jour, état as segmented control Neuf/Bon/Usé, disponible switch, photo upload with drag & drop, client-side validation of type jpg/png/webp and size <= 2 Mo, live preview, ability to keep existing photo on edit). Warn in a hint that changing the price does not affect existing requests. Load existing item for edit from GET /proprietaire/materiels (find by id) .
- `/proprietaire/demandes`: requests received, status filter tabs (Toutes, En attente, Confirmées, Refusées, Terminées, Annulées) using the API `statut` param, server pagination (10/page). Each row/card: item, renter name, dates, nb_jours, total, StatusBadge. A detail Dialog/Sheet shows: item, renter name + phone (+email), dates, nb_jours, prix_total, renter message, and actions: Confirmer (en_attente), Refuser with an optional motif textarea dialog (en_attente), Marquer comme terminée (confirmee, with confirmation "Le matériel a-t-il bien été restitué ?"), and a green "Contacter sur WhatsApp" button opening `https://wa.me/<phone>?text=<encoded pre-filled message in French mentioning the item name and dates>`. Phone normalization for wa.me: keep digits only, strip leading "00"; if it does not already start with "229" and has <= 10 digits, prefix "229". Hide the button if no phone. After each action invalidate queries (demandes, stats) and toast the result; show the API message on 422.
Common
- `/profil`: edit name, telephone, and (for non-admins) a JPG/PNG/WEBP profile photo (maximum 2 Mo); email and role are read-only. The photo can be changed or removed via PUT /profile and updates the auth context.
- Navbar: logo "LocaMat", links by role, user dropdown (Profil, Déconnexion). Footer. 404 page.

## Quality bar (very important: I want a flawless, polished result)
- Distinctive, modern, trustworthy marketplace look, not a generic template: deep teal primary, warm amber accent, stone neutrals, generous spacing, rounded-xl cards, subtle shadows, smooth hover/transition states, a refined typographic hierarchy (e.g. Inter or Plus Jakarta Sans), a clean logo wordmark. Light theme (dark mode optional if cheap).
- Every list/query has: loading skeletons, error state with retry, and a meaningful empty state. Every mutation has a loading state on its button (disabled + spinner) and success/error toasts. No console errors, no layout shift, no horizontal scroll on mobile.
- Accessible: labels, aria attributes, focus rings, keyboard navigable dialogs, alt texts. Set document titles per page.
- All form validations in French with zod, matching the API rules, plus server errors mapped back to fields.
- Code quality: typed everywhere, no `any`, reusable components (MaterielCard, StatusBadge, PriceTag, EmptyState, ErrorState, ConfirmDialog, PhotoUpload, DateRangePicker, ProtectedRoute), TanStack Query keys centralized.
- Add a short README section explaining how to set VITE_API_URL and run the frontend against the Laravel API (php artisan serve on port 8000).

This project was built with [Lovable](https://lovable.dev).

## Build with Lovable

Continue developing this project in the [Lovable editor](https://lovable.dev/projects/31b9d1ce-1049-4ffe-8955-391faff18f06).

- **Ship faster**: describe what you want to build and Lovable handles the code.
- **Stay in sync**: every change made in Lovable is committed straight to this repository.
- **Full ownership**: this code is yours. Push to `main` on GitHub and your changes sync back into Lovable, ready for your next prompt.

## Development

Prefer working locally? You need Node.js and npm — [install with nvm](https://github.com/nvm-sh/nvm#installing-and-updating).

### Connecter l'API Laravel

L'URL de l'API est définie dans `locamat frontend/.env.local` via `VITE_API_URL`. La valeur par défaut est `http://localhost:8000/api` (voir `.env.example`).

Depuis le dossier de travail, lancez l'API puis le frontend dans deux terminaux :

```sh
cd locamat-api
php artisan serve --port=8000
```

```sh
cd "locamat frontend"
npm install
npm run dev
```

Si Laravel utilise une autre adresse, mettez à jour `VITE_API_URL` dans `.env.local`, puis redémarrez Vite.

```sh
git clone <this-repository-url>
cd <repository-name>
npm i
npm run dev
```
