import { useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { zodResolver } from "@hookform/resolvers/zod";
import { AlertCircle, CheckCircle2 } from "lucide-react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { AuthShell } from "@/components/AuthShell";
import { Field, fieldAria } from "@/components/FormField";
import { LoadingButton } from "@/components/SubmitButton";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiError } from "@/lib/api";
import { seo } from "@/lib/seo";

const supportSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, "L’adresse e-mail est requise.")
    .email("Adresse e-mail invalide."),
  message: z
    .string()
    .trim()
    .min(10, "Le message doit contenir au moins 10 caractères.")
    .max(1000, "Le message ne peut pas dépasser 1 000 caractères."),
});

type SupportValues = z.infer<typeof supportSchema>;

interface SupportResponse {
  message: string;
  locked_until: string;
}

export const Route = createFileRoute("/compte-bloque")({
  validateSearch: (search: Record<string, unknown>): { email?: string; redirect?: string } => ({
    email: typeof search.email === "string" ? search.email : undefined,
    redirect: typeof search.redirect === "string" ? search.redirect : undefined,
  }),
  head: () => seo("Compte bloqué", "Demandez de l’aide pour récupérer votre compte LocaMat."),
  component: CompteBloque,
});

function CompteBloque() {
  const { email, redirect } = Route.useSearch();
  const [submitted, setSubmitted] = useState<SupportResponse | null>(null);
  const form = useForm<SupportValues>({
    resolver: zodResolver(supportSchema),
    defaultValues: { email: email ?? "", message: "" },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      setSubmitted(
        await api<SupportResponse>("/account-lock/support", {
          method: "POST",
          body: values,
        }),
      );
    } catch (error) {
      if (error instanceof ApiError && error.hasFieldErrors) {
        for (const [field, messages] of Object.entries(error.errors)) {
          if ((field === "email" || field === "message") && messages[0]) {
            form.setError(field, { type: "server", message: messages[0] });
          }
        }
        return;
      }
      form.setError("root.server", {
        type: "server",
        message:
          error instanceof ApiError
            ? error.message
            : "Impossible d’envoyer votre demande. Réessayez plus tard.",
      });
    }
  });

  return (
    <AuthShell
      title={submitted ? "Demande envoyée" : "Votre compte est bloqué"}
      subtitle={
        submitted
          ? "L’administrateur a été informé du blocage de votre compte."
          : "Après trois tentatives de connexion incorrectes, l’accès à votre compte est temporairement bloqué."
      }
    >
      {submitted ? (
        <div className="space-y-5">
          <div className="rounded-xl border border-success/30 bg-success/5 p-4 text-sm">
            <p className="flex items-center gap-2 font-semibold text-success">
              <CheckCircle2 className="size-5" aria-hidden />
              Votre message a été transmis.
            </p>
            <p className="mt-3 text-muted-foreground">
              Un e-mail de confirmation a été envoyé à <strong>{form.getValues("email")}</strong>.
              Votre compte sera rétabli au plus tard le{" "}
              <strong>
                {new Date(submitted.locked_until).toLocaleString("fr-FR", {
                  dateStyle: "long",
                  timeStyle: "short",
                  timeZone: "Africa/Porto-Novo",
                })}
              </strong>
              . L’administrateur peut le débloquer avant cette échéance.
            </p>
          </div>
          <Button asChild variant="outline" className="w-full">
            <Link to="/connexion" search={redirect ? { redirect } : {}}>
              Retour à la connexion
            </Link>
          </Button>
        </div>
      ) : (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
          <div className="flex gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
            <AlertCircle className="size-5 shrink-0 text-amber-600" aria-hidden />
            <p>
              Envoyez un message à l’administrateur. Après l’envoi, votre compte sera rétabli au
              plus tard dans les 12 heures suivant son blocage. Un e-mail vous confirmera la prise
              en compte de votre demande.
            </p>
          </div>
          <Field id="email" label="Adresse e-mail" error={errors.email?.message}>
            <Input
              type="email"
              autoComplete="email"
              {...fieldAria("email", errors.email?.message)}
              {...form.register("email")}
            />
          </Field>
          <Field
            id="message"
            label="Message à l’administrateur"
            error={errors.message?.message}
            hint="Expliquez que votre compte est bloqué. 10 caractères minimum."
          >
            <Textarea
              rows={5}
              maxLength={1000}
              placeholder="Bonjour, mon compte est bloqué après trois tentatives de connexion. Pouvez-vous m’aider ?"
              {...fieldAria("message", errors.message?.message)}
              {...form.register("message")}
            />
          </Field>
          {errors.root?.server?.message && (
            <p role="alert" className="text-sm font-medium text-destructive">
              {errors.root.server.message}
            </p>
          )}
          <LoadingButton type="submit" size="lg" className="w-full" loading={isSubmitting}>
            Envoyer à l’administrateur
          </LoadingButton>
          <p className="text-center text-sm text-muted-foreground">
            <Link
              to="/connexion"
              search={redirect ? { redirect } : {}}
              className="font-semibold text-primary hover:underline"
            >
              Retour à la connexion
            </Link>
          </p>
        </form>
      )}
    </AuthShell>
  );
}
