import { useEffect, useState } from "react";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { History, Search, Users } from "lucide-react";
import { useUtilisateursHistorique } from "@/hooks/use-super-admin";
import { formatDateTime } from "@/lib/format";
import { seo } from "@/lib/seo";
import type { UtilisateurHistorique, UtilisateursParams } from "@/types/api";
import { PaginationBar } from "@/components/PaginationBar";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { Badge } from "@/components/ui/badge";
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

type UtilisateursSearch = UtilisateursParams;

const ROLES = ["locataire", "proprietaire", "admin"] as const;

const ROLE_LABELS: Record<string, string> = {
  locataire: "Locataire",
  proprietaire: "Propriétaire",
  admin: "Administrateur",
  super_admin: "Super-administrateur",
};

function isRole(value: string): value is (typeof ROLES)[number] {
  return ROLES.some((role) => role === value);
}

export const Route = createFileRoute("/super-admin/utilisateurs")({
  validateSearch: (search: Record<string, unknown>): UtilisateursSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    role: typeof search.role === "string" && isRole(search.role) ? search.role : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Utilisateurs", "Ouvrez l’historique d’activité de chaque utilisateur de LocaMat."),
  component: UtilisateursPage,
});

function UtilisateursPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/super-admin/utilisateurs" });
  const [query, setQuery] = useState(search.q ?? "");
  const utilisateurs = useUtilisateursHistorique(search);

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  return (
    <>
      <PageHeader
        title="Utilisateurs"
        description="Tous les comptes de la plateforme, supprimés compris. Ouvrez l’historique d’un utilisateur pour voir tout ce qu’il a fait et tout ce qui lui est arrivé."
      />

      <div className="mb-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
        <div className="relative">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Nom, e-mail ou téléphone…"
            aria-label="Rechercher un utilisateur"
            className="pl-9"
          />
        </div>
        <Select
          value={search.role ?? "tous"}
          onValueChange={(value) =>
            void navigate({
              search: (previous) => ({
                ...previous,
                role: isRole(value) ? value : undefined,
                page: undefined,
              }),
            })
          }
        >
          <SelectTrigger aria-label="Filtrer par rôle">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="tous">Tous les rôles</SelectItem>
            {ROLES.map((role) => (
              <SelectItem key={role} value={role}>
                {ROLE_LABELS[role]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {utilisateurs.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }, (_, index) => (
            <Skeleton key={index} className="h-20 rounded-xl" />
          ))}
        </div>
      ) : utilisateurs.isError ? (
        <ErrorState error={utilisateurs.error} onRetry={() => void utilisateurs.refetch()} />
      ) : utilisateurs.data.data.length === 0 ? (
        <EmptyState
          icon={Users}
          title="Aucun utilisateur trouvé"
          description="Essayez avec une autre recherche."
          action={
            search.q || search.role ? (
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
          <ul className="space-y-3" aria-label="Utilisateurs de la plateforme">
            {utilisateurs.data.data.map((utilisateur) => (
              <li key={utilisateur.id}>
                <UtilisateurCard utilisateur={utilisateur} />
              </li>
            ))}
          </ul>
          <PaginationBar
            meta={utilisateurs.data.meta}
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

function UtilisateurCard({ utilisateur }: { utilisateur: UtilisateurHistorique }) {
  return (
    <article className="grid gap-3 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="truncate font-bold">{utilisateur.name}</h2>
          {utilisateur.role && (
            <Badge variant="secondary">{ROLE_LABELS[utilisateur.role] ?? utilisateur.role}</Badge>
          )}
          {utilisateur.supprime && <Badge variant="destructive">Dans la corbeille</Badge>}
          {utilisateur.anonymise && <Badge variant="outline">Anonymisé</Badge>}
          {!utilisateur.supprime && !utilisateur.is_active && (
            <Badge variant="outline">Désactivé</Badge>
          )}
        </div>
        <p className="mt-1 truncate text-sm text-muted-foreground">
          {utilisateur.email}
          {utilisateur.telephone ? ` · ${utilisateur.telephone}` : ""}
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          {utilisateur.actions_count} action(s) enregistrée(s)
          {utilisateur.derniere_activite
            ? ` · dernière activité le ${formatDateTime(utilisateur.derniere_activite)}`
            : " · aucune activité enregistrée"}
        </p>
      </div>
      <Button size="sm" asChild>
        <Link
          to="/super-admin/journal"
          search={{ utilisateur: utilisateur.id, nom: utilisateur.name }}
        >
          <History aria-hidden /> Voir l’historique
        </Link>
      </Button>
    </article>
  );
}
