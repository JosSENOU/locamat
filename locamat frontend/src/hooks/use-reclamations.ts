import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { Paginated, Reclamation, ReclamationMotif, ReclamationStatut } from "@/types/api";

export function useMesReclamations() {
  return useQuery({
    queryKey: queryKeys.mesReclamations,
    queryFn: ({ signal }) => api<Reclamation[]>("/locataire/reclamations", { signal }),
  });
}

export function useCreateReclamation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: { location_id: number; motif: ReclamationMotif; description: string }) =>
      api<Reclamation>("/locataire/reclamations", { method: "POST", body }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.mesReclamations });
      void queryClient.invalidateQueries({ queryKey: ["admin", "reclamations"] });
    },
  });
}

export function useAdminReclamations(page: number) {
  return useQuery({
    queryKey: queryKeys.adminReclamations(page),
    queryFn: ({ signal }) =>
      api<Paginated<Reclamation>>("/admin/reclamations", { query: { page }, signal }),
    placeholderData: keepPreviousData,
  });
}

export function useUpdateReclamation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      statut,
      note_admin,
    }: {
      id: number;
      statut: ReclamationStatut;
      note_admin: string;
    }) =>
      api<Reclamation>(`/admin/reclamations/${id}`, {
        method: "PATCH",
        body: { statut, note_admin },
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "reclamations"] });
      void queryClient.invalidateQueries({ queryKey: queryKeys.mesReclamations });
    },
  });
}
