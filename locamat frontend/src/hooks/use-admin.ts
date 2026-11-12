import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { AdminProfilBody } from "@/lib/schemas";
import type {
  AdminLocationsParams,
  AdminMaterielsParams,
  AdminStats,
  AdminUser,
  AdminUsersParams,
  AdminCategory,
  Location,
  Materiel,
  Paginated,
  Role,
} from "@/types/api";

export function useAdminStats() {
  return useQuery({
    queryKey: queryKeys.adminStats,
    queryFn: ({ signal }) => api<AdminStats>("/admin/stats", { signal }),
  });
}

export function useAdminCategories() {
  return useQuery({
    queryKey: queryKeys.adminCategories,
    queryFn: ({ signal }) => api<AdminCategory[]>("/admin/categories", { signal }),
  });
}

export function useSaveAdminCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, name }: { id?: number; name: string }) =>
      api<AdminCategory>(id ? `/admin/categories/${id}` : "/admin/categories", {
        method: id ? "PUT" : "POST",
        body: { name },
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.adminCategories });
      void qc.invalidateQueries({ queryKey: queryKeys.categories });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
    },
  });
}

export function useDeleteAdminCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<void>(`/admin/categories/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.adminCategories });
      void qc.invalidateQueries({ queryKey: queryKeys.categories });
    },
  });
}

export function useAdminUsers(params: AdminUsersParams) {
  return useQuery({
    queryKey: queryKeys.adminUsers(params),
    queryFn: ({ signal }) =>
      api<Paginated<AdminUser>>("/admin/utilisateurs", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}

export function useUpdateAdminUserStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      api<AdminUser>(`/admin/utilisateurs/${id}/statut`, { method: "PATCH", body: { is_active } }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
    },
  });
}

export function useUnlockAdminUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      api<AdminUser>(`/admin/utilisateurs/${id}/deverrouiller`, { method: "PATCH" }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
    },
  });
}

export function useUpdateAdminUserProfil() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...body }: { id: number } & AdminProfilBody) =>
      api<AdminUser>(`/admin/utilisateurs/${id}/profil`, { method: "PATCH", body }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
    },
  });
}

export function useAdminMateriels(params: AdminMaterielsParams) {
  return useQuery({
    queryKey: queryKeys.adminMateriels(params),
    queryFn: ({ signal }) =>
      api<Paginated<Materiel>>("/admin/materiels", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}

export function useUpdateAdminMaterielAvailability() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, disponible }: { id: number; disponible: boolean }) =>
      api<Materiel>(`/admin/materiels/${id}/disponibilite`, {
        method: "PATCH",
        body: { disponible },
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
    },
  });
}

export function useAdminLocations(params: AdminLocationsParams) {
  return useQuery({
    queryKey: queryKeys.adminLocations(params),
    queryFn: ({ signal }) =>
      api<Paginated<Location>>("/admin/locations", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}
