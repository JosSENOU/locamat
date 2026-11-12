import { useEffect, useState } from "react";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { ArrowLeft, BookOpenCheck, Search } from "lucide-react";
import { useJournal } from "@/hooks/use-super-admin";
import { formatDateTime } from "@/lib/format";
import {
  ACTION_GROUPS,
  ACTION_LABELS,
  describeDetails,
  isAction,
  ROLE_LABELS,
} from "@/lib/journal";
import { seo } from "@/lib/seo";
import type { AdminAction, AdminActionType } from "@/types/api";
import { PaginationBar } from "@/components/PaginationBar";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";

type JournalSearch = {
  q?: string;
  action?: AdminActionType;
  utilisateur?: number;
  nom?: string;
  du?: string;
  au?: string;
  page?: number;
};

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

export const Route = createFileRoute("/super-admin/journal")({
  validateSearch: (search: Record<string, unknown>): JournalSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    action:
      typeof search.action === "string" && isAction(search.action) ? search.action : undefined,
    utilisateur: Number(search.utilisateur) > 0 ? Number(search.utilisateur) : undefined,
    nom: typeof search.nom === "string" && search.nom.length > 0 ? search.nom : undefined,
    du: typeof search.du === "string" && DATE_PATTERN.test(search.du) ? search.du : undefined,
    au: typeof search.au === "string" && DATE_PATTERN.test(search.au) ? search.au : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Historique d’activité", "Tout ce qui se passe sur la plateforme LocaMat, par utilisateur."),
  component: JournalPage,
});

function JournalPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/super-admin/journal" });
  const [query, setQuery] = useState(search.q ?? "");
  const journal = useJournal({
    q: search.q,
    action: search.action,
    utilisateur: search.utilisateur,
    du: search.du,
    au: search.au,
    page: search.page,
  });

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const filter = (patch: Partial<JournalSearch>) =>
    void navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }) });

  const hasFilters = !!(search.q || search.action || search.utilisateur || search.du || search.au);

  return (
    <>
      <PageHeader
        title={search.utilisateur ? `Historique de ${search.nom ?? `l’utilisateur n° ${search.utilisateur}`}` : "Historique d’activité"}
        description={
          search.utilisateur
            ? "Tout ce que cet utilisateur a fait sur la plateforme, et ce qui lui est arrivé (actions d’un administrateur sur son compte, locations où il est locataire ou propriétaire)."
            : "Tout ce qui se passe sur la plateforme : inscriptions, connexions, matériels, locations et actions des administrateurs. Le nom de l’auteur est conservé même si son compte est supprimé."
        }
      />

      {search.utilisateur && (
        <div className="mb-4 flex flex-wrap gap-2">
          <Button variant="outline" size="sm" asChild>
            <Link to="/super-admin/utilisateurs">
              <ArrowLeft aria-hidden /> Retour aux utilisateurs
            </Link>
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => filter({ utilisateur: undefined, nom: undefined })}
          >
            Voir l’historique de tous les utilisateurs
          </Button>
        </div>
      )}

      <div className="mb-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_260px_160px_160px]">
        <div className="relative">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Auteur ou élément concerné…"
            aria-label="Rechercher dans l’historique"
            className="pl-9"
          />
        </div>
        <Select
          value={search.action ?? "toutes"}
          onValueChange={(value) => filter({ action: isAction(value) ? value : undefined })}
        >
          <SelectTrigger aria-label="Filtrer par type d’action">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="toutes">Toutes les actions</SelectItem>
            {ACTION_GROUPS.map((group) => (
              <SelectGroup key={group.label}>
                <SelectLabel>{group.label}</SelectLabel>
                {group.actions.map((action) => (
                  <SelectItem key={action} value={action}>
                    {ACTION_LABELS[action]}
                  </SelectItem>
                ))}
              </SelectGroup>
            ))}
          </SelectContent>
        </Select>
        <Input
          type="date"
          value={search.du ?? ""}
          max={search.au}
          onChange={(event) => filter({ du: event.target.value || undefined })}
          aria-label="Actions à partir du"
        />
        <Input
          type="date"
          value={search.au ?? ""}
          min={search.du}
          onChange={(event) => filter({ au: event.target.value || undefined })}
          aria-label="Actions jusqu’au"
        />
      </div>

      {journal.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 6 }, (_, index) => (
            <Skeleton key={index} className="h-16 rounded-xl" />
          ))}
        </div>
      ) : journal.isError ? (
        <ErrorState error={journal.error} onRetry={() => void journal.refetch()} />
      ) : journal.data.data.length === 0 ? (
        <EmptyState
          icon={BookOpenCheck}
          title="Aucune activité trouvée"
          description="L’activité de la plateforme apparaîtra ici au fil du temps."
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
          <ol className="space-y-2" aria-label="Historique d’activité">
            {journal.data.data.map((entry) => {
              const detail = describeDetails(entry);
              return (
                <li key={entry.id} className="rounded-xl border bg-card px-4 py-3 shadow-card">
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="font-bold">{ACTION_LABELS[entry.action] ?? entry.action}</span>
                    {entry.cible_libelle && (
                      <span className="text-sm font-semibold text-primary">
                        {entry.cible_libelle}
                      </span>
                    )}
                    <time
                      dateTime={entry.created_at}
                      className="text-xs text-muted-foreground sm:ml-auto"
                    >
                      {formatDateTime(entry.created_at)}
                    </time>
                  </div>
                  <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    Par{" "}
                    {entry.acteur_id ? (
                      <Link
                        to="/super-admin/journal"
                        search={{ utilisateur: entry.acteur_id, nom: entry.acteur_nom }}
                        className="font-semibold text-foreground underline-offset-2 hover:underline"
                      >
                        {entry.acteur_nom}
                      </Link>
                    ) : (
                      entry.acteur_nom
                    )}
                    {entry.acteur_role && (
                      <Badge variant="outline">
                        {ROLE_LABELS[entry.acteur_role] ?? entry.acteur_role}
                      </Badge>
                    )}
                    {detail && <span>· {detail}</span>}
                  </p>
                </li>
              );
            })}
          </ol>
          <PaginationBar
            meta={journal.data.meta}
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
