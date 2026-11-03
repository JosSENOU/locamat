import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type {
  AdminLocationsParams,
  AdminMaterielsParams,
  AdminStats,
  AdminUser,
  AdminUsersParams,
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

export function useAdminUsers(params: AdminUsersParams) {
  return useQuery({
    queryKey: queryKeys.adminUsers(params),
    queryFn: ({ signal }) =>
      api<Paginated<AdminUser>>("/admin/utilisateurs", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}

export function useUpdateAdminUserRole() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, role }: { id: number; role: Role }) =>
      api<AdminUser>(`/admin/utilisateurs/${id}/role`, { method: "PATCH", body: { role } }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
    },
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
