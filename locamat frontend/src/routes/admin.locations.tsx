import { useEffect, useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { CalendarDays, ClipboardList, LoaderCircle, Mail, Phone, Search, X } from "lucide-react";
import { useAdminLocations } from "@/hooks/use-admin";
import { formatDate, formatFCFA, formatRange } from "@/lib/format";
import { seo } from "@/lib/seo";
import { STATUTS, STATUT_PLURAL } from "@/lib/constants";
import type { AdminLocationsParams, Statut } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { StatusBadge } from "@/components/StatusBadge";
import { PaginationBar } from "@/components/PaginationBar";
import { StatusTabs } from "@/components/StatusTabs";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";

type LocationSearch = { q?: string; statut?: Statut; page?: number };
type StatusFilter = Statut | "toutes";
const STATUT_ORDER: Statut[] = ["en_attente", "confirmee", "terminee", "refusee", "annulee"];

function isStatut(value: string): value is Statut {
  return STATUTS.some((statut) => statut === value);
}

export const Route = createFileRoute("/admin/locations")({
  validateSearch: (search: Record<string, unknown>): LocationSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    statut:
      typeof search.statut === "string" && isStatut(search.statut) ? search.statut : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Suivi des locations", "Consultez les demandes et l’historique des locations LocaMat."),
  component: AdminLocationsPage,
});

function AdminLocationsPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/admin/locations" });
  const [query, setQuery] = useState(search.q ?? "");
  const locations = useAdminLocations(search);

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 200);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const filter = (patch: Partial<AdminLocationsParams>) =>
    void navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }) });

  return (
    <>
      <PageHeader
        title="Suivi des locations"
        description="Consultez les demandes, leur état et les informations associées. Aucune action n’altère l’historique."
      />
      <div className="relative mb-5">
        <Search
          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Matériel, locataire ou e-mail…"
          aria-label="Rechercher par matériel, locataire ou e-mail"
          className="pr-10 pl-9"
        />
        {query && (
          <button
            type="button"
            className="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onClick={() => {
              setQuery("");
              void navigate({
                search: (previous) => ({ ...previous, q: undefined, page: undefined }),
              });
            }}
            aria-label="Effacer la recherche"
          >
            <X className="size-4" aria-hidden />
          </button>
        )}
      </div>
      <StatusTabs<StatusFilter>
        label="Filtrer les locations par statut"
        value={search.statut ?? "toutes"}
        onChange={(status) => filter({ statut: status === "toutes" ? undefined : status })}
        items={[
          { value: "toutes", label: "Toutes" },
          ...STATUT_ORDER.map((status) => ({
            value: status as StatusFilter,
            label: STATUT_PLURAL[status],
          })),
        ]}
      />

      {locations.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }, (_, index) => (
            <Skeleton key={index} className="h-36 rounded-xl" />
          ))}
        </div>
      ) : locations.isError ? (
        <ErrorState error={locations.error} onRetry={() => void locations.refetch()} />
      ) : locations.data.data.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title="Aucune location trouvée"
          description={
            search.q
              ? `Aucune location ne correspond à « ${search.q} ». Essayez un autre terme.`
              : "Aucune demande ne correspond aux filtres sélectionnés."
          }
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
          {locations.isFetching && (
            <p className="mb-3 flex items-center gap-2 text-sm text-muted-foreground" role="status">
              <LoaderCircle className="size-4 animate-spin" aria-hidden />
              Filtrage des locations…
            </p>
          )}
          {search.q && (
            <p className="mb-3 text-sm text-muted-foreground" aria-live="polite">
              {locations.data.meta.total} résultat{locations.data.meta.total === 1 ? "" : "s"} pour
              {` « ${search.q} »`}
            </p>
          )}
          <ul
            className={`space-y-3 transition-opacity ${locations.isFetching ? "opacity-60" : ""}`}
            aria-label="Demandes de location"
            aria-busy={locations.isFetching}
          >
            {locations.data.data.map((location) => {
              const materiel = location.materiel;
              const locataire = location.locataire;
              return (
                <li key={location.id}>
                  <article className="rounded-xl border bg-card p-4 shadow-card sm:p-5">
                    <div className="flex flex-col gap-4 sm:flex-row">
                      <MaterielPhoto
                        categorie={materiel?.categorie}
                        photo={materiel?.photo}
                        alt={materiel?.nom ?? "Matériel"}
                        className="size-20 shrink-0 rounded-lg"
                        iconClassName="size-6"
                      />
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h2 className="font-bold">
                            {materiel?.nom ?? `Matériel #${location.materiel_id}`}
                          </h2>
                          <StatusBadge statut={location.statut} />
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                          {materiel?.proprietaire?.name ?? "Propriétaire"} ·{" "}
                          {locataire?.name ?? `Locataire #${location.locataire_id}`}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                          <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                            <CalendarDays className="size-4" aria-hidden />
                            {formatRange(location.date_debut, location.date_fin)} ·{" "}
                            {location.nb_jours} jour{location.nb_jours === 1 ? "" : "s"}
                          </span>
                          <span className="font-bold text-primary">
                            {formatFCFA(location.prix_total)}
                          </span>
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                          {locataire?.email && (
                            <a
                              className="inline-flex items-center gap-1.5 hover:text-primary"
                              href={`mailto:${locataire.email}`}
                            >
                              <Mail className="size-3.5" aria-hidden /> {locataire.email}
                            </a>
                          )}
                          {locataire?.telephone && (
                            <a
                              className="inline-flex items-center gap-1.5 hover:text-primary"
                              href={`tel:${locataire.telephone}`}
                            >
                              <Phone className="size-3.5" aria-hidden /> {locataire.telephone}
                            </a>
                          )}
                          <span>Demande reçue le {formatDate(location.created_at)}</span>
                        </div>
                        {location.message && (
                          <p className="mt-3 rounded-lg bg-muted/60 p-3 text-sm text-muted-foreground">
                            « {location.message} »
                          </p>
                        )}
                        {location.motif_refus && (
                          <p className="mt-2 text-sm text-destructive">
                            Motif du refus : {location.motif_refus}
                          </p>
                        )}
                      </div>
                    </div>
                  </article>
                </li>
              );
            })}
          </ul>
          <PaginationBar
            meta={locations.data.meta}
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
