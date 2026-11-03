import { useEffect, useState } from "react";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { Eye, Package, Search } from "lucide-react";
import { toast } from "sonner";
import { useAdminMateriels, useUpdateAdminMaterielAvailability } from "@/hooks/use-admin";
import { errorMessage } from "@/lib/api";
import { formatFCFA } from "@/lib/format";
import { seo } from "@/lib/seo";
import type { AdminMaterielsParams } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { EtatBadge } from "@/components/Badges";
import { PaginationBar } from "@/components/PaginationBar";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

type MaterielSearch = { q?: string; disponible?: "0" | "1"; page?: number };

export const Route = createFileRoute("/admin/materiels")({
  validateSearch: (search: Record<string, unknown>): MaterielSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    disponible:
      search.disponible === "0" || search.disponible === "1" ? search.disponible : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Modération des matériels", "Consultez et gérez la visibilité des matériels LocaMat."),
  component: AdminMaterielsPage,
});

function AdminMaterielsPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/admin/materiels" });
  const [query, setQuery] = useState(search.q ?? "");
  const materiels = useAdminMateriels(search);
  const updateAvailability = useUpdateAdminMaterielAvailability();

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const filter = (patch: Partial<AdminMaterielsParams>) =>
    void navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }) });

  const setAvailability = (id: number, nom: string, disponible: boolean) => {
    updateAvailability.mutate(
      { id, disponible },
      {
        onSuccess: () =>
          toast.success(
            disponible
              ? `« ${nom} » est visible dans le catalogue.`
              : `« ${nom} » est masqué du catalogue.`,
          ),
        onError: (error) => toast.error(errorMessage(error)),
      },
    );
  };

  return (
    <>
      <PageHeader
        title="Matériels"
        description="Consultez les annonces et modérez leur visibilité sans supprimer l’historique."
      />
      <div className="mb-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_200px]">
        <div className="relative">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Nom du matériel ou catégorie…"
            aria-label="Rechercher un matériel"
            className="pl-9"
          />
        </div>
        <Select
          value={search.disponible ?? "tous"}
          onValueChange={(value) =>
            filter({ disponible: value === "0" || value === "1" ? value : undefined })
          }
        >
          <SelectTrigger aria-label="Filtrer par visibilité">
            <SelectValue placeholder="Toutes les annonces" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="tous">Toutes les annonces</SelectItem>
            <SelectItem value="1">Visibles</SelectItem>
            <SelectItem value="0">Masquées</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {materiels.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }, (_, index) => (
            <Skeleton key={index} className="h-28 rounded-xl" />
          ))}
        </div>
      ) : materiels.isError ? (
        <ErrorState error={materiels.error} onRetry={() => void materiels.refetch()} />
      ) : materiels.data.data.length === 0 ? (
        <EmptyState
          icon={Package}
          title="Aucun matériel trouvé"
          description="Modifiez votre recherche ou le filtre de visibilité."
          action={
            <Button
              variant="outline"
              onClick={() => {
                setQuery("");
                void navigate({ search: {} });
              }}
            >
              Effacer les filtres
            </Button>
          }
        />
      ) : (
        <>
          <ul className="space-y-3" aria-label="Matériels publiés">
            {materiels.data.data.map((item) => (
              <li key={item.id}>
                <article className="grid gap-4 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_210px] sm:items-center">
                  <div className="flex min-w-0 items-center gap-4">
                    <MaterielPhoto
                      categorie={item.categorie}
                      photo={item.photo}
                      alt={item.nom}
                      className="size-20 shrink-0 rounded-lg"
                      iconClassName="size-6"
                    />
                    <div className="min-w-0">
                      <Link
                        to="/materiels/$id"
                        params={{ id: String(item.id) }}
                        className="block truncate font-bold hover:text-primary"
                      >
                        {item.nom}
                      </Link>
                      <p className="mt-1 truncate text-sm text-muted-foreground">
                        {item.categorie} ·{" "}
                        {item.proprietaire?.name ?? `Propriétaire #${item.proprietaire_id}`}
                      </p>
                      <div className="mt-2 flex flex-wrap items-center gap-2">
                        <EtatBadge etat={item.etat} />
                        <span className="text-sm font-bold text-primary">
                          {formatFCFA(item.prix_journalier)} / jour
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {item.locations_count ?? 0} location
                          {item.locations_count === 1 ? "" : "s"}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center justify-between gap-3 border-t pt-3 sm:border-0 sm:pt-0">
                    <div>
                      <p className="text-sm font-semibold">
                        {item.disponible ? "Visible au catalogue" : "Masqué"}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {item.disponible
                          ? "Les locataires peuvent le trouver"
                          : "Conservé dans l’historique"}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button asChild size="icon" variant="ghost" aria-label={`Voir ${item.nom}`}>
                        <Link to="/materiels/$id" params={{ id: String(item.id) }}>
                          <Eye aria-hidden />
                        </Link>
                      </Button>
                      <Switch
                        checked={item.disponible}
                        onCheckedChange={(available) =>
                          setAvailability(item.id, item.nom, available)
                        }
                        disabled={updateAvailability.isPending}
                        aria-label={`${item.disponible ? "Masquer" : "Rendre visible"} ${item.nom}`}
                      />
                    </div>
                  </div>
                </article>
              </li>
            ))}
          </ul>
          <PaginationBar
            meta={materiels.data.meta}
            onPage={(page) =>
              void navigate({
                search: (previous) => ({ ...previous, page: page > 1 ? page : undefined }),
              })
            }
          />
        </>
      )}
    </>
  );
}
