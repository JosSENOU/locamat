import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { loginSchema } from "@/lib/schemas";
import { AuthShell } from "@/components/AuthShell";
import { Field, fieldAria } from "@/components/FormField";
import { Input } from "@/components/ui/input";
import { LoadingButton } from "@/components/SubmitButton";
import { applyServerErrors } from "@/lib/form-errors";
import { seo } from "@/lib/seo";

const requestSchema = loginSchema.pick({ email: true });
type RequestValues = { email: string };

export const Route = createFileRoute("/mot-de-passe-oublie")({
  head: () => seo("Mot de passe oublié", "Recevez un code pour réinitialiser votre mot de passe."),
  component: ForgotPassword,
});

function ForgotPassword() {
  const navigate = useNavigate();
  const form = useForm<RequestValues>({
    resolver: zodResolver(requestSchema),
    defaultValues: { email: "" },
  });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      await api<{ message: string }>("/forgot-password", {
        method: "POST",
        body: values,
      });
      toast.success("Si un compte correspond à cette adresse, un code lui a été envoyé.");
      void navigate({
        to: "/mot-de-passe/code-verification",
        search: { email: values.email },
      });
    } catch (error) {
      applyServerErrors(error, form.setError, ["email"]);
    }
  });

  return (
    <AuthShell
      title="Mot de passe oublié ?"
      subtitle="Nous vous enverrons un code de vérification si un compte correspond à cette adresse."
    >
      <form onSubmit={submit} noValidate className="space-y-5">
        <Field id="email" label="Adresse e-mail" error={errors.email?.message}>
          <Input
            type="email"
            autoComplete="email"
            {...fieldAria("email", errors.email?.message)}
            {...form.register("email")}
          />
        </Field>
        <LoadingButton type="submit" className="w-full" loading={isSubmitting}>
          Envoyer le code
        </LoadingButton>
        <p className="text-center text-sm text-muted-foreground">
          <Link to="/connexion" className="font-semibold text-primary hover:underline">
            Retour à la connexion
          </Link>
        </p>
      </form>
    </AuthShell>
  );
}
