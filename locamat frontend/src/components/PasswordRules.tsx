import { Check, Circle } from "lucide-react";
import { passwordRules } from "@/lib/password";
import { cn } from "@/lib/utils";

/** Liste de contrôle qui se coche au fil de la saisie du mot de passe. */
export function PasswordRules({
  value,
  minLength = 8,
  className,
}: {
  value: string;
  minLength?: number;
  className?: string;
}) {
  return (
    <ul
      aria-label="Règles du mot de passe"
      className={cn("grid gap-1 rounded-xl border bg-muted/40 p-3 text-xs sm:grid-cols-2", className)}
    >
      {passwordRules(minLength).map((rule) => {
        const ok = rule.test(value) && value.length > 0;

        return (
          <li
            key={rule.id}
            className={cn(
              "flex items-center gap-2",
              ok ? "font-medium text-primary" : "text-muted-foreground",
            )}
          >
            {ok ? (
              <Check className="size-3.5 shrink-0" aria-hidden />
            ) : (
              <Circle className="size-3.5 shrink-0" aria-hidden />
            )}
            <span>{rule.label}</span>
            <span className="sr-only">{ok ? "respecté" : "non respecté"}</span>
          </li>
        );
      })}
    </ul>
  );
}
