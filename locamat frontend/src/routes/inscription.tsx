import { createFileRoute, Link } from "@tanstack/react-router";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { MapPin, Package, Search } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { toast } from "sonner";
import { registerSchema, type RegisterValues } from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { seo } from "@/lib/seo";
import { cn } from "@/lib/utils";
import { AuthShell } from "@/components/AuthShell";
import { Field, fieldAria } from "@/components/FormField";
import { PasswordInput } from "@/components/PasswordInput";
import { PhoneField } from "@/components/PhoneField";
import { PasswordRules } from "@/components/PasswordRules";
import { LoadingButton } from "@/components/SubmitButton";
import { Input } from "@/components/ui/input";
import { useNavigate } from "@tanstack/react-router";

export const Route = createFileRoute("/inscription")({
  validateSearch: (
    s: Record<string, unknown>,
  ): { redirect?: string; role?: "locataire" | "proprietaire" } => ({
    redirect: typeof s.redirect === "string" ? s.redirect : undefined,
    role: s.role === "locataire" || s.role === "proprietaire" ? s.role : undefined,
  }),
  head: () =>
    seo("Inscription", "Créez votre compte LocaMat : louez du matériel ou proposez le vôtre."),
  component: Inscription,
});

const ROLES = [
  {
    value: "locataire",
    title: "Je loue du matériel",
    desc: "Trouvez l'équipement pour vos projets.",
    icon: Search,
  },
  {
    value: "proprietaire",
    title: "Je propose mon matériel",
    desc: "Publiez, localisez et gérez vos locations.",
    icon: Package,
  },
] as const;

const FIELDS = [
  "name",
  "email",
  "telephone",
  "role",
  "ville",
  "quartier",
  "adresse",
  "password",
  "password_confirmation",
] as const;

