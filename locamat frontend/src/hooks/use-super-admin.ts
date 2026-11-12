import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { AdministrateurValues } from "@/lib/schemas";
import type {
  AdministrateursParams,
  AdminAction,
  AdminUser,
  CorbeilleCategorie,
  CorbeilleMateriel,
  CorbeillePage,
  CorbeilleParams,
  CorbeilleUtilisateur,
  JournalParams,
  Paginated,
  SuperAdminApercu,
  UtilisateurHistorique,
  UtilisateursParams,
} from "@/types/api";

export function useSuperAdminApercu() {
  return useQuery({
    queryKey: queryKeys.superAdminApercu,
    queryFn: ({ signal }) => api<SuperAdminApercu>("/super-admin/apercu", { signal }),
  });
}

export function useJournal(params: JournalParams) {
  return useQuery({
    queryKey: queryKeys.superAdminJournal(params),
    queryFn: ({ signal }) =>
      api<Paginated<AdminAction>>("/super-admin/journal", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}

/** Tous les comptes (supprimés compris), pour ouvrir l'historique de chacun. */
export function useUtilisateursHistorique(params: UtilisateursParams) {
  return useQuery({
    queryKey: queryKeys.superAdminUtilisateurs(params),
    queryFn: ({ signal }) =>
      api<Paginated<UtilisateurHistorique>>("/super-admin/utilisateurs", {
        query: { ...params },
        signal,
      }),
    placeholderData: keepPreviousData,
  });
}

export function useAdministrateurs(params: AdministrateursParams) {
  return useQuery({
    queryKey: queryKeys.superAdminAdministrateurs(params),
    queryFn: ({ signal }) =>
      api<Paginated<AdminUser>>("/super-admin/administrateurs", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}

export function useCreateAdministrateur() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ telephone, ...values }: AdministrateurValues) =>
      api<AdminUser>("/super-admin/administrateurs", {
        method: "POST",
        body: { ...values, ...(telephone ? { telephone } : {}) },
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.superAdmin });
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
    },
  });
}

/** Active ou désactive un compte administrateur (réservé au super-administrateur). */
export function useToggleAdministrateur() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      api<AdminUser>(`/admin/utilisateurs/${id}/statut`, { method: "PATCH", body: { is_active } }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.superAdmin });
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
    },
  });
}

/** Suppression logique d'un compte : il part dans la corbeille et reste restaurable. */
export function useDeleteUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      api<{ message: string }>(`/admin/utilisateurs/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.superAdmin });
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
    },
  });
}

export function useCorbeilleUtilisateurs(params: CorbeilleParams) {
  return useQuery({
    queryKey: queryKeys.superAdminCorbeille(params),
    queryFn: ({ signal }) =>
      api<CorbeillePage<CorbeilleUtilisateur>>("/super-admin/corbeille", {
        query: { ...params },
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: params.type === "utilisateurs",
  });
}

export function useCorbeilleMateriels(params: CorbeilleParams) {
  return useQuery({
    queryKey: queryKeys.superAdminCorbeille(params),
    queryFn: ({ signal }) =>
      api<CorbeillePage<CorbeilleMateriel>>("/super-admin/corbeille", {
        query: { ...params },
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: params.type === "materiels",
  });
}

type CorbeilleAction = "restaurer" | "purger";

/** Restaure (PATCH …/restaurer) ou supprime définitivement (DELETE) un élément de la corbeille. */
export function useCorbeilleAction(type: CorbeilleParams["type"]) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, action }: { id: number; action: CorbeilleAction }) =>
      api<{ message: string; resultat?: string }>(
        action === "restaurer"
          ? `/super-admin/corbeille/${type}/${id}/restaurer`
          : `/super-admin/corbeille/${type}/${id}`,
        { method: action === "restaurer" ? "PATCH" : "DELETE" },
      ),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.superAdmin });
      void qc.invalidateQueries({ queryKey: queryKeys.admin });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
    },
  });
}

export function useCorbeilleCategories(params: CorbeilleParams) {
  return useQuery({
    queryKey: queryKeys.superAdminCorbeille(params),
    queryFn: ({ signal }) =>
      api<CorbeillePage<CorbeilleCategorie>>("/super-admin/corbeille", {
        query: { ...params },
        signal,
      }),
    placeholderData: keepPreviousData,
    enabled: params.type === "categories",
  });
}
