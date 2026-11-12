import { useEffect, useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { Receipt, Search } from "lucide-react";
import { useAchats } from "@/hooks/use-super-admin";
import { STATUT_LABELS, STATUTS } from "@/lib/constants";
import { formatDateTime, formatFCFA, formatRange } from "@/lib/format";
import { seo } from "@/lib/seo";
import type { AchatsParams, Location, Statut } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { PaginationBar } from "@/components/PaginationBar";
import { RetardBadge, StatusBadge } from "@/components/StatusBadge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";

type AchatsSearch = AchatsParams;

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

function isStatut(value: string): value is Statut {
  return STATUTS.some((statut) => statut === value);
}

export const Route = createFileRoute("/super-admin/comptabilite")({
  validateSearch: (search: Record<string, unknown>): AchatsSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    statut:
      typeof search.statut === "string" && (search.statut === "tous" || isStatut(search.statut))
        ? search.statut
        : undefined,
    du: typeof search.du === "string" && DATE_PATTERN.test(search.du) ? search.du : undefined,
    au: typeof search.au === "string" && DATE_PATTERN.test(search.au) ? search.au : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Comptabilité", "Historique de tous les achats effectués sur la plateforme LocaMat."),
  component: ComptabilitePage,
});

function ComptabilitePage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/super-admin/comptabilite" });
  const [query, setQuery] = useState(search.q ?? "");
  const achats = useAchats(search);

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const filter = (patch: Partial<AchatsSearch>) =>
    void navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }) });

  const hasFilters = !!(search.q || search.statut || search.du || search.au);

  return (
    <>
      <PageHeader
        title="Comptabilité"
        description="Historique de tous les achats de la plateforme : locations confirmées ou terminées, pénalités de retard comprises."
      />

      <div className="mb-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_200px_160px_160px]">
        <div className="relative">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Matériel, locataire ou e-mail…"
            aria-label="Rechercher un achat"
            className="pl-9"
          />
        </div>
        <Select
          value={search.statut ?? "achats"}
          onValueChange={(value) =>
            filter({
              statut: value === "achats" ? undefined : value === "tous" || isStatut(value) ? value : undefined,
            })
          }
        >
          <SelectTrigger aria-label="Filtrer par statut">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="achats">Achats effectifs</SelectItem>
            <SelectItem value="tous">Tous les statuts</SelectItem>
            {STATUTS.map((statut) => (
              <SelectItem key={statut} value={statut}>
                {STATUT_LABELS[statut]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Input
          type="date"
          value={search.du ?? ""}
          max={search.au}
          onChange={(event) => filter({ du: event.target.value || undefined })}
          aria-label="Demandes à partir du"
        />
        <Input
          type="date"
          value={search.au ?? ""}
          min={search.du}
          onChange={(event) => filter({ au: event.target.value || undefined })}
          aria-label="Demandes jusqu’au"
        />
      </div>

      {achats.isPending ? (
        <div className="space-y-3">
          <Skeleton className="h-24 rounded-2xl" />
          {Array.from({ length: 5 }, (_, index) => (
            <Skeleton key={index} className="h-24 rounded-xl" />
          ))}
        </div>
      ) : achats.isError ? (
        <ErrorState error={achats.error} onRetry={() => void achats.refetch()} />
      ) : (
        <>
          <dl className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Total label="Achats" value={String(achats.data.totaux.nombre_achats)} />
            <Total label="Total réalisé" value={formatFCFA(achats.data.totaux.total_realise)} strong />
            <Total label="dont pénalités" value={formatFCFA(achats.data.totaux.penalites)} />
            <Total label="En cours" value={formatFCFA(achats.data.totaux.montant_en_cours)} />
          </dl>

          {achats.data.data.length === 0 ? (
            <EmptyState
              icon={Receipt}
              title="Aucun achat trouvé"
              description="Essayez avec une autre période, un autre statut ou une autre recherche."
              action={
                hasFilters ? (
                  <Button
                    variant="outline"
                    onClick={() => {
                      setQuery("");
                      void navigate({ search: {} });
                    }}
                  >
                    Effacer les filtres
                  </Button>
                ) : undefined
              }
            />
          ) : (
            <>
              <ul className="space-y-3" aria-label="Historique des achats">
                {achats.data.data.map((achat) => (
                  <li key={achat.id}>
                    <AchatCard achat={achat} />
                  </li>
                ))}
              </ul>
              <PaginationBar
                meta={achats.data.meta}
                onPage={(page) =>
                  void navigate({
                    search: (previous) => ({ ...previous, page: page > 1 ? page : undefined }),
                  })
                }
              />
            </>
          )}
        </>
      )}
    </>
  );
}

function Total({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="rounded-2xl border bg-card px-4 py-3 shadow-card">
      <dt className="text-xs font-semibold text-muted-foreground">{label}</dt>
      <dd className={strong ? "mt-1 text-xl font-extrabold tabular-nums" : "mt-1 text-lg font-bold tabular-nums"}>
        {value}
      </dd>
    </div>
  );
}

function AchatCard({ achat }: { achat: Location }) {
  const proprietaire = achat.materiel?.proprietaire?.name;
  const locataire = achat.locataire;

  return (
    <article className="grid gap-3 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="truncate font-bold">
            {achat.materiel?.nom ?? `Matériel n° ${achat.materiel_id}`}
          </h2>
          <StatusBadge statut={achat.statut} />
          {achat.en_retard && <RetardBadge />}
        </div>
        <p className="mt-1 text-sm text-muted-foreground">
          {formatRange(achat.date_debut, achat.date_fin)} · {achat.nb_jours} jour
          {achat.nb_jours > 1 ? "s" : ""} · {achat.quantite} exemplaire{achat.quantite > 1 ? "s" : ""}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          Locataire : <span className="font-semibold text-foreground">{locataire?.name ?? "—"}</span>
          {locataire?.email ? ` (${locataire.email})` : ""}
          {proprietaire && (
            <>
              {" "}
              · Propriétaire : <span className="font-semibold text-foreground">{proprietaire}</span>
            </>
          )}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          Demandé le {formatDateTime(achat.created_at)} · Achat n° {achat.id}
        </p>
      </div>
      <div className="text-left sm:text-right">
        <p className="text-lg font-extrabold tabular-nums">{formatFCFA(achat.montant_total_a_payer)}</p>
        {Number.parseFloat(achat.penalite_montant) > 0 && (
          <p className="text-xs text-destructive">
            dont {formatFCFA(achat.penalite_montant)} de pénalités
          </p>
        )}
      </div>
    </article>
  );
}
