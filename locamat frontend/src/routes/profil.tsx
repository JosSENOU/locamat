import { createFileRoute } from "@tanstack/react-router";
import { useForm } from "react-hook-form";
import { useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";
import { ShieldCheck, Trash2 } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { profileSchema, type ProfileValues } from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { ApiError, errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import type { User } from "@/types/api";
import { RequireRole } from "@/components/RequireRole";
import { Field, fieldAria } from "@/components/FormField";
import { LoadingButton } from "@/components/SubmitButton";
import { PhotoUpload } from "@/components/PhotoUpload";
import { ProfileAvatar } from "@/components/ProfileAvatar";
import { PageHeader } from "@/components/States";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

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
};

function ProfilPage() {
  const { user, updateProfile } = useAuth();
  const [photo, setPhoto] = useState<File | null>(null);
  const [photoError, setPhotoError] = useState<string | null>(null);
  const [removePhoto, setRemovePhoto] = useState(false);
  const form = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: { name: user?.name ?? "", telephone: user?.telephone ?? "" },
  });
  const { errors, isSubmitting, isDirty } = form.formState;

  if (!user) return null;
  const canEditPhoto = user.role !== "admin";

  const onSubmit = form.handleSubmit(async (v) => {
    try {
      const u = await updateProfile(v, photo, removePhoto);
      form.reset({ name: u.name, telephone: u.telephone ?? "" });
      setPhoto(null);
      setPhotoError(null);
      setRemovePhoto(false);
      toast.success("Profil mis à jour.");
    } catch (e) {
      applyServerErrors(e, form.setError, ["name", "telephone"]);
      if (e instanceof ApiError && e.errors.photo?.[0]) setPhotoError(e.errors.photo[0]);
      else toast.error(errorMessage(e));
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
            {user.role === "admin" && <ShieldCheck className="size-3.5 text-primary" aria-hidden />}
            {ROLE_LABELS[user.role]}
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
            <Field id="telephone" label="Téléphone" optional error={errors.telephone?.message}>
              <Input
                type="tel"
                autoComplete="tel"
                {...fieldAria("telephone", errors.telephone?.message)}
                {...form.register("telephone")}
              />
            </Field>
            <Field id="email" label="Adresse e-mail" hint="Non modifiable.">
              <Input id="email" value={user.email} readOnly disabled />
            </Field>
            <Field id="role" label="Type de compte" hint="Non modifiable.">
              <Input id="role" value={ROLE_LABELS[user.role]} readOnly disabled />
            </Field>
          </div>

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
      </section>
    </div>
  );
}
