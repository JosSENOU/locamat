import type {
  AdminLocationsParams,
  AdminMaterielsParams,
  AdminUsersParams,
  CatalogueParams,
  Statut,
} from "@/types/api";

export const queryKeys = {
  user: ["user"] as const,
  materiels: ["materiels"] as const,
  catalogue: (p: CatalogueParams) => ["materiels", "catalogue", p] as const,
  materiel: (id: number) => ["materiels", "detail", id] as const,
  periodes: (id: number) => ["materiels", "periodes", id] as const,
  ownMateriels: ["proprietaire", "materiels"] as const,
  demandesAll: ["proprietaire", "demandes"] as const,
  demandes: (statut: Statut | undefined, page: number) =>
    ["proprietaire", "demandes", statut ?? "toutes", page] as const,
  stats: ["proprietaire", "stats"] as const,
  mesLocations: ["locataire", "locations"] as const,
  admin: ["admin"] as const,
  adminStats: ["admin", "stats"] as const,
  adminUsers: (p: AdminUsersParams) => ["admin", "utilisateurs", p] as const,
  adminMateriels: (p: AdminMaterielsParams) => ["admin", "materiels", p] as const,
  adminLocations: (p: AdminLocationsParams) => ["admin", "locations", p] as const,
};
