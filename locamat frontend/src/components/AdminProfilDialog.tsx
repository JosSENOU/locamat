import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { MapPin, Pencil } from "lucide-react";
import { toast } from "sonner";
import { useUpdateAdminUserProfil } from "@/hooks/use-admin";
import { applyServerErrors } from "@/lib/form-errors";
import { adminProfilSchemaFor, type AdminProfilValues } from "@/lib/schemas";
import { PhoneField } from "@/components/PhoneField";
import type { AdminUser } from "@/types/api";
import { Field, fieldAria } from "./FormField";
import { LoadingButton } from "./SubmitButton";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "./ui/dialog";

const FIELDS = ["name", "telephone", "ville", "quartier", "adresse"] as const;

/**
 * Permet à un administrateur de modifier le profil d'un compte déjà inscrit
 * (nom, téléphone) et, pour un propriétaire, d'ajouter sa localisation.
 */
export function AdminProfilDialog({
  account,
  onClose,
}: {
  account: AdminUser | null;
  onClose: () => void;
}) {
  return (
    <Dialog open={!!account} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto rounded-2xl sm:max-w-lg">
        {account && <ProfilForm key={account.id} account={account} onClose={onClose} />}
      </DialogContent>
    </Dialog>
  );
}

function ProfilForm({ account, onClose }: { account: AdminUser; onClose: () => void }) {
  const isOwner = (account.roles ?? [account.role]).includes("proprietaire");
  const save = useUpdateAdminUserProfil();
  const form = useForm<AdminProfilValues>({
    resolver: zodResolver(adminProfilSchemaFor(isOwner)),
    defaultValues: {
      name: account.name,
      telephone: account.telephone ?? "",
      ville: account.ville ?? "",
      quartier: account.quartier ?? "",
      adresse: account.adresse ?? "",
    },
  });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit((values) => {
    const { ville, quartier, adresse, ...base } = values;
    save.mutate(
      { id: account.id, ...(isOwner ? { ...base, ville, quartier, adresse } : base) },
      {
        onSuccess: () => {
          toast.success(`Le profil de ${account.name} a été mis à jour.`);
          onClose();
        },
        onError: (error) => applyServerErrors(error, form.setError, FIELDS),
      },
    );
  });

  return (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          <Pencil className="size-5 text-primary" aria-hidden />
          Modifier le profil
        </DialogTitle>
        <DialogDescription>
          {account.email}
          {isOwner ? " · Propriétaire" : ""}
        </DialogDescription>
      </DialogHeader>
      <form onSubmit={onSubmit} noValidate className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field id="admin-name" label="Nom complet" error={errors.name?.message}>
            <Input
              autoComplete="off"
              {...fieldAria("admin-name", errors.name?.message)}
              {...form.register("name")}
            />
          </Field>
          <Field
            id="admin-telephone"
            label="Téléphone"
            optional={!isOwner}
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
                  inputProps={fieldAria("admin-telephone", errors.telephone?.message)}
                />
              )}
            />
          </Field>
        </div>

        {isOwner && (
          <div className="space-y-4 rounded-2xl border border-primary/20 bg-primary-soft/60 p-4">
            <div>
              <h3 className="flex items-center gap-2 text-sm font-bold">
                <span className="grid size-6 place-items-center rounded-md bg-primary text-primary-foreground">
                  <MapPin className="size-3.5" aria-hidden />
                </span>
                Localisation du matériel
              </h3>
              <p className="mt-1 text-xs text-muted-foreground">
                Ville et quartier apparaissent sur les annonces. L'adresse précise n'est partagée
                qu'avec le locataire dont la demande est confirmée.
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field id="admin-ville" label="Ville" error={errors.ville?.message}>
                <Input
                  placeholder="Cotonou"
                  {...fieldAria("admin-ville", errors.ville?.message)}
                  {...form.register("ville")}
                />
              </Field>
              <Field id="admin-quartier" label="Quartier" optional error={errors.quartier?.message}>
                <Input
                  placeholder="Haie Vive"
                  {...fieldAria("admin-quartier", errors.quartier?.message)}
                  {...form.register("quartier")}
                />
              </Field>
            </div>
            <Field
              id="admin-adresse"
              label="Adresse ou repère précis"
              optional
              error={errors.adresse?.message}
            >
              <Input
                placeholder="Ex. : en face de la pharmacie, rue 123"
                {...fieldAria("admin-adresse", errors.adresse?.message)}
                {...form.register("adresse")}
              />
            </Field>
          </div>
        )}

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="outline" onClick={onClose}>
            Annuler
          </Button>
          <LoadingButton type="submit" loading={save.isPending}>
            Enregistrer
          </LoadingButton>
        </div>
      </form>
    </>
  );
}
