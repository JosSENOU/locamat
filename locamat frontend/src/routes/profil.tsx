import { createFileRoute } from "@tanstack/react-router";
import { Controller, useForm } from "react-hook-form";
import { useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";
import { MapPin, ShieldCheck, Trash2 } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import {
  becomeOwnerSchema,
  changePasswordSchema,
  profileSchemaFor,
  type BecomeOwnerValues,
  type ChangePasswordValues,
  type ProfileValues,
} from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { ApiError, api, errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import type { User } from "@/types/api";
import { userHasRole } from "@/lib/constants";
import { RequireRole } from "@/components/RequireRole";
import { Field, fieldAria } from "@/components/FormField";
import { LoadingButton } from "@/components/SubmitButton";
import { PhotoUpload } from "@/components/PhotoUpload";
import { ProfileAvatar } from "@/components/ProfileAvatar";
import { PageHeader } from "@/components/States";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { PasswordInput } from "@/components/PasswordInput";
import { PhoneField } from "@/components/PhoneField";
import { PasswordRules } from "@/components/PasswordRules";

export const Route = createFileRoute("/profil")({
  head: () => seo("Mon profil", "Modifiez votre nom et votre numéro de téléphone."),
  component: () => (
    <RequireRole>
      <ProfilPage />
    </RequireRole>
  ),
});

const ROLE_LABELS: Record<User["role"], string> = {
  locataire: "Locataire",
  proprietaire: "Propriétaire",
  admin: "Administrateur",
  super_admin: "Super-administrateur",
};

function ProfilPage() {
  const { user, updateProfile, becomeOwner } = useAuth();
  const [photo, setPhoto] = useState<File | null>(null);
  const [photoError, setPhotoError] = useState<string | null>(null);
  const [removePhoto, setRemovePhoto] = useState(false);
  const isOwner = userHasRole(user, "proprietaire");
  const form = useForm<ProfileValues>({
    resolver: zodResolver(profileSchemaFor(isOwner)),
    defaultValues: {
      name: user?.name ?? "",
      telephone: user?.telephone ?? "",
      ...(isOwner
        ? {
            ville: user?.ville ?? "",
            quartier: user?.quartier ?? "",
            adresse: user?.adresse ?? "",
          }
        : {}),
    },
  });
  const passwordForm = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: { current_password: "", password: "", password_confirmation: "" },
  });
  const ownerForm = useForm<BecomeOwnerValues>({
    resolver: zodResolver(becomeOwnerSchema),
    defaultValues: { telephone: "", ville: "", quartier: "", adresse: "" },
  });
  const { errors, isSubmitting, isDirty } = form.formState;
  const passwordErrors = passwordForm.formState.errors;
  const ownerErrors = ownerForm.formState.errors;

  if (!user) return null;
  const userRoles = user.roles?.length ? user.roles : [user.role];
  const rolesLabel = userRoles.map((role) => ROLE_LABELS[role]).join(", ");
  const canEditPhoto = !userHasRole(user, "admin");

  const onSubmit = form.handleSubmit(async (v) => {
    try {
      const u = await updateProfile(v, photo, removePhoto);
      form.reset({
        name: u.name,
        telephone: u.telephone ?? "",
        ...(isOwner
          ? { ville: u.ville ?? "", quartier: u.quartier ?? "", adresse: u.adresse ?? "" }
          : {}),
      });
      setPhoto(null);
      setPhotoError(null);
      setRemovePhoto(false);
      toast.success("Profil mis à jour.");
    } catch (e) {
      applyServerErrors(e, form.setError, ["name", "telephone", "ville", "quartier", "adresse"]);
      if (e instanceof ApiError && e.errors.photo?.[0]) setPhotoError(e.errors.photo[0]);
      else toast.error(errorMessage(e));
    }
  });

  const onChangePassword = passwordForm.handleSubmit(async (values) => {
    try {
      const result = await api<{ message: string }>("/change-password", {
        method: "POST",
        body: values,
      });
      passwordForm.reset();
      toast.success(result.message);
    } catch (error) {
      applyServerErrors(error, passwordForm.setError, [
        "current_password",
        "password",
        "password_confirmation",
      ]);
    }
  });

  const onBecomeOwner = ownerForm.handleSubmit(async (values) => {
    try {
      const updatedUser = await becomeOwner(values);
      form.reset({
        name: updatedUser.name,
        telephone: updatedUser.telephone ?? "",
        ville: updatedUser.ville ?? "",
        quartier: updatedUser.quartier ?? "",
        adresse: updatedUser.adresse ?? "",
      });
      toast.success(
        "Votre compte propriétaire est activé. Vous pouvez maintenant publier du matériel.",
      );
    } catch (error) {
      applyServerErrors(error, ownerForm.setError, ["telephone", "ville", "quartier", "adresse"]);
      if (!(error instanceof ApiError && error.hasFieldErrors)) toast.error(errorMessage(error));
    }
  });

  return (
    <div className="container-page max-w-3xl py-8 sm:py-12">
      <PageHeader
        title="Mon profil"
        description="Gérez les informations et la présentation de votre compte LocaMat."
      />
      <section className="overflow-hidden rounded-3xl border bg-card shadow-card">
        <div className="relative flex flex-col items-center bg-gradient-to-b from-primary/10 via-primary/5 to-transparent px-6 pb-8 pt-8 text-center sm:pb-10 sm:pt-10">
          <div
            aria-hidden
            className="absolute left-1/2 top-0 size-48 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
          />
          {canEditPhoto ? (
            <PhotoUpload
              id="profile-photo"
              variant="avatar"
              file={photo}
              existing={removePhoto ? null : user.photo}
              onChange={(file) => {
                setPhoto(file);
                setPhotoError(null);
                if (file) setRemovePhoto(false);
              }}
              error={photoError ?? undefined}
              onError={setPhotoError}
            />
          ) : (
            <ProfileAvatar name={user.name} photo={null} className="size-32 text-3xl sm:size-36" />
          )}
          <h2 className="mt-5 max-w-full truncate text-xl font-extrabold sm:text-2xl">
            {form.watch("name") || user.name}
          </h2>
          <span className="mt-2 inline-flex items-center gap-1.5 rounded-full border bg-background/80 px-3 py-1 text-xs font-semibold text-muted-foreground shadow-sm">
            {userHasRole(user, "admin") && (
              <ShieldCheck className="size-3.5 text-primary" aria-hidden />
            )}
            {rolesLabel}
          </span>
          <p className="mt-2 max-w-md text-sm text-muted-foreground">
            {canEditPhoto
              ? "Une photo centrée aide les autres membres à vous reconnaître."
              : "Les paramètres de photo de profil ne sont pas disponibles pour les administrateurs."}
          </p>
          {canEditPhoto && user.photo && !photo && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="mt-2 text-destructive hover:text-destructive"
              onClick={() => {
                setRemovePhoto(!removePhoto);
                setPhotoError(null);
              }}
            >
              <Trash2 aria-hidden />
              {removePhoto ? "Conserver la photo actuelle" : "Supprimer la photo"}
            </Button>
          )}
        </div>

        <Separator />

        <form onSubmit={onSubmit} noValidate className="space-y-6 p-5 sm:p-8">
          <div>
            <h3 className="font-bold">Informations personnelles</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Ces informations sont partagées avec vos contacts une fois une location confirmée.
            </p>
          </div>

          <div className="grid gap-5 sm:grid-cols-2">
            <Field id="name" label="Nom complet" error={errors.name?.message}>
              <Input
                autoComplete="name"
                {...fieldAria("name", errors.name?.message)}
                {...form.register("name")}
              />
            </Field>
            <Field
              id="telephone"
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
                    inputProps={fieldAria("telephone", errors.telephone?.message)}
                  />
                )}
              />
            </Field>
            <Field id="email" label="Adresse e-mail" hint="Non modifiable.">
              <Input id="email" value={user.email} readOnly disabled />
            </Field>
            <Field id="role" label="Type de compte" hint="Non modifiable.">
              <Input id="role" value={rolesLabel} readOnly disabled />
            </Field>
          </div>

          {isOwner && (
            <div className="space-y-5 rounded-2xl border border-primary/20 bg-primary-soft/60 p-5">
              <div>
                <h3 className="flex items-center gap-2 font-bold">
                  <span className="grid size-6 place-items-center rounded-md bg-primary text-primary-foreground">
                    <MapPin className="size-3.5" aria-hidden />
                  </span>
                  Localisation de mon matériel
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                  Ville et quartier sont affichés sur vos annonces et au locataire qui prépare sa
                  demande. L'adresse précise n'est partagée qu'une fois sa demande confirmée.
                </p>
              </div>
              <div className="grid gap-5 sm:grid-cols-2">
                <Field id="ville" label="Ville" error={errors.ville?.message}>
                  <Input
                    autoComplete="address-level2"
                    {...fieldAria("ville", errors.ville?.message)}
                    {...form.register("ville")}
                  />
                </Field>
                <Field id="quartier" label="Quartier" optional error={errors.quartier?.message}>
                  <Input
                    {...fieldAria("quartier", errors.quartier?.message)}
                    {...form.register("quartier")}
                  />
                </Field>
              </div>
              <Field
                id="adresse"
                label="Adresse ou repère précis"
                optional
                error={errors.adresse?.message}
              >
                <Input
                  placeholder="Ex. : en face de la pharmacie, rue 123"
                  {...fieldAria("adresse", errors.adresse?.message)}
                  {...form.register("adresse")}
                />
              </Field>
            </div>
          )}

          <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-muted-foreground">
              Votre adresse e-mail et votre rôle ne peuvent pas être modifiés ici.
            </p>
            <LoadingButton
              type="submit"
              loading={isSubmitting}
              disabled={!isDirty && !photo && !removePhoto}
            >
              Enregistrer les modifications
            </LoadingButton>
          </div>
        </form>

        {userHasRole(user, "locataire") && !isOwner && (
          <>
            <Separator />
            <form onSubmit={onBecomeOwner} noValidate className="space-y-5 p-5 sm:p-8">
              <div>
                <h3 className="font-bold">Devenir propriétaire</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                  Vous conserverez votre accès locataire et pourrez aussi publier du matériel.
                  Indiquez votre téléphone et le lieu de retrait des annonces.
                </p>
              </div>
              <div className="grid gap-5 sm:grid-cols-2">
                <Field
                  id="owner-telephone"
                  label="Téléphone / WhatsApp"
                  error={ownerErrors.telephone?.message}
                >
                  <Controller
                    control={ownerForm.control}
                    name="telephone"
                    render={({ field }) => (
                      <PhoneField
                        value={field.value}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        name={field.name}
                        inputRef={field.ref}
                        inputProps={fieldAria("owner-telephone", ownerErrors.telephone?.message)}
                      />
                    )}
                  />
                </Field>
                <Field id="owner-ville" label="Ville" error={ownerErrors.ville?.message}>
                  <Input id="owner-ville" {...ownerForm.register("ville")} />
                </Field>
                <Field
                  id="owner-quartier"
                  label="Quartier"
                  optional
                  error={ownerErrors.quartier?.message}
                >
                  <Input id="owner-quartier" {...ownerForm.register("quartier")} />
                </Field>
                <Field
                  id="owner-adresse"
                  label="Adresse ou repère précis"
                  optional
                  error={ownerErrors.adresse?.message}
                >
                  <Input id="owner-adresse" {...ownerForm.register("adresse")} />
                </Field>
              </div>
              <LoadingButton type="submit" loading={ownerForm.formState.isSubmitting}>
                Activer mon compte propriétaire
              </LoadingButton>
            </form>
          </>
        )}

        <Separator />
        <form onSubmit={onChangePassword} noValidate className="space-y-5 p-5 sm:p-8">
          <div>
            <h3 className="font-bold">Changer mon mot de passe</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Choisissez un mot de passe d’au moins 8 caractères.
            </p>
          </div>
          <Field
            id="current_password"
            label="Mot de passe actuel"
            error={passwordErrors.current_password?.message}
          >
            <PasswordInput
              autoComplete="current-password"
              {...fieldAria("current_password", passwordErrors.current_password?.message)}
              {...passwordForm.register("current_password")}
            />
          </Field>
          <div className="grid gap-5 sm:grid-cols-2">
            <Field
              id="new-password"
              label="Nouveau mot de passe"
              error={passwordErrors.password?.message}
            >
              <PasswordInput
                autoComplete="new-password"
                {...fieldAria("new-password", passwordErrors.password?.message)}
                {...passwordForm.register("password")}
              />
            </Field>
            <PasswordRules value={passwordForm.watch("password") ?? ""} />
            <Field
              id="password_confirmation"
              label="Confirmer le nouveau mot de passe"
              error={passwordErrors.password_confirmation?.message}
            >
              <PasswordInput
                autoComplete="new-password"
                {...fieldAria(
                  "password_confirmation",
                  passwordErrors.password_confirmation?.message,
                )}
                {...passwordForm.register("password_confirmation")}
              />
            </Field>
          </div>
          <div className="flex justify-end">
            <LoadingButton type="submit" loading={passwordForm.formState.isSubmitting}>
              Modifier le mot de passe
            </LoadingButton>
          </div>
        </form>
      </section>
    </div>
  );
}
