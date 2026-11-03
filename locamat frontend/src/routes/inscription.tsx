import { createFileRoute, Link } from "@tanstack/react-router";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Package, Search } from "lucide-react";
import { toast } from "sonner";
import { useAuth } from "@/hooks/use-auth";
import { registerSchema, type RegisterValues } from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { seo } from "@/lib/seo";
import { cn } from "@/lib/utils";
import { AuthShell } from "@/components/AuthShell";
import { Field, fieldAria } from "@/components/FormField";
import { PasswordInput } from "@/components/PasswordInput";
import { LoadingButton } from "@/components/SubmitButton";
import { Input } from "@/components/ui/input";
import { useAfterAuth } from "@/hooks/use-after-auth";

export const Route = createFileRoute("/inscription")({
  validateSearch: (s: Record<string, unknown>): { redirect?: string } => ({
    redirect: typeof s.redirect === "string" ? s.redirect : undefined,
  }),
  head: () => seo("Inscription", "Créez votre compte LocaMat : louez du matériel ou proposez le vôtre."),
  component: Inscription,
});

const ROLES = [
  { value: "locataire", title: "Je loue du matériel", desc: "Trouvez l'équipement pour vos projets.", icon: Search },
  { value: "proprietaire", title: "Je propose mon matériel", desc: "Publiez et gérez vos locations.", icon: Package },
] as const;

const FIELDS = ["name", "email", "telephone", "role", "password", "password_confirmation"] as const;

function Inscription() {
  const { redirect } = Route.useSearch();
  const { register: signup } = useAuth();
  const after = useAfterAuth(redirect);
  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: "", email: "", telephone: "", password: "", password_confirmation: "" },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (v) => {
    try {
      const u = await signup(v);
      toast.success("Compte créé, bienvenue sur LocaMat !");
      after(u);
    } catch (e) {
      applyServerErrors(e, form.setError, FIELDS);
    }
  });

  return (
    <AuthShell title="Créer un compte" subtitle="Quelques secondes suffisent. Choisissez d'abord votre profil.">
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
                      onClick={() => field.onChange(r.value)}
                      className={cn(
                        "flex flex-col items-start gap-2 rounded-xl border-2 bg-card p-4 text-left transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                        active ? "border-primary bg-primary-soft shadow-card" : "hover:border-primary/40",
                      )}
                    >
                      <r.icon className={cn("size-6", active ? "text-primary" : "text-muted-foreground")} aria-hidden />
                      <span className="font-bold">{r.title}</span>
                      <span className="text-xs text-muted-foreground">{r.desc}</span>
                    </button>
                  );
                })}
              </div>
            )}
          />
          {errors.role && <p role="alert" className="mt-1.5 text-xs font-medium text-destructive">{errors.role.message}</p>}
        </fieldset>
        <Field id="name" label="Nom complet" error={errors.name?.message}>
          <Input autoComplete="name" {...fieldAria("name", errors.name?.message)} {...form.register("name")} />
        </Field>
        <Field id="email" label="Adresse e-mail" error={errors.email?.message}>
          <Input type="email" autoComplete="email" {...fieldAria("email", errors.email?.message)} {...form.register("email")} />
        </Field>
        <Field id="telephone" label="Téléphone" optional error={errors.telephone?.message} hint="Utile pour être contacté via WhatsApp.">
          <Input type="tel" autoComplete="tel" placeholder="+229 01 23 45 67" {...fieldAria("telephone", errors.telephone?.message)} {...form.register("telephone")} />
        </Field>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field id="password" label="Mot de passe" error={errors.password?.message} hint="8 caractères minimum.">
            <PasswordInput autoComplete="new-password" {...fieldAria("password", errors.password?.message)} {...form.register("password")} />
          </Field>
          <Field id="password_confirmation" label="Confirmation" error={errors.password_confirmation?.message}>
            <PasswordInput autoComplete="new-password" {...fieldAria("password_confirmation", errors.password_confirmation?.message)} {...form.register("password_confirmation")} />
          </Field>
        </div>
        <LoadingButton type="submit" size="lg" className="w-full" loading={isSubmitting}>
          Créer mon compte
        </LoadingButton>
        <p className="text-center text-sm text-muted-foreground">
          Déjà inscrit ?{" "}
          <Link to="/connexion" search={{ redirect }} className="font-semibold text-primary hover:underline">
            Se connecter
          </Link>
        </p>
      </form>
    </AuthShell>
  );
}
