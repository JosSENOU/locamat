import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { CatalogueParams, Materiel, Paginated, Periode } from "@/types/api";

export function useCatalogue(params: CatalogueParams) {
  return useQuery({
    queryKey: queryKeys.catalogue(params),
    queryFn: ({ signal }) =>
      api<Paginated<Materiel>>("/materiels", { query: { ...params }, signal }),
    placeholderData: keepPreviousData,
  });
}

export function useMateriel(id: number) {
  return useQuery({
    queryKey: queryKeys.materiel(id),
    queryFn: ({ signal }) => api<Materiel>(`/materiels/${id}`, { signal }),
    enabled: Number.isFinite(id),
  });
}

export function usePeriodes(id: number) {
  return useQuery({
    queryKey: queryKeys.periodes(id),
    queryFn: ({ signal }) => api<Periode[]>(`/materiels/${id}/periodes-reservees`, { signal }),
    enabled: Number.isFinite(id),
  });
}

export function useOwnMateriels(enabled = true) {
  return useQuery({
    queryKey: queryKeys.ownMateriels,
    queryFn: ({ signal }) => api<Materiel[]>("/proprietaire/materiels", { signal }),
    enabled,
  });
}

function useInvalidateMateriels() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: queryKeys.ownMateriels });
    void qc.invalidateQueries({ queryKey: queryKeys.materiels });
  };
}

export function useSaveMateriel(id?: number) {
  const invalidate = useInvalidateMateriels();
  return useMutation({
    mutationFn: (fd: FormData) => {
      if (id) fd.set("_method", "PUT");
      return api<Materiel>(id ? `/materiels/${id}` : "/materiels", {
        method: "POST",
        formData: fd,
      });
    },
    onSuccess: invalidate,
  });
}

export function useDeleteMateriel() {
  const invalidate = useInvalidateMateriels();
  return useMutation({
    mutationFn: (id: number) => api<void>(`/materiels/${id}`, { method: "DELETE" }),
    onSuccess: invalidate,
  });
}

export function useToggleDisponibilite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, disponible }: { id: number; disponible: boolean }) =>
      api<Materiel>(`/materiels/${id}/disponibilite`, { method: "PATCH", body: { disponible } }),
    onMutate: async ({ id, disponible }) => {
      await qc.cancelQueries({ queryKey: queryKeys.ownMateriels });
      const previous = qc.getQueryData<Materiel[]>(queryKeys.ownMateriels);
      qc.setQueryData<Materiel[]>(queryKeys.ownMateriels, (old) =>
        old?.map((m) => (m.id === id ? { ...m, disponible } : m)),
      );
      return { previous };
    },
    onError: (_e, _v, ctx) => {
      if (ctx?.previous) qc.setQueryData(queryKeys.ownMateriels, ctx.previous);
    },
    onSettled: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.ownMateriels });
      void qc.invalidateQueries({ queryKey: queryKeys.materiels });
    },
  });
}
