import { useEffect, useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { Search, ShieldCheck, Users } from "lucide-react";
import { toast } from "sonner";
import { useAdminUsers, useUpdateAdminUserRole, useUpdateAdminUserStatus } from "@/hooks/use-admin";
import { useAuth } from "@/hooks/use-auth";
import { errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import type { AdminUser, Role } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { PaginationBar } from "@/components/PaginationBar";
import { ProfileAvatar } from "@/components/ProfileAvatar";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";

type UserSearch = { q?: string; role?: Role; is_active?: "0" | "1"; page?: number };
type UserRoleFilter = Role | "tous";
const ROLES = ["locataire", "proprietaire", "admin"] as const satisfies readonly Role[];

const ROLE_LABELS: Record<Role, string> = {
  locataire: "Locataire",
  proprietaire: "Propriétaire",
  admin: "Administrateur",
};

function isRole(value: string): value is Role {
  return ROLES.some((role) => role === value);
}

export const Route = createFileRoute("/admin/utilisateurs")({
  validateSearch: (search: Record<string, unknown>): UserSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    role: typeof search.role === "string" && isRole(search.role) ? search.role : undefined,
    is_active: search.is_active === "0" || search.is_active === "1" ? search.is_active : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Comptes utilisateurs", "Consultez et gérez les rôles et l’accès aux comptes LocaMat."),
  component: AdminUsersPage,
});

function AdminUsersPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/admin/utilisateurs" });
  const { user: actor } = useAuth();
  const [query, setQuery] = useState(search.q ?? "");
  const users = useAdminUsers(search);
  const updateRole = useUpdateAdminUserRole();
  const updateStatus = useUpdateAdminUserStatus();

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const filter = (patch: Partial<UserSearch>) =>
    void navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }) });

  const changeRole = (account: AdminUser, role: Role) => {
    updateRole.mutate(
      { id: account.id, role },
      {
        onSuccess: () => toast.success(`Le rôle de ${account.name} a été mis à jour.`),
        onError: (error) => toast.error(errorMessage(error)),
      },
    );
  };

  const changeStatus = (account: AdminUser, is_active: boolean) => {
    updateStatus.mutate(
      { id: account.id, is_active },
      {
        onSuccess: () =>
          toast.success(
            is_active
              ? `Le compte de ${account.name} est réactivé.`
              : `Le compte de ${account.name} est désactivé.`,
          ),
        onError: (error) => toast.error(errorMessage(error)),
      },
    );
  };

  return (
    <>
      <PageHeader
        title="Comptes utilisateurs"
        description="Recherchez les membres, ajustez leurs rôles et désactivez les comptes si nécessaire."
      />
      <div className="mb-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_200px_190px]">
        <div className="relative">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Nom, e-mail ou téléphone…"
            aria-label="Rechercher un compte"
            className="pl-9"
          />
        </div>
        <Select
          value={search.role ?? "tous"}
          onValueChange={(value) =>
            filter({ role: value === "tous" || !isRole(value) ? undefined : value })
          }
        >
          <SelectTrigger aria-label="Filtrer par rôle">
            <SelectValue placeholder="Tous les rôles" />
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
        <Select
          value={search.is_active ?? "tous"}
          onValueChange={(value) =>
            filter({ is_active: value === "1" || value === "0" ? value : undefined })
          }
        >
          <SelectTrigger aria-label="Filtrer par statut du compte">
            <SelectValue placeholder="Tous les statuts" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="tous">Tous les statuts</SelectItem>
            <SelectItem value="1">Actifs</SelectItem>
            <SelectItem value="0">Désactivés</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {users.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }, (_, index) => (
            <Skeleton key={index} className="h-28 rounded-xl" />
          ))}
        </div>
      ) : users.isError ? (
        <ErrorState error={users.error} onRetry={() => void users.refetch()} />
      ) : users.data.data.length === 0 ? (
        <EmptyState
          icon={Users}
          title="Aucun compte trouvé"
          description="Essayez avec un autre nom, rôle ou statut."
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
          <ul className="space-y-3" aria-label="Comptes utilisateurs">
            {users.data.data.map((account) => {
              const isSelf = account.id === actor?.id;
              return (
                <li key={account.id}>
                  <article className="grid gap-4 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_190px_180px] sm:items-center">
                    <div className="flex min-w-0 items-center gap-3">
                      <ProfileAvatar
                        name={account.name}
                        photo={account.photo}
                        className="size-10 text-xs"
                      />
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <h2 className="truncate font-bold">{account.name}</h2>
                          {isSelf && <Badge variant="outline">Votre compte</Badge>}
                          {account.role === "admin" && (
                            <ShieldCheck
                              className="size-4 text-primary"
                              aria-label="Administrateur"
                            />
                          )}
                        </div>
                        <a
                          className="mt-1 block truncate text-sm text-muted-foreground hover:text-primary"
                          href={`mailto:${account.email}`}
                        >
                          {account.email}
                        </a>
                        <p className="mt-1 text-xs text-muted-foreground">
                          {account.telephone || "Téléphone non renseigné"}
                          {account.materiels_count
                            ? ` · ${account.materiels_count} matériel${account.materiels_count > 1 ? "s" : ""}`
                            : ""}
                        </p>
                      </div>
                    </div>
                    <div className="space-y-1.5">
                      <p className="text-xs font-semibold text-muted-foreground">Rôle du compte</p>
                      <Select
                        value={account.role}
                        onValueChange={(value) => {
                          if (isRole(value)) changeRole(account, value);
                        }}
                        disabled={isSelf || updateRole.isPending}
                      >
                        <SelectTrigger aria-label={`Rôle de ${account.name}`}>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {ROLES.map((role) => (
                            <SelectItem key={role} value={role}>
                              {ROLE_LABELS[role]}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <label className="flex items-center justify-between gap-3 border-t pt-3 text-sm sm:border-0 sm:pt-0">
                      <span>
                        <span className="block font-semibold">
                          {account.is_active ? "Compte actif" : "Désactivé"}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {isSelf ? "Votre session est protégée" : "Les accès sont révoqués"}
                        </span>
                      </span>
                      <Switch
                        checked={account.is_active}
                        onCheckedChange={(active) => changeStatus(account, active)}
                        disabled={isSelf || updateStatus.isPending}
                        aria-label={`${account.is_active ? "Désactiver" : "Réactiver"} le compte de ${account.name}`}
                      />
                    </label>
                  </article>
                </li>
              );
            })}
          </ul>
          <PaginationBar
            meta={users.data.meta}
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
