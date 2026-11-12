export type Role = "proprietaire" | "locataire" | "admin";

/** Rôle d'un compte : le super-administrateur n'est jamais attribuable depuis l'application. */
export type AccountRole = Role | "super_admin";

export interface User {
  id: number;
  name: string;
  email: string;
  role: AccountRole;
  roles?: AccountRole[];
  telephone: string | null;
  photo: string | null;
  /** Localisation (propriétaires uniquement). */
  ville?: string | null;
  quartier?: string | null;
  adresse?: string | null;
}

export interface AdminUser extends User {
  is_active: boolean;
  is_locked: boolean;
  materiels_count?: number;
  created_at: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export interface RegistrationResponse {
  message: string;
  email: string;
}

export type Etat = "neuf" | "bon" | "usé";

export interface Category {
  id: number;
  name: string;
}

export interface AdminCategory extends Category {
  materiels_count: number;
}

export interface ProprietaireLite {
  id: number;
  name: string;
  photo?: string | null;
  telephone?: string | null;
  /** Public : ville + quartier. */
  ville?: string | null;
  quartier?: string | null;
  /** Adresse précise : fournie seulement une fois la location confirmée. */
  adresse?: string | null;
}

export interface Materiel {
  id: number;
  user_id?: number;
  proprietaire_id: number;
  nom: string;
  categorie: string;
  description: string | null;
  photo: string | null;
  photos?: string[];
  prix_journalier: string;
  etat: Etat;
  etats_exemplaires?: Etat[];
  disponible: boolean;
  quantite: number;
  quantite_disponible?: number;
  /** Locations effectuées (terminées). */
  locations_count?: number;
  /** Locations confirmées, donc en cours. */
  locations_confirmees_count?: number;
  proprietaire?: ProprietaireLite;
  created_at: string;
  updated_at: string;
}

export interface AppNotification {
  id: string;
  type: string | null;
  titre: string;
  message: string;
  /** Relative frontend path, e.g. "/proprietaire/demandes?statut=en_attente". */
  lien: string | null;
  location_id: number | null;
  materiel_id: number | null;
  lu: boolean;
  created_at: string;
}

export interface NotificationsPage extends Paginated<AppNotification> {
  unread_count: number;
}

export type Statut = "en_attente" | "confirmee" | "refusee" | "annulee" | "terminee";

export interface LocationHistorique {
  id: number;
  evenement:
    | "creation"
    | "confirmation"
    | "refus"
    | "annulation"
    | "prolongation_demandee"
    | "prolongation_acceptee"
    | "prolongation_refusee"
    | "retour_signale"
    | "terminee";
  statut: Statut;
  note: string | null;
  acteur: { id: number; name: string } | null;
  created_at: string;
}

export interface Location {
  id: number;
  materiel_id: number;
  locataire_id: number;
  date_debut: string;
  date_fin: string;
  /** Date de retour prévue à l'origine (avant toute prolongation acceptée). */
  date_fin_initiale: string | null;
  /** Location confirmée dont la date de retour est dépassée. */
  en_retard: boolean;
  nb_jours: number;
  /** Nombre d'exemplaires loués. */
  quantite: number;
  prix_total: string;
  statut: Statut;
  message: string | null;
  motif_refus: string | null;
  terminee_at: string | null;
  prolongation_date_fin: string | null;
  prolongation_montant: string | null;
  prolongation_statut: "en_attente" | "confirmee" | "refusee" | null;
  penalite_jours: number;
  penalite_montant: string;
  montant_total_a_payer: string;
  materiel?: Materiel;
  locataire?: User;
  /** Chronologie des changements de statut (du plus ancien au plus récent). */
  historiques?: LocationHistorique[];
  created_at: string;
  updated_at: string;
}

export type ReclamationMotif =
  "comportement_inapproprie" | "materiel_non_conforme" | "paiement" | "securite" | "autre";

export type ReclamationStatut = "nouvelle" | "en_cours" | "traitee" | "rejetee";

export interface Reclamation {
  id: number;
  location_id: number;
  motif: ReclamationMotif;
  description: string;
  statut: ReclamationStatut;
  note_admin: string | null;
  traitee_at: string | null;
  locataire?: Pick<User, "id" | "name" | "email">;
  proprietaire?: Pick<User, "id" | "name" | "email">;
  location?: {
    id: number;
    date_debut: string;
    date_fin: string;
    statut: Statut;
    materiel: { id: number; nom: string } | null;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface Periode {
  date_debut: string;
  date_fin: string | null;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  from: number | null;
  to: number | null;
}

export interface Paginated<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: PaginationMeta;
}

export interface OwnerStats {
  demandes_en_attente: number;
  locations_en_cours: number;
  revenus_mois: string;
}

export interface AdminStats {
  total_utilisateurs: number;
  utilisateurs_actifs: number;
  utilisateurs_suspendus: number;
  locataires: number;
  proprietaires: number;
  administrateurs: number;
  total_materiels: number;
  materiels_disponibles: number;
  locations_total: number;
  demandes_en_attente: number;
  locations_confirmees: number;
  locations_terminees: number;
  revenus_total: string;
}

export interface AdminUsersParams {
  q?: string;
  role?: Role;
  is_active?: "0" | "1";
  page?: number;
}

export interface AdminMaterielsParams {
  q?: string;
  disponible?: "0" | "1";
  page?: number;
}

export interface AdminLocationsParams {
  q?: string;
  statut?: Statut;
  page?: number;
}

export interface CatalogueParams {
  q?: string;
  categorie?: string;
  prix_min?: number;
  prix_max?: number;
  page?: number;
}

/* ------------------------------------------------------------------ */
/* Espace super-administrateur                                         */
/* ------------------------------------------------------------------ */

export interface SuperAdminApercu {
  administrateurs: number;
  corbeille: { utilisateurs: number; materiels: number; categories: number };
  retention_days: number;
}

export type CorbeilleType = "utilisateurs" | "materiels" | "categories";

/** Qui a supprimé l'élément (renseigné quand la suppression a été journalisée). */
export interface SupprimePar {
  nom: string;
  role: AccountRole | null;
  le: string;
}

export interface CorbeilleUtilisateur {
  id: number;
  name: string;
  email: string;
  role: AccountRole | null;
  roles: AccountRole[];
  telephone: string | null;
  deleted_at: string;
  purge_le: string;
  anonymise: boolean;
  locations_count: number;
  materiels_count: number;
  supprime_par: SupprimePar | null;
}

export interface CorbeilleMateriel {
  id: number;
  nom: string;
  photo: string | null;
  prix_journalier: string;
  proprietaire: { id: number; name: string; dans_la_corbeille: boolean } | null;
  deleted_at: string;
  purge_le: string;
  locations_count: number;
  conserve_pour_historique: boolean;
  supprime_par: SupprimePar | null;
}

export interface CorbeilleCategorie {
  id: number;
  name: string;
  deleted_at: string;
  purge_le: string;
  supprime_par: SupprimePar | null;
}

export interface CorbeillePage<T> extends Paginated<T> {
  retention_days: number;
}

export interface CorbeilleParams {
  type: CorbeilleType;
  q?: string;
  page?: number;
}

export type AdminActionType =
  | "inscription"
  | "connexion"
  | "deconnexion"
  | "compte_bloque"
  | "profil_personnel_modifie"
  | "mot_de_passe_modifie"
  | "mot_de_passe_reinitialise"
  | "materiel_cree"
  | "materiel_modifie"
  | "location_demandee"
  | "location_confirmee"
  | "location_refusee"
  | "location_annulee"
  | "location_terminee"
  | "prolongation_demandee"
  | "prolongation_confirmee"
  | "prolongation_refusee"
  | "reclamation_creee"
  | "reclamation_mise_a_jour"
  | "role_modifie"
  | "statut_modifie"
  | "compte_debloque"
  | "profil_modifie"
  | "compte_supprime"
  | "compte_restaure"
  | "compte_purge"
  | "admin_cree"
  | "materiel_disponibilite"
  | "materiel_supprime"
  | "materiel_restaure"
  | "materiel_purge"
  | "categorie_creee"
  | "categorie_modifiee"
  | "categorie_supprimee"
  | "categorie_restauree"
  | "categorie_purgee";

export interface AdminAction {
  id: number;
  acteur_id: number | null;
  acteur_nom: string;
  acteur_role: AccountRole | null;
  action: AdminActionType;
  cible_type: "utilisateur" | "materiel" | "categorie" | "location" | "reclamation";
  cible_id: number | null;
  cible_libelle: string | null;
  concerne_id: number | null;
  details: Record<string, unknown> | null;
  created_at: string;
}

export interface JournalParams {
  q?: string;
  action?: AdminActionType;
  utilisateur?: number;
  du?: string;
  au?: string;
  page?: number;
}

export interface UtilisateurHistorique {
  id: number;
  name: string;
  email: string;
  telephone: string | null;
  role: AccountRole | null;
  roles: AccountRole[];
  is_active: boolean;
  supprime: boolean;
  anonymise: boolean;
  created_at: string;
  actions_count: number;
  derniere_activite: string | null;
}

export interface UtilisateursParams {
  q?: string;
  role?: Exclude<AccountRole, "super_admin">;
  page?: number;
}

export interface AdministrateursParams {
  q?: string;
  page?: number;
}
