import { useEffect, useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Search, ShieldCheck, Trash2, UserCog, UserPlus } from "lucide-react";
import { toast } from "sonner";
import {
  useAdministrateurs,
  useCreateAdministrateur,
  useDeleteUser,
  useToggleAdministrateur,
} from "@/hooks/use-super-admin";
import { useAuth } from "@/hooks/use-auth";
import { errorMessage } from "@/lib/api";
import { applyServerErrors } from "@/lib/form-errors";
import { formatDate } from "@/lib/format";
import { administrateurSchema, type AdministrateurValues } from "@/lib/schemas";
import { seo } from "@/lib/seo";
import type { AdminUser } from "@/types/api";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { Field, fieldAria } from "@/components/FormField";
import { PasswordInput } from "@/components/PasswordInput";
import { PhoneField } from "@/components/PhoneField";
import { PasswordRules } from "@/components/PasswordRules";
import { PaginationBar } from "@/components/PaginationBar";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { LoadingButton } from "@/components/SubmitButton";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";

type AdministrateursSearch = { q?: string; page?: number };

export const Route = createFileRoute("/super-admin/administrateurs")({
  validateSearch: (search: Record<string, unknown>): AdministrateursSearch => ({
    q: typeof search.q === "string" && search.q.length > 0 ? search.q : undefined,
    page: Number(search.page) > 1 ? Number(search.page) : undefined,
  }),
  head: () =>
    seo("Administrateurs", "Créez et gérez les comptes administrateurs de la plateforme LocaMat."),
  component: AdministrateursPage,
});

function AdministrateursPage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/super-admin/administrateurs" });
  const { user: actor } = useAuth();
  const [query, setQuery] = useState(search.q ?? "");
  const [creating, setCreating] = useState(false);
  const [toDelete, setToDelete] = useState<AdminUser | null>(null);
  const administrateurs = useAdministrateurs(search);
  const toggle = useToggleAdministrateur();
  const remove = useDeleteUser();

  useEffect(() => setQuery(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const q = query.trim() || undefined;
      if (q !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q, page: undefined }) });
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, navigate, search.q]);

  const changeStatus = (account: AdminUser, is_active: boolean) => {
    toggle.mutate(
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

  const confirmDelete = () => {
    if (!toDelete) return;
    remove.mutate(toDelete.id, {
      onSuccess: () => {
        toast.success(`Le compte de ${toDelete.name} a été placé dans la corbeille.`);
        setToDelete(null);
      },
      onError: (error) => {
        toast.error(errorMessage(error));
        setToDelete(null);
      },
    });
  };

  return (
    <>
      <PageHeader
        title="Administrateurs"
        description="Seul le super-administrateur crée, désactive ou supprime les comptes administrateurs. Chaque action est inscrite au journal."
        actions={
          <Button onClick={() => setCreating(true)}>
            <UserPlus aria-hidden /> Nouvel administrateur
          </Button>
        }
      />

      <div className="relative mb-5 max-w-md">
        <Search
          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Nom ou e-mail…"
          aria-label="Rechercher un administrateur"
          className="pl-9"
        />
      </div>

      {administrateurs.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }, (_, index) => (
            <Skeleton key={index} className="h-24 rounded-xl" />
          ))}
        </div>
      ) : administrateurs.isError ? (
        <ErrorState error={administrateurs.error} onRetry={() => void administrateurs.refetch()} />
      ) : administrateurs.data.data.length === 0 ? (
        <EmptyState
          icon={UserCog}
          title="Aucun administrateur trouvé"
          description="Essayez une autre recherche ou créez un nouveau compte administrateur."
        />
      ) : (
        <>
          <ul className="space-y-3" aria-label="Comptes administrateurs">
            {administrateurs.data.data.map((account) => {
              const isSelf = account.id === actor?.id;
              const isSuperAdmin = account.role === "super_admin";
              return (
                <li key={account.id}>
                  <article className="grid gap-4 rounded-xl border bg-card p-4 shadow-card sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-center">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <h2 className="truncate font-bold">{account.name}</h2>
                        <Badge variant={isSuperAdmin ? "default" : "secondary"}>
                          <ShieldCheck className="mr-1 size-3" aria-hidden />
                          {isSuperAdmin ? "Super-administrateur" : "Administrateur"}
                        </Badge>
                        {isSelf && <Badge variant="outline">Votre compte</Badge>}
                      </div>
                      <p className="mt-1 truncate text-sm text-muted-foreground">{account.email}</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {account.telephone || "Téléphone non renseigné"} · créé le{" "}
                        {formatDate(account.created_at)}
                      </p>
                    </div>
                    <label className="flex items-center justify-between gap-3 text-sm">
                      <span>
                        <span className="block font-semibold">
                          {account.is_active ? "Compte actif" : "Désactivé"}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {isSuperAdmin || isSelf
                            ? "Non modifiable ici"
                            : "Les accès sont révoqués"}
                        </span>
                      </span>
                      <Switch
                        checked={account.is_active}
                        onCheckedChange={(active) => changeStatus(account, active)}
                        disabled={isSuperAdmin || isSelf || toggle.isPending}
                        aria-label={`${account.is_active ? "Désactiver" : "Réactiver"} le compte de ${account.name}`}
                      />
                    </label>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="text-destructive hover:text-destructive"
                      onClick={() => setToDelete(account)}
                      disabled={isSuperAdmin || isSelf}
                    >
                      <Trash2 aria-hidden /> Supprimer
                    </Button>
                  </article>
                </li>
              );
            })}
          </ul>
          <PaginationBar
            meta={administrateurs.data.meta}
            onPage={(page) =>
              void navigate({
                search: (previous) => ({ ...previous, page: page > 1 ? page : undefined }),
              })
            }
          />
        </>
      )}

      <CreateAdministrateurDialog open={creating} onClose={() => setCreating(false)} />
      <ConfirmDialog
        open={!!toDelete}
        onOpenChange={(open) => !open && setToDelete(null)}
        title="Supprimer ce compte administrateur ?"
        description={
          toDelete
            ? `${toDelete.name} sera placé dans la corbeille. Son historique d’actions est conservé et vous pourrez le restaurer.`
            : undefined
        }
        confirmLabel="Placer dans la corbeille"
        destructive
        loading={remove.isPending}
        onConfirm={confirmDelete}
      />
    </>
  );
}

