export type Role = "proprietaire" | "locataire" | "admin";

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  telephone: string | null;
  photo: string | null;
}

export interface AdminUser extends User {
  is_active: boolean;
  materiels_count?: number;
  created_at: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export type Etat = "neuf" | "bon" | "usé";

export interface ProprietaireLite {
  id: number;
  name: string;
  photo?: string | null;
  telephone?: string | null;
}

export interface Materiel {
  id: number;
  proprietaire_id: number;
  nom: string;
  categorie: string;
  description: string | null;
  photo: string | null;
  prix_journalier: string;
  etat: Etat;
  disponible: boolean;
  locations_count?: number;
  proprietaire?: ProprietaireLite;
  created_at: string;
  updated_at: string;
}

export type Statut = "en_attente" | "confirmee" | "refusee" | "annulee" | "terminee";

export interface Location {
  id: number;
  materiel_id: number;
  locataire_id: number;
  date_debut: string;
  date_fin: string;
  nb_jours: number;
  prix_total: string;
  statut: Statut;
  message: string | null;
  motif_refus: string | null;
  terminee_at: string | null;
  materiel?: Materiel;
  locataire?: User;
  created_at: string;
  updated_at: string;
}

export interface Periode {
  date_debut: string;
  date_fin: string;
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
