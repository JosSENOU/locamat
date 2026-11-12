import { useEffect, useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { History, Package, RotateCcw, Search, Tag, Trash2, Users } from "lucide-react";
import { toast } from "sonner";
import {
  useCorbeilleAction,
  useCorbeilleCategories,
  useCorbeilleMateriels,
  useCorbeilleUtilisateurs,
} from "@/hooks/use-super-admin";
import { errorMessage } from "@/lib/api";
import { formatDate, formatFCFA, formatRetention } from "@/lib/format";
import { seo } from "@/lib/seo";
import type {
  CorbeilleCategorie,
  CorbeilleMateriel,
  CorbeilleType,
  CorbeilleUtilisateur,
  SupprimePar,
} from "@/types/api";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { PaginationBar } from "@/components/PaginationBar";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";

type CorbeilleSearch = { type?: CorbeilleType; q?: string; page?: number };

export const Route = createFileRoute("/super-admin/corbeille")({
  validateSearch: (search: Record<string, unknown>): CorbeilleSearch => ({
    type:
      search.type === "materiels" || search.type === "categories" ? search.type : undefined,
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Corbeille", "Restaurez ou supprimez définitivement les comptes, matériels et catégories supprimés."),
  component: CorbeillePage,
});

const ROLE_LABELS: Record<string, string> = {
  locataire: "Locataire",
  proprietaire: "Propriétaire",
  admin: "Administrateur",
  super_admin: "Super-administrateur",
};

function CorbeillePage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/super-admin/corbeille" });
  const type: CorbeilleType = search.type ?? "utilisateurs";
  const [query, setQuery] = useState(search.q ?? "");

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const setType = (next: CorbeilleType) =>
    void navigate({
      search: () => ({ type: next === "utilisateurs" ? undefined : next }),
    });

  const setPage = (page: number) =>
    void navigate({ search: (previous) => ({ ...previous, page: page > 1 ? page : undefined }) });

  return (
    <>
      <PageHeader
        title="Corbeille"
        description="Les comptes, matériels et catégories supprimés restent restaurables jusqu’à la fin de la durée de conservation, puis la purge automatique les supprime définitivement. L’historique des achats n’est jamais perdu."
      />

      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="inline-flex rounded-lg border bg-card p-1" role="tablist" aria-label="Type d’élément">
          <Button
            role="tab"
            aria-selected={type === "utilisateurs"}
            variant={type === "utilisateurs" ? "default" : "ghost"}
            size="sm"
            onClick={() => setType("utilisateurs")}
          >
            <Users aria-hidden /> Comptes
          </Button>
          <Button
            role="tab"
            aria-selected={type === "materiels"}
            variant={type === "materiels" ? "default" : "ghost"}
            size="sm"
            onClick={() => setType("materiels")}
          >
            <Package aria-hidden /> Matériels
          </Button>
          <Button
            role="tab"
            aria-selected={type === "categories"}
            variant={type === "categories" ? "default" : "ghost"}
            size="sm"
            onClick={() => setType("categories")}
          >
            <Tag aria-hidden /> Catégories
          </Button>
        </div>
        <div className="relative w-full sm:max-w-sm">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={
              type === "materiels"
                ? "Nom du matériel…"
                : type === "categories"
                  ? "Nom de la catégorie…"
                  : "Nom ou e-mail…"
            }
            aria-label="Rechercher dans la corbeille"
            className="pl-9"
          />
        </div>
      </div>

      {type === "utilisateurs" ? (
        <UtilisateursList q={search.q} page={search.page} onPage={setPage} />
      ) : type === "materiels" ? (
        <MaterielsList q={search.q} page={search.page} onPage={setPage} />
      ) : (
        <CategoriesList q={search.q} page={search.page} onPage={setPage} />
      )}
    </>
  );
}

function ListSkeleton() {
  return (
    <div className="space-y-3">
      {Array.from({ length: 4 }, (_, index) => (
        <Skeleton key={index} className="h-24 rounded-xl" />
      ))}
    </div>
  );
}

function RetentionNote({ days }: { days: number }) {
  return (
    <p className="mb-4 flex items-center gap-2 text-sm text-muted-foreground">
      <History className="size-4 shrink-0" aria-hidden />
      Durée de conservation : {formatRetention(days)}.
    </p>
  );
}

/** Indique qui a supprimé l'élément : repère les suppressions faites par erreur. */
function SupprimePar({ value }: { value: SupprimePar | null }) {
  if (!value) return null;
  const roles: Record<string, string> = {
    locataire: "locataire",
    proprietaire: "propriétaire",
    admin: "administrateur",
    super_admin: "super-administrateur",
  };

  return (
    <p className="mt-1 text-xs font-semibold text-foreground">
      Supprimé par {value.nom}
      {value.role ? ` (${roles[value.role] ?? value.role})` : ""}
    </p>
  );
}

type Pending<T> = { item: T; action: "restaurer" | "purger" } | null;

function UtilisateursList({
  q,
  page,
  onPage,
}: {
  q: string | undefined;
  page: number | undefined;
  onPage: (page: number) => void;
}) {
  const list = useCorbeilleUtilisateurs({ type: "utilisateurs", q, page });
  const act = useCorbeilleAction("utilisateurs");
  const [pending, setPending] = useState<Pending<CorbeilleUtilisateur>>(null);

  const confirm = () => {
    if (!pending) return;
    act.mutate(
      { id: pending.item.id, action: pending.action },
      {
        onSuccess: (res) => toast.success(res.message),
        onError: (error) => toast.error(errorMessage(error)),
        onSettled: () => setPending(null),
      },
    );
  };

  if (list.isPending) return <ListSkeleton />;
  if (list.isError) return <ErrorState error={list.error} onRetry={() => void list.refetch()} />;
  if (list.data.data.length === 0) {
    return (
      <EmptyState
        icon={Trash2}
        title="Aucun compte dans la corbeille"
        description="Les comptes supprimés apparaîtront ici et resteront restaurables."
      />
    );
  }

  return (
    <>
      <RetentionNote days={list.data.retention_days} />
      <ul className="space-y-3" aria-label="Comptes dans la corbeille">
        {list.data.data.map((account) => (
          <li key={account.id}>
            <article className="grid gap-3 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="truncate font-bold">{account.name}</h2>
                  {account.role && (
                    <Badge variant="secondary">{ROLE_LABELS[account.role] ?? account.role}</Badge>
                  )}
                  {account.anonymise && <Badge variant="outline">Anonymisé</Badge>}
                </div>
                <p className="mt-1 truncate text-sm text-muted-foreground">{account.email}</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Supprimé le {formatDate(account.deleted_at)} · suppression définitive le{" "}
                  {formatDate(account.purge_le)} · {account.locations_count} location(s) ·{" "}
                  {account.materiels_count} matériel(s)
                </p>
                <SupprimePar value={account.supprime_par} />
                {account.locations_count + account.materiels_count > 0 && !account.anonymise && (
                  <p className="mt-1 text-xs text-muted-foreground">
                    Ce compte a un historique : à la purge, ses données personnelles seront
                    anonymisées mais les achats resteront consultables.
                  </p>
                )}
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={account.anonymise}
                  onClick={() => setPending({ item: account, action: "restaurer" })}
                >
                  <RotateCcw aria-hidden /> Restaurer
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="text-destructive hover:text-destructive"
                  disabled={account.anonymise}
                  onClick={() => setPending({ item: account, action: "purger" })}
                >
                  <Trash2 aria-hidden /> Supprimer définitivement
                </Button>
              </div>
            </article>
          </li>
        ))}
      </ul>
      <PaginationBar meta={list.data.meta} onPage={onPage} />
      <ConfirmDialog
        open={!!pending}
        onOpenChange={(open) => !open && setPending(null)}
        title={
          pending?.action === "purger"
            ? "Supprimer définitivement ce compte ?"
            : "Restaurer ce compte ?"
        }
        description={
          pending?.action === "purger"
            ? `${pending.item.name} sera supprimé pour de bon. S’il a un historique de locations, il sera anonymisé pour conserver la comptabilité. Cette action est irréversible.`
            : pending
              ? `${pending.item.name} retrouvera son accès, ainsi que les matériels supprimés avec lui.`
              : undefined
        }
        confirmLabel={pending?.action === "purger" ? "Supprimer définitivement" : "Restaurer"}
        destructive={pending?.action === "purger"}
        loading={act.isPending}
        onConfirm={confirm}
      />
    </>
  );
}

function MaterielsList({
  q,
  page,
  onPage,
}: {
  q: string | undefined;
  page: number | undefined;
  onPage: (page: number) => void;
}) {
  const list = useCorbeilleMateriels({ type: "materiels", q, page });
  const act = useCorbeilleAction("materiels");
  const [pending, setPending] = useState<Pending<CorbeilleMateriel>>(null);

  const confirm = () => {
    if (!pending) return;
    act.mutate(
      { id: pending.item.id, action: pending.action },
      {
        onSuccess: (res) => toast.success(res.message),
        onError: (error) => toast.error(errorMessage(error)),
        onSettled: () => setPending(null),
      },
    );
  };

  if (list.isPending) return <ListSkeleton />;
  if (list.isError) return <ErrorState error={list.error} onRetry={() => void list.refetch()} />;
  if (list.data.data.length === 0) {
    return (
      <EmptyState
        icon={Trash2}
        title="Aucun matériel dans la corbeille"
        description="Les matériels supprimés apparaîtront ici et resteront restaurables."
      />
    );
  }

  return (
    <>
      <RetentionNote days={list.data.retention_days} />
      <ul className="space-y-3" aria-label="Matériels dans la corbeille">
        {list.data.data.map((materiel) => (
          <li key={materiel.id}>
            <article className="grid gap-3 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="truncate font-bold">{materiel.nom}</h2>
                  {materiel.conserve_pour_historique && (
                    <Badge variant="outline">Conservé pour l’historique</Badge>
                  )}
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                  {formatFCFA(materiel.prix_journalier)} / jour · propriétaire :{" "}
                  {materiel.proprietaire?.name ?? "—"}
                  {materiel.proprietaire?.dans_la_corbeille ? " (compte dans la corbeille)" : ""}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Supprimé le {formatDate(materiel.deleted_at)} · {materiel.locations_count}{" "}
                  location(s)
                  {materiel.conserve_pour_historique
                    ? " · ne sera jamais détruit : l’historique des achats le référence"
                    : ` · suppression définitive le ${formatDate(materiel.purge_le)}`}
                </p>
                <SupprimePar value={materiel.supprime_par} />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={materiel.proprietaire?.dans_la_corbeille}
                  title={
                    materiel.proprietaire?.dans_la_corbeille
                      ? "Restaurez d’abord le compte du propriétaire."
                      : undefined
                  }
                  onClick={() => setPending({ item: materiel, action: "restaurer" })}
                >
                  <RotateCcw aria-hidden /> Restaurer
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="text-destructive hover:text-destructive"
                  disabled={materiel.conserve_pour_historique}
                  onClick={() => setPending({ item: materiel, action: "purger" })}
                >
                  <Trash2 aria-hidden /> Supprimer définitivement
                </Button>
              </div>
            </article>
          </li>
        ))}
      </ul>
      <PaginationBar meta={list.data.meta} onPage={onPage} />
      <ConfirmDialog
        open={!!pending}
        onOpenChange={(open) => !open && setPending(null)}
        title={
          pending?.action === "purger"
            ? "Supprimer définitivement ce matériel ?"
            : "Restaurer ce matériel ?"
        }
        description={
          pending?.action === "purger"
            ? `« ${pending.item.nom} » et ses photos seront supprimés pour de bon. Cette action est irréversible.`
            : pending
              ? `« ${pending.item.nom} » redeviendra visible pour son propriétaire.`
              : undefined
        }
        confirmLabel={pending?.action === "purger" ? "Supprimer définitivement" : "Restaurer"}
        destructive={pending?.action === "purger"}
        loading={act.isPending}
        onConfirm={confirm}
      />
    </>
  );
}

function CategoriesList({
  q,
  page,
  onPage,
}: {
  q: string | undefined;
  page: number | undefined;
  onPage: (page: number) => void;
}) {
  const list = useCorbeilleCategories({ type: "categories", q, page });
  const act = useCorbeilleAction("categories");
  const [pending, setPending] = useState<Pending<CorbeilleCategorie>>(null);

  const confirm = () => {
    if (!pending) return;
    act.mutate(
      { id: pending.item.id, action: pending.action },
      {
        onSuccess: (res) => toast.success(res.message),
        onError: (error) => toast.error(errorMessage(error)),
        onSettled: () => setPending(null),
      },
    );
  };

  if (list.isPending) return <ListSkeleton />;
  if (list.isError) return <ErrorState error={list.error} onRetry={() => void list.refetch()} />;
  if (list.data.data.length === 0) {
    return (
      <EmptyState
        icon={Trash2}
        title="Aucune catégorie dans la corbeille"
        description="Les catégories supprimées par un administrateur apparaîtront ici et resteront restaurables."
      />
    );
  }

  return (
    <>
      <RetentionNote days={list.data.retention_days} />
      <ul className="space-y-3" aria-label="Catégories dans la corbeille">
        {list.data.data.map((categorie) => (
          <li key={categorie.id}>
            <article className="grid gap-3 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
              <div className="min-w-0">
                <h2 className="truncate font-bold">{categorie.name}</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                  Supprimée le {formatDate(categorie.deleted_at)} · suppression définitive le{" "}
                  {formatDate(categorie.purge_le)}
                </p>
                <SupprimePar value={categorie.supprime_par} />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPending({ item: categorie, action: "restaurer" })}
                >
                  <RotateCcw aria-hidden /> Restaurer
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="text-destructive hover:text-destructive"
                  onClick={() => setPending({ item: categorie, action: "purger" })}
                >
                  <Trash2 aria-hidden /> Supprimer définitivement
                </Button>
              </div>
            </article>
          </li>
        ))}
      </ul>
      <PaginationBar meta={list.data.meta} onPage={onPage} />
      <ConfirmDialog
        open={!!pending}
        onOpenChange={(open) => !open && setPending(null)}
        title={
          pending?.action === "purger"
            ? "Supprimer définitivement cette catégorie ?"
            : "Restaurer cette catégorie ?"
        }
        description={
          pending?.action === "purger"
            ? `La catégorie « ${pending.item.name} » sera supprimée pour de bon. Cette action est irréversible.`
            : pending
              ? `La catégorie « ${pending.item.name} » redeviendra disponible pour les propriétaires.`
              : undefined
        }
        confirmLabel={pending?.action === "purger" ? "Supprimer définitivement" : "Restaurer"}
        destructive={pending?.action === "purger"}
        loading={act.isPending}
        onConfirm={confirm}
      />
    </>
  );
}
