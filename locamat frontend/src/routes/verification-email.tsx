import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { CheckCircle2 } from "lucide-react";
import { api, errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import { AuthShell } from "@/components/AuthShell";
import { Field } from "@/components/FormField";
import { Input } from "@/components/ui/input";
import { LoadingButton } from "@/components/SubmitButton";

export const Route = createFileRoute("/verification-email")({
  validateSearch: (search: Record<string, unknown>): { verified: boolean; email?: string } => ({
    // Le routeur parse `?verified=1` en nombre (JSON) : on accepte toutes les formes.
    verified: [1, "1", true, "true"].includes(search.verified as string | number | boolean),
    email: typeof search.email === "string" ? search.email : undefined,
  }),
  head: () => seo("Vérification de l’adresse e-mail", "Activez votre compte LocaMat."),
  component: EmailVerification,
});

function EmailVerification() {
  const { verified, email } = Route.useSearch();
  const navigate = useNavigate();
  const [address, setAddress] = useState(email ?? "");
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [sendError, setSendError] = useState<string | null>(null);

  useEffect(() => {
    if (!verified) return;

    const timeout = window.setTimeout(() => {
      void navigate({ to: "/connexion", replace: true });
    }, 6000);

    return () => window.clearTimeout(timeout);
  }, [navigate, verified]);

  const resend = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSending(true);
    setSent(false);
    setSendError(null);
    try {
      await api("/email/verification-notification", {
        method: "POST",
        body: { email: address },
      });
      setSent(true);
    } catch (error) {
      setSendError(errorMessage(error));
    } finally {
      setSending(false);
    }
  };

  return (
    <AuthShell
      title={verified ? "E-mail vérifié avec succès !" : "Vérifiez votre adresse e-mail"}
      subtitle={
        verified
          ? "Bienvenue sur LocaMat ! Votre adresse e-mail est confirmée et votre compte est activé : vous pouvez maintenant vous connecter."
          : "Ouvrez le lien envoyé par e-mail pour activer votre compte avant de vous connecter."
      }
    >
      <div className="space-y-5 text-center">
        {verified && <CheckCircle2 className="mx-auto size-16 text-primary" aria-hidden />}
        {verified && (
          <p role="status" className="text-lg font-bold text-primary">
            E-mail vérifié avec succès. Bienvenue sur LocaMat !
          </p>
        )}
        {verified && (
          <Link
            to="/connexion"
            className="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
          >
            Se connecter maintenant
          </Link>
        )}
        {verified && (
          <p className="text-xs text-muted-foreground">
            Vous serez redirigé automatiquement vers la page de connexion dans quelques secondes.
          </p>
        )}
        {!verified && (
          <>
            <p className="text-sm text-muted-foreground">
              {email
                ? `Un lien de confirmation a été envoyé à ${email}.`
                : "Ouvrez le lien envoyé par e-mail pour activer votre compte."}
            </p>
            <form onSubmit={resend} className="space-y-3 text-left">
              <Field id="email" label="Adresse e-mail">
                <Input
                  type="email"
                  autoComplete="email"
                  required
                  value={address}
                  onChange={(event) => {
                    setAddress(event.target.value);
                    setSent(false);
                  }}
                />
              </Field>
              <LoadingButton type="submit" variant="outline" className="w-full" loading={sending}>
                Renvoyer le lien de confirmation
              </LoadingButton>
              {sent && (
                <p role="status" className="text-center text-sm text-muted-foreground">
                  Si un compte non vérifié correspond à cette adresse, un nouveau lien a été envoyé.
                </p>
              )}
              {sendError && (
                <p role="alert" className="text-center text-sm text-destructive">
                  {sendError}
                </p>
              )}
            </form>
          </>
        )}
        {!verified && (
          <Link
            to="/connexion"
            className="block text-sm font-semibold text-primary hover:underline"
          >
            Aller à la connexion
          </Link>
        )}
      </div>
    </AuthShell>
  );
}
