import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { Location, OwnerStats, Paginated, Statut } from "@/types/api";

/* ---------- Locataire ---------- */

export function useMesLocations() {
  return useQuery({
    queryKey: queryKeys.mesLocations,
    queryFn: ({ signal }) => api<Location[]>("/locataire/locations", { signal }),
  });
}

export interface CreateLocationInput {
  materiel_id: number;
  quantite?: number;
  date_debut: string;
  date_fin: string;
  message?: string;
}

export function useCreateLocation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateLocationInput) =>
      api<Location>("/locations", { method: "POST", body }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: queryKeys.mesLocations }),
  });
}

export function useAnnulerLocation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<Location>(`/locations/${id}/annuler`, { method: "PATCH" }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: queryKeys.mesLocations }),
  });
}

export function useRequestProlongation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, date_fin }: { id: number; date_fin: string }) =>
      api<Location>(`/locations/${id}/prolongation`, {
        method: "POST",
        body: { date_fin },
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.mesLocations });
      void qc.invalidateQueries({ queryKey: queryKeys.demandesAll });
    },
  });
}

/* ---------- Propriétaire ---------- */

export function useDemandes(statut: Statut | undefined, page: number, materielId?: number) {
  return useQuery({
    queryKey: queryKeys.demandes(statut, page, materielId),
    queryFn: ({ signal }) =>
      api<Paginated<Location>>("/proprietaire/demandes", {
        query: { statut, page, materiel_id: materielId },
        signal,
      }),
    placeholderData: keepPreviousData,
  });
}

export function useOwnerStats() {
  return useQuery({
    queryKey: queryKeys.stats,
    queryFn: ({ signal }) => api<OwnerStats>("/proprietaire/stats", { signal }),
  });
}

function useOwnerAction<V>(fn: (v: V) => Promise<Location>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.demandesAll });
      void qc.invalidateQueries({ queryKey: queryKeys.stats });
      void qc.invalidateQueries({ queryKey: queryKeys.ownMateriels });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
      void qc.invalidateQueries({ queryKey: queryKeys.mesLocations });
    },
  });
}

export function useConfirmerLocation() {
  return useOwnerAction((id: number) =>
    api<Location>(`/proprietaire/locations/${id}/confirmer`, { method: "PATCH" }),
  );
}

export function useRefuserLocation() {
  return useOwnerAction(({ id, motif_refus }: { id: number; motif_refus?: string }) =>
    api<Location>(`/proprietaire/locations/${id}/refuser`, {
      method: "PATCH",
      body: motif_refus ? { motif_refus } : {},
    }),
  );
}

export function useTerminerLocation() {
  return useOwnerAction((id: number) =>
    api<Location>(`/proprietaire/locations/${id}/terminer`, { method: "PATCH" }),
  );
}

export function useConfirmerProlongation() {
  return useOwnerAction((id: number) =>
    api<Location>(`/proprietaire/locations/${id}/prolongation/confirmer`, { method: "PATCH" }),
  );
}

export function useRefuserProlongation() {
  return useOwnerAction((id: number) =>
    api<Location>(`/proprietaire/locations/${id}/prolongation/refuser`, { method: "PATCH" }),
  );
}
