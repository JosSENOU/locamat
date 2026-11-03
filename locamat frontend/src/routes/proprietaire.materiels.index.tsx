import { useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Package, Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { useDeleteMateriel, useOwnMateriels, useToggleDisponibilite } from "@/hooks/use-materiels";
import { errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import type { Materiel } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { EtatBadge } from "@/components/Badges";
import { PriceTag } from "@/components/PriceTag";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";

export const Route = createFileRoute("/proprietaire/materiels/")({
  head: () => seo("Mes matériels", "Gérez votre matériel : prix, disponibilité, modifications."),
  component: MesMateriels,
});

function MesMateriels() {
  const { data, isPending, isError, error, refetch } = useOwnMateriels();
  const toggle = useToggleDisponibilite();
  const del = useDeleteMateriel();
  const [toDelete, setToDelete] = useState<Materiel | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const onToggle = (m: Materiel, v: boolean) =>
    toggle.mutate(
      { id: m.id, disponible: v },
      {
        onSuccess: () => toast.success(v ? `« ${m.nom} » est visible dans le catalogue.` : `« ${m.nom} » est masqué du catalogue.`),
        onError: (e) => toast.error(errorMessage(e)),
      },
    );

  const confirmDelete = () => {
    if (!toDelete) return;
    del.mutate(toDelete.id, {
      onSuccess: () => {
        toast.success("Matériel supprimé.");
        setToDelete(null);
      },
      onError: (e) => {
        setDeleteError(errorMessage(e));
        toast.error(errorMessage(e));
      },
    });
  };

  return (
    <>
      <PageHeader
        title="Mes matériels"
        description="Le prix, l'état et la visibilité de chaque équipement."
        actions={<Button asChild><Link to="/proprietaire/materiels/nouveau"><Plus aria-hidden /> Ajouter</Link></Button>}
      />
      {isPending ? (
        <div className="space-y-3">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}</div>
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : data.length === 0 ? (
        <EmptyState
          icon={Package}
          title="Aucun matériel publié"
          description="Publiez votre premier équipement pour commencer à recevoir des demandes."
          action={<Button asChild><Link to="/proprietaire/materiels/nouveau"><Plus aria-hidden /> Ajouter un matériel</Link></Button>}
        />
      ) : (
        <ul className="space-y-3">
          {data.map((m) => (
            <li key={m.id} className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-card sm:flex-row sm:items-center">
              <div className="flex flex-1 items-center gap-4">
                <MaterielPhoto categorie={m.categorie} photo={m.photo} alt={m.nom} className="size-20 shrink-0 rounded-lg" iconClassName="size-6" />
                <div className="min-w-0 space-y-1">
                  <Link to="/materiels/$id" params={{ id: String(m.id) }} className="block truncate font-bold hover:text-primary">{m.nom}</Link>
                  <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span className="font-semibold text-primary">{m.categorie}</span>
                    <EtatBadge etat={m.etat} />
                    <span>{m.locations_count ?? 0} location{(m.locations_count ?? 0) > 1 ? "s" : ""}</span>
                  </div>
                  <PriceTag value={m.prix_journalier} size="sm" />
                </div>
              </div>
              <div className="flex items-center justify-between gap-4 border-t pt-3 sm:border-0 sm:pt-0">
                <label className="flex items-center gap-2 text-sm font-medium">
                  <Switch checked={m.disponible} onCheckedChange={(v) => onToggle(m, v)} aria-label={`Disponibilité de ${m.nom}`} />
                  {m.disponible ? "Disponible" : "Indisponible"}
                </label>
                <div className="flex gap-1">
                  <Button variant="ghost" size="icon" asChild aria-label={`Modifier ${m.nom}`}>
                    <Link to="/proprietaire/materiels/$id/modifier" params={{ id: String(m.id) }}><Pencil /></Link>
                  </Button>
                  <Button variant="ghost" size="icon" className="text-destructive" aria-label={`Supprimer ${m.nom}`} onClick={() => { setDeleteError(null); setToDelete(m); }}>
                    <Trash2 />
                  </Button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
      <ConfirmDialog
        open={!!toDelete}
        onOpenChange={(o) => !o && setToDelete(null)}
        title={`Supprimer « ${toDelete?.nom ?? ""} » ?`}
        description="Cette action est définitive."
        confirmLabel="Supprimer"
        destructive
        loading={del.isPending}
        onConfirm={confirmDelete}
      >
        {deleteError && toDelete && (
          <div role="alert" className="space-y-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm">
            <p className="font-medium text-destructive">{deleteError}</p>
            {toDelete.disponible && (
              <div className="flex items-center justify-between gap-3">
                <span className="text-muted-foreground">Vous pouvez plutôt le masquer du catalogue.</span>
                <Button size="sm" variant="outline" onClick={() => { onToggle(toDelete, false); setToDelete(null); }}>
                  Rendre indisponible
                </Button>
              </div>
            )}
          </div>
        )}
      </ConfirmDialog>
    </>
  );
}
