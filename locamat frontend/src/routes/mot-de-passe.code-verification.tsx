import { useState } from "react";
import type { FormEvent } from "react";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { api, errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import { AuthShell } from "@/components/AuthShell";
import { Field } from "@/components/FormField";
import { Input } from "@/components/ui/input";
import { LoadingButton } from "@/components/SubmitButton";

export const Route = createFileRoute("/mot-de-passe/code-verification")({
  validateSearch: (search: Record<string, unknown>): { email?: string } => ({
    email: typeof search.email === "string" ? search.email : undefined,
  }),
  head: () =>
    seo("Code de vérification", "Vérifiez votre adresse pour réinitialiser votre mot de passe."),
  component: VerifyPasswordResetCode,
});

function VerifyPasswordResetCode() {
  const { email } = Route.useSearch();
  const navigate = useNavigate();
  const [code, setCode] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!email || code.length !== 6) return;

    setSubmitting(true);
    setError(null);
    try {
      const result = await api<{ token: string }>("/verify-reset-code", {
        method: "POST",
        body: { email, code },
      });
      void navigate({
        to: "/mot-de-passe/reinitialiser",
        search: { email, token: result.token },
        replace: true,
      });
    } catch (verificationError) {
      setError(errorMessage(verificationError));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthShell
      title="Vérification par code"
      subtitle={
        email
          ? `Saisissez le code à 6 chiffres envoyé à ${email}.`
          : "Le lien est incomplet. Recommencez la demande avec votre adresse e-mail."
      }
    >
      {email ? (
        <form onSubmit={submit} className="space-y-5">
          <Field id="code" label="Code de vérification">
            <Input
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              pattern="[0-9]{6}"
              maxLength={6}
              required
              value={code}
              onChange={(event) => {
                setCode(event.target.value.replace(/\D/g, "").slice(0, 6));
                setError(null);
              }}
            />
          </Field>
          {error && (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          )}
          <LoadingButton type="submit" className="w-full" loading={submitting}>
            Vérifier le code
          </LoadingButton>
          <p className="text-center text-sm text-muted-foreground">
            <Link to="/mot-de-passe-oublie" className="font-semibold text-primary hover:underline">
              Demander un autre code
            </Link>
          </p>
        </form>
      ) : (
        <Link to="/mot-de-passe-oublie" className="font-semibold text-primary hover:underline">
          Recommencer
        </Link>
      )}
    </AuthShell>
  );
}