function Inscription() {
  const { redirect, role: initialRole } = Route.useSearch();
  const navigate = useNavigate();
  const { register: signup } = useAuth();
  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: "",
      email: "",
      telephone: "",
      role: initialRole,
      ville: "",
      quartier: "",
      adresse: "",
      password: "",
      password_confirmation: "",
    },
  });
  const { errors, isSubmitting } = form.formState;
  const role = form.watch("role");
  const isOwner = role === "proprietaire";

  const onSubmit = form.handleSubmit(async (v) => {
    try {
      const result = await signup(v);
      toast.success(result.message);
      void navigate({
        to: "/verification-email",
        search: { email: result.email, verified: false },
        replace: true,
      });
    } catch (e) {
      applyServerErrors(e, form.setError, FIELDS);
    }
  });

  return (
    <AuthShell
      variant={role ?? "default"}
      title="Créer un compte"
      subtitle={
        isOwner
          ? "Présentez-vous et indiquez où se trouve votre matériel."
          : role === "locataire"
            ? "Quelques secondes suffisent pour réserver du matériel."
            : "Choisissez d'abord votre profil : le formulaire s'adapte."
      }
    >
      <form onSubmit={onSubmit} noValidate className="space-y-5">
        <fieldset>
          <legend className="mb-2 text-sm font-semibold">Vous êtes…</legend>
          <Controller
            control={form.control}
            name="role"
            render={({ field }) => (
              <div role="radiogroup" className="grid gap-3 sm:grid-cols-2">
                {ROLES.map((r) => {
                  const active = field.value === r.value;
                  return (
                    <button
                      key={r.value}
                      type="button"
                      role="radio"
                      aria-checked={active}
                      onClick={() => {
                        field.onChange(r.value);
                        form.clearErrors();
                      }}
                      className={cn(
                        "flex flex-col items-start gap-2 rounded-xl border-2 bg-card p-4 text-left transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                        active
                          ? "border-primary bg-primary-soft shadow-card"
                          : "hover:border-primary/40",
                      )}
                    >
                      <r.icon
                        className={cn("size-6", active ? "text-primary" : "text-muted-foreground")}
                        aria-hidden
                      />
                      <span className="font-bold">{r.title}</span>
                      <span className="text-xs text-muted-foreground">{r.desc}</span>
                    </button>
                  );
                })}
              </div>
            )}
          />
          {errors.role && (
            <p role="alert" className="mt-1.5 text-xs font-medium text-destructive">
              {errors.role.message}
            </p>
          )}
        </fieldset>

        {!role && (
          <p className="rounded-xl border border-dashed bg-card px-4 py-6 text-center text-sm text-muted-foreground">
            Choisissez votre profil ci-dessus pour afficher le formulaire adapté.
          </p>
        )}

        {role && (
          <div
            key={role}
            className="space-y-5 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-300"
          >
            <Field
              id="name"
              label={isOwner ? "Nom complet ou nom de la structure" : "Nom complet"}
              error={errors.name?.message}
            >
              <Input
                autoComplete="name"
                {...fieldAria("name", errors.name?.message)}
                {...form.register("name")}
              />
            </Field>
            <Field id="email" label="Adresse e-mail" error={errors.email?.message}>
              <Input
                type="email"
                autoComplete="email"
                {...fieldAria("email", errors.email?.message)}
                {...form.register("email")}
              />
            </Field>
            <Field
              id="telephone"
              label={isOwner ? "Téléphone / WhatsApp" : "Téléphone"}
              optional={!isOwner}
              error={errors.telephone?.message}
              hint={
                isOwner
                  ? "Choisissez le pays puis saisissez votre numéro (Bénin : 01 XX XX XX XX). Les locataires vous contactent dessus une fois leur demande confirmée."
                  : "Choisissez le pays puis saisissez votre numéro (Bénin : 01 XX XX XX XX)."
              }
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

            {isOwner && (
              <fieldset className="space-y-4 rounded-2xl border border-primary/20 bg-primary-soft/60 p-4">
                <legend className="-ml-1 flex items-center gap-2 px-1 text-sm font-bold">
                  <span className="grid size-6 place-items-center rounded-md bg-primary text-primary-foreground">
                    <MapPin className="size-3.5" aria-hidden />
                  </span>
                  Où se trouve votre matériel ?
                </legend>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field id="ville" label="Ville" error={errors.ville?.message}>
                    <Input
                      autoComplete="address-level2"
                      placeholder="Cotonou"
                      {...fieldAria("ville", errors.ville?.message)}
                      {...form.register("ville")}
                    />
                  </Field>
                  <Field id="quartier" label="Quartier" optional error={errors.quartier?.message}>
                    <Input
                      placeholder="Haie Vive"
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
                  hint="Ville et quartier sont visibles sur vos annonces. L'adresse précise n'est partagée qu'avec le locataire dont vous confirmez la demande."
                >
                  <Input
                    placeholder="Ex. : en face de la pharmacie, rue 123"
                    {...fieldAria("adresse", errors.adresse?.message)}
                    {...form.register("adresse")}
                  />
                </Field>
              </fieldset>
            )}

            <div className="grid gap-5 sm:grid-cols-2">
              <Field id="password" label="Mot de passe" error={errors.password?.message}>
                <PasswordInput
                  autoComplete="new-password"
                  {...fieldAria("password", errors.password?.message)}
                  {...form.register("password")}
                />
              </Field>
              <Field
                id="password_confirmation"
                label="Confirmation"
                error={errors.password_confirmation?.message}
              >
                <PasswordInput
                  autoComplete="new-password"
                  {...fieldAria("password_confirmation", errors.password_confirmation?.message)}
                  {...form.register("password_confirmation")}
                />
              </Field>
            </div>
            <PasswordRules value={form.watch("password") ?? ""} />
            <LoadingButton type="submit" size="lg" className="w-full" loading={isSubmitting}>
              {isOwner ? "Créer mon compte propriétaire" : "Créer mon compte locataire"}
            </LoadingButton>
          </div>
        )}

        <p className="text-center text-sm text-muted-foreground">
          Déjà inscrit ?{" "}
          <Link
            to="/connexion"
            search={{ redirect }}
            className="font-semibold text-primary hover:underline"
          >
            Se connecter
          </Link>
        </p>
      </form>
    </AuthShell>
  );
}
