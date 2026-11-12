import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { Flag } from "lucide-react";
import { toast } from "sonner";
import { useAdminReclamations, useUpdateReclamation } from "@/hooks/use-reclamations";
import { errorMessage } from "@/lib/api";
import { formatDateTime, formatRange } from "@/lib/format";
import { seo } from "@/lib/seo";
import type { Reclamation, ReclamationMotif, ReclamationStatut } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { PaginationBar } from "@/components/PaginationBar";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";

const MOTIFS: Record<ReclamationMotif, string> = {
  comportement_inapproprie: "Comportement inapproprié",
  materiel_non_conforme: "Matériel non conforme à l’annonce",
  paiement: "Problème de paiement",
  securite: "Problème de sécurité",
  autre: "Autre problème",
};

const STATUTS: { value: ReclamationStatut; label: string }[] = [
  { value: "nouvelle", label: "Nouvelle" },
  { value: "en_cours", label: "En cours d’examen" },
  { value: "traitee", label: "Traitée" },
  { value: "rejetee", label: "Clôturée" },
];

export const Route = createFileRoute("/admin/reclamations")({
  validateSearch: (search: Record<string, unknown>): { page?: number } => ({
    page: Number(search.page) > 1 ? Math.floor(Number(search.page)) : undefined,
  }),
  head: () =>
    seo(
      "Plaintes des locataires",
      "Consultez et traitez les signalements concernant les propriétaires.",
    ),
  component: AdminReclamationsPage,
});

function AdminReclamationsPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/admin/reclamations" });
  const page = search.page ?? 1;
  const reclamations = useAdminReclamations(page);

  return (
    <>
      <PageHeader
        title="Plaintes des locataires"
        description="Ces signalements sont visibles uniquement par l’administration. Examinez les faits et consignez votre décision."
      />
      {reclamations.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }, (_, index) => (
            <Skeleton key={index} className="h-56 rounded-xl" />
          ))}
        </div>
      ) : reclamations.isError ? (
        <ErrorState error={reclamations.error} onRetry={() => void reclamations.refetch()} />
      ) : reclamations.data.data.length === 0 ? (
        <EmptyState
          icon={Flag}
          title="Aucune plainte"
          description="Les nouveaux signalements des locataires apparaîtront ici."
        />
      ) : (
        <>
          <ul className="space-y-4" aria-label="Plaintes reçues">
            {reclamations.data.data.map((reclamation) => (
              <li key={reclamation.id}>
                <ReclamationCard reclamation={reclamation} />
              </li>
            ))}
          </ul>
          <PaginationBar
            meta={reclamations.data.meta}
            onPage={(nextPage) =>
              void navigate({ search: { page: nextPage > 1 ? nextPage : undefined } })
            }
          />
        </>
      )}
    </>
  );
}

function ReclamationCard({ reclamation }: { reclamation: Reclamation }) {
  const updateReclamation = useUpdateReclamation();
  const [statut, setStatut] = useState(reclamation.statut);
  const [noteAdmin, setNoteAdmin] = useState(reclamation.note_admin ?? "");

  useEffect(() => {
    setStatut(reclamation.statut);
    setNoteAdmin(reclamation.note_admin ?? "");
  }, [reclamation.note_admin, reclamation.statut]);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    try {
      await updateReclamation.mutateAsync({
        id: reclamation.id,
        statut,
        note_admin: noteAdmin,
      });
      toast.success("Le suivi de la plainte a été mis à jour.");
    } catch (error) {
      toast.error(errorMessage(error));
    }
  };

  const location = reclamation.location;
  return (
    <article className="space-y-4 rounded-xl border bg-card p-4 shadow-card sm:p-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs text-muted-foreground">
            Signalée le {formatDateTime(reclamation.created_at)} · Location #
            {reclamation.location_id}
          </p>
          <h2 className="mt-1 text-lg font-bold">{MOTIFS[reclamation.motif]}</h2>
        </div>
        <span className="rounded-full bg-muted px-3 py-1 text-xs font-semibold">
          {STATUTS.find((option) => option.value === reclamation.statut)?.label}
        </span>
      </header>
      <p className="whitespace-pre-wrap text-sm leading-relaxed">{reclamation.description}</p>
      <dl className="grid gap-3 rounded-lg bg-muted/40 p-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-xs text-muted-foreground">Locataire</dt>
          <dd className="font-semibold">
            {reclamation.locataire?.name ?? "Compte supprimé"}
            {reclamation.locataire?.email && (
              <span className="block font-normal text-muted-foreground">
                {reclamation.locataire.email}
              </span>
            )}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-muted-foreground">Propriétaire signalé</dt>
          <dd className="font-semibold">
            {reclamation.proprietaire?.name ?? "Compte supprimé"}
            {reclamation.proprietaire?.email && (
              <span className="block font-normal text-muted-foreground">
                {reclamation.proprietaire.email}
              </span>
            )}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-muted-foreground">Matériel</dt>
          <dd className="font-semibold">{location?.materiel?.nom ?? "Matériel indisponible"}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted-foreground">Période de location</dt>
          <dd className="font-semibold">
            {location ? formatRange(location.date_debut, location.date_fin) : "Non disponible"}
          </dd>
        </div>
      </dl>
      <form onSubmit={submit} className="space-y-3 border-t pt-4">
        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_2fr]">
          <label className="space-y-1.5 text-sm font-semibold">
            Décision
            <select
              value={statut}
              onChange={(event) => {
                const next = STATUTS.find((option) => option.value === event.target.value);
                if (next) setStatut(next.value);
              }}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-normal"
            >
              {STATUTS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1.5 text-sm font-semibold">
            Réponse ou note pour le locataire
            <Textarea
              value={noteAdmin}
              maxLength={3000}
              rows={2}
              onChange={(event) => setNoteAdmin(event.target.value)}
              className="font-normal"
              placeholder="Ajoutez une réponse ou un commentaire de suivi (facultatif)."
            />
          </label>
        </div>
        <div className="flex justify-end">
          <Button type="submit" disabled={updateReclamation.isPending}>
            {updateReclamation.isPending ? "Enregistrement…" : "Enregistrer le suivi"}
          </Button>
        </div>
      </form>
    </article>
  );
}
