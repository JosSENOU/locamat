import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";
import { api, ApiError } from "@/lib/api";
import { resetPasswordSchema, type ResetPasswordValues } from "@/lib/schemas";
import { applyServerErrors } from "@/lib/form-errors";
import { seo } from "@/lib/seo";
import { AuthShell } from "@/components/AuthShell";
import { Field, fieldAria } from "@/components/FormField";
import { PasswordInput } from "@/components/PasswordInput";
import { PasswordRules } from "@/components/PasswordRules";
import { LoadingButton } from "@/components/SubmitButton";

export const Route = createFileRoute("/mot-de-passe/reinitialiser")({
  validateSearch: (search: Record<string, unknown>): { token?: string; email?: string } => ({
    token: typeof search.token === "string" ? search.token : undefined,
    email: typeof search.email === "string" ? search.email : undefined,
  }),
  head: () => seo("Réinitialiser le mot de passe", "Choisissez un nouveau mot de passe."),
  component: ResetPassword,
});

function ResetPassword() {
  const { token, email } = Route.useSearch();
  const navigate = useNavigate();
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const form = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: "", password_confirmation: "" },
  });
  const { errors, isSubmitting } = form.formState;

  useEffect(() => {
    if (!successMessage) return;

    const timeout = window.setTimeout(() => {
      void navigate({ to: "/connexion", replace: true });
    }, 2500);

    return () => window.clearTimeout(timeout);
  }, [navigate, successMessage]);

  const submit = form.handleSubmit(async (values) => {
    if (!token || !email) {
      toast.error("La vérification est incomplète. Demandez un nouveau code.");
      return;
    }
    try {
      const result = await api<{ message: string }>("/reset-password", {
        method: "POST",
        body: { ...values, token, email },
      });
      setSuccessMessage(result.message);
    } catch (error) {
      if (error instanceof ApiError && (error.errors.email || error.errors.token)) {
        toast.error(error.message);
      } else {
        applyServerErrors(error, form.setError, ["password", "password_confirmation"]);
      }
    }
  });

  return (
    <AuthShell
      title="Nouveau mot de passe"
      subtitle="Choisissez un mot de passe sécurisé d’au moins 8 caractères."
    >
      {successMessage ? (
        <div className="space-y-4 rounded-xl border bg-muted/40 p-4 text-center text-sm">
          <p className="font-semibold">{successMessage}</p>
          <p className="text-muted-foreground">Vous allez être redirigé vers la connexion.</p>
          <Link to="/connexion" className="font-semibold text-primary hover:underline">
            Se connecter maintenant
          </Link>
        </div>
      ) : !token || !email ? (
        <div className="space-y-4 rounded-xl border bg-muted/40 p-4 text-sm">
          <p>La vérification est incomplète ou expirée.</p>
          <Link to="/mot-de-passe-oublie" className="font-semibold text-primary hover:underline">
            Demander un nouveau code
          </Link>
        </div>
      ) : (
        <form onSubmit={submit} noValidate className="space-y-5">
          <Field id="password" label="Nouveau mot de passe" error={errors.password?.message}>
            <PasswordInput
              autoComplete="new-password"
              {...fieldAria("password", errors.password?.message)}
              {...form.register("password")}
            />
          </Field>
          <PasswordRules value={form.watch("password") ?? ""} />
          <Field
            id="password_confirmation"
            label="Confirmer le mot de passe"
            error={errors.password_confirmation?.message}
          >
            <PasswordInput
              autoComplete="new-password"
              {...fieldAria("password_confirmation", errors.password_confirmation?.message)}
              {...form.register("password_confirmation")}
            />
          </Field>
          <LoadingButton type="submit" className="w-full" loading={isSubmitting}>
            Réinitialiser mon mot de passe
          </LoadingButton>
        </form>
      )}
    </AuthShell>
  );
}