const FIELDS = ["name", "email", "telephone", "password", "password_confirmation"] as const;

function CreateAdministrateurDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto rounded-2xl sm:max-w-lg">
        {open && <CreateForm onClose={onClose} />}
      </DialogContent>
    </Dialog>
  );
}

function CreateForm({ onClose }: { onClose: () => void }) {
  const create = useCreateAdministrateur();
  const form = useForm<AdministrateurValues>({
    resolver: zodResolver(administrateurSchema),
    defaultValues: { name: "", email: "", telephone: "", password: "", password_confirmation: "" },
  });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit((values) => {
    create.mutate(values, {
      onSuccess: (account) => {
        toast.success(`Le compte administrateur de ${account.name} a été créé.`);
        onClose();
      },
      onError: (error) => applyServerErrors(error, form.setError, FIELDS),
    });
  });

  return (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          <UserPlus className="size-5 text-primary" aria-hidden />
          Nouvel administrateur
        </DialogTitle>
        <DialogDescription>
          Le compte est actif immédiatement. Communiquez-lui son mot de passe de façon sécurisée.
        </DialogDescription>
      </DialogHeader>
      <form onSubmit={onSubmit} noValidate className="space-y-4">
        <Field id="adm-name" label="Nom complet" error={errors.name?.message}>
          <Input
            autoComplete="off"
            {...fieldAria("adm-name", errors.name?.message)}
            {...form.register("name")}
          />
        </Field>
        <Field id="adm-email" label="Adresse e-mail" error={errors.email?.message}>
          <Input
            type="email"
            autoComplete="off"
            {...fieldAria("adm-email", errors.email?.message)}
            {...form.register("email")}
          />
        </Field>
        <Field
          id="adm-telephone"
          label="Téléphone"
          optional
          error={errors.telephone?.message}
          hint="Choisissez le pays puis saisissez le numéro (Bénin : 01 XX XX XX XX). Vous pouvez aussi coller le numéro complet avec l’indicatif (+…)."
        >
          <Controller
            control={form.control}
            name="telephone"
            render={({ field }) => (
              <PhoneField
                value={field.value}
                onChange={field.onChange}
                onBlur={field.onBlur}
                name={field.name}
                inputRef={field.ref}
                inputProps={fieldAria("adm-telephone", errors.telephone?.message)}
              />
            )}
          />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id="adm-password"
            label="Mot de passe"
            error={errors.password?.message}
          >
            <PasswordInput
              autoComplete="new-password"
              {...fieldAria("adm-password", errors.password?.message)}
              {...form.register("password")}
            />
          </Field>
          <Field
            id="adm-password-confirmation"
            label="Confirmation"
            error={errors.password_confirmation?.message}
          >
            <PasswordInput
              autoComplete="new-password"
              {...fieldAria("adm-password-confirmation", errors.password_confirmation?.message)}
              {...form.register("password_confirmation")}
            />
          </Field>
        </div>
        <PasswordRules value={form.watch("password") ?? ""} minLength={12} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="outline" onClick={onClose}>
            Annuler
          </Button>
          <LoadingButton type="submit" loading={create.isPending}>
            Créer le compte
          </LoadingButton>
        </div>
      </form>
    </>
  );
}
