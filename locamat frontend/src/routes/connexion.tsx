import { useEffect } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useAfterAuth } from "@/hooks/use-after-auth";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";
import { useAuth } from "@/hooks/use-auth";
import { loginSchema, type LoginValues } from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { seo } from "@/lib/seo";
import { AuthShell } from "@/components/AuthShell";
import { Field, fieldAria } from "@/components/FormField";
import { PasswordInput } from "@/components/PasswordInput";
import { LoadingButton } from "@/components/SubmitButton";
import { Input } from "@/components/ui/input";

export const Route = createFileRoute("/connexion")({
  validateSearch: (s: Record<string, unknown>): { redirect?: string } => ({
    redirect: typeof s.redirect === "string" ? s.redirect : undefined,
  }),
  head: () => seo("Connexion", "Connectez-vous à LocaMat pour louer ou proposer du matériel."),
  component: Connexion,
});

function Connexion() {
  const { redirect } = Route.useSearch();
  const { login, user } = useAuth();
  const after = useAfterAuth(redirect);
  const form = useForm<LoginValues>({ resolver: zodResolver(loginSchema), defaultValues: { email: "", password: "" } });
  const { errors, isSubmitting } = form.formState;

  useEffect(() => {
    if (user) after(user);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSubmit = form.handleSubmit(async (v) => {
    try {
      const u = await login(v);
      toast.success(`Bonjour ${u.name} !`);
      after(u);
    } catch (e) {
      applyServerErrors(e, form.setError, ["email", "password"]);
    }
  });

  return (
    <AuthShell title="Bon retour parmi nous" subtitle="Connectez-vous pour suivre vos locations et vos demandes.">
      <form onSubmit={onSubmit} noValidate className="space-y-5">
        <Field id="email" label="Adresse e-mail" error={errors.email?.message}>
          <Input type="email" autoComplete="email" {...fieldAria("email", errors.email?.message)} {...form.register("email")} />
        </Field>
        <Field id="password" label="Mot de passe" error={errors.password?.message}>
          <PasswordInput autoComplete="current-password" {...fieldAria("password", errors.password?.message)} {...form.register("password")} />
        </Field>
        <LoadingButton type="submit" size="lg" className="w-full" loading={isSubmitting}>
          Se connecter
        </LoadingButton>
        <p className="text-center text-sm text-muted-foreground">
          Pas encore de compte ?{" "}
          <Link to="/inscription" search={{ redirect }} className="font-semibold text-primary hover:underline">
            Créer un compte
          </Link>
        </p>
      </form>
    </AuthShell>
  );
}
