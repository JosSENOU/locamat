import { useEffect } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { useAuth } from "@/hooks/use-auth";
import { ApiError, errorMessage } from "@/lib/api";
import { loginSchema, type LoginValues } from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { seo } from "@/lib/seo";
import { userHasRole } from "@/lib/constants";
import { Logo } from "@/components/Logo";
import { Field, fieldAria } from "@/components/FormField";
import { PasswordInput } from "@/components/PasswordInput";
import { LoadingButton } from "@/components/SubmitButton";
import { Input } from "@/components/ui/input";

export const Route = createFileRoute("/super-admin/connexion")({
  head: () =>
    seo("Connexion super-administrateur", "Accès réservé au super-administrateur LocaMat."),
  component: SuperAdminConnexion,
});

function SuperAdminConnexion() {
  const navigate = useNavigate();
  const { loginSuperAdmin, user } = useAuth();
  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
  });
  const { errors, isSubmitting } = form.formState;

  useEffect(() => {
    if (userHasRole(user, "super_admin")) void navigate({ to: "/super-admin", replace: true });
  }, [user, navigate]);

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const account = await loginSuperAdmin(values);
      toast.success(`Bonjour ${account.name} !`);
      void navigate({ to: "/super-admin", replace: true });
    } catch (error) {
      // Compte bloqué (423) ou désactivé (403) : le message de l'API suffit, aucun champ n'est en cause.
      if (error instanceof ApiError && (error.status === 423 || error.status === 403)) {
        toast.error(errorMessage(error));
        return;
      }
      applyServerErrors(error, form.setError, ["email", "password"]);
    }
  });

  return (
    <div className="grid min-h-screen place-items-center bg-muted/30 px-4 py-10">
      <div className="w-full max-w-md rounded-3xl border bg-card p-8 shadow-elevated">
        <div className="flex justify-center">
          <Logo />
        </div>
        <div className="mt-6 flex flex-col items-center text-center">
          <span className="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
            <ShieldCheck className="size-6" aria-hidden />
          </span>
          <h1 className="mt-4 text-2xl font-extrabold">Espace super-administrateur</h1>
          <p className="mt-1.5 text-sm text-muted-foreground">
            Accès réservé : historique de la plateforme, comptes et restauration des éléments
            supprimés.
          </p>
        </div>
        <form onSubmit={onSubmit} noValidate className="mt-8 space-y-5">
          <Field id="sa-email" label="Adresse e-mail" error={errors.email?.message}>
            <Input
              type="email"
              autoComplete="email"
              {...fieldAria("sa-email", errors.email?.message)}
              {...form.register("email")}
            />
          </Field>
          <Field id="sa-password" label="Mot de passe" error={errors.password?.message}>
            <PasswordInput
              autoComplete="current-password"
              {...fieldAria("sa-password", errors.password?.message)}
              {...form.register("password")}
            />
          </Field>
          <p className="-mt-3 text-xs leading-relaxed text-muted-foreground">
            Pour créer ou modifier un mot de passe : 12 caractères minimum, avec une majuscule, une
            minuscule, un chiffre et un caractère spécial, sans espace.
          </p>
          <LoadingButton type="submit" size="lg" className="w-full" loading={isSubmitting}>
            Se connecter
          </LoadingButton>
        </form>
      </div>
    </div>
  );
}
