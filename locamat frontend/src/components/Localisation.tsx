import { MapPin } from "lucide-react";
import { cn } from "@/lib/utils";
import type { ProprietaireLite } from "@/types/api";

type Localisation = Pick<ProprietaireLite, "ville" | "quartier">;

/** « Haie Vive, Cotonou » : quartier (facultatif) puis ville. */
function localisationLabel(p: Localisation | null | undefined): string | null {
  const parts = [p?.quartier, p?.ville].filter((v): v is string => !!v && v.trim() !== "");
  return parts.length > 0 ? parts.join(", ") : null;
}

/** Ligne compacte (carte du catalogue, fiche propriétaire). */
export function LocalisationInline({
  proprietaire,
  className,
}: {
  proprietaire: Localisation | null | undefined;
  className?: string;
}) {
  const label = localisationLabel(proprietaire);
  if (!label) return null;
  return (
    <span className={cn("inline-flex items-center gap-1", className)}>
      <MapPin className="size-3.5 shrink-0" aria-hidden />
      <span className="truncate">{label}</span>
    </span>
  );
}

/**
 * Bloc « Où récupérer le matériel ».
 * Ville + quartier sont publics ; l'adresse précise n'est fournie par l'API
 * qu'une fois la location confirmée (`confirmed`).
 */
export function LocalisationCard({
  proprietaire,
  adresse,
  confirmed = false,
  className,
}: {
  proprietaire: Localisation | null | undefined;
  adresse?: string | null;
  confirmed?: boolean;
  className?: string;
}) {
  const label = localisationLabel(proprietaire);
  return (
    <div
      className={cn(
        "flex gap-3 rounded-xl border border-primary/20 bg-primary-soft p-3.5",
        className,
      )}
    >
      <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground">
        <MapPin className="size-4" aria-hidden />
      </span>
      <div className="min-w-0 text-sm">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Où récupérer le matériel
        </p>
        {label ? (
          <p className="font-bold">{label}</p>
        ) : (
          <p className="text-muted-foreground">
            Le propriétaire n'a pas encore indiqué sa localisation.
          </p>
        )}
        {confirmed ? (
          <p className="mt-1">
            {adresse ? (
              <>
                <span className="text-muted-foreground">Adresse précise : </span>
                {adresse}
              </>
            ) : (
              <span className="text-xs text-muted-foreground">
                Aucune adresse précise renseignée : contactez le propriétaire.
              </span>
            )}
          </p>
        ) : (
          <p className="mt-1 text-xs text-muted-foreground">
            L'adresse précise vous sera communiquée dès que le propriétaire aura confirmé votre
            demande.
          </p>
        )}
      </div>
    </div>
  );
}
