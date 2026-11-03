import { cn } from "@/lib/utils";
import { ETAT_LABELS } from "@/lib/constants";
import type { Etat } from "@/types/api";

export function EtatBadge({ etat, className }: { etat: Etat; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold",
        etat === "neuf" && "bg-highlight/25 text-highlight-foreground",
        etat === "bon" && "bg-accent text-accent-foreground",
        etat === "usé" && "bg-muted text-muted-foreground",
        className,
      )}
    >
      {ETAT_LABELS[etat]}
    </span>
  );
}

export function DispoBadge({ disponible, className }: { disponible: boolean; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset",
        disponible ? "bg-success/12 text-success ring-success/30" : "bg-muted text-muted-foreground ring-border",
        className,
      )}
    >
      <span aria-hidden className="size-1.5 rounded-full bg-current" />
      {disponible ? "Disponible" : "Indisponible"}
    </span>
  );
}
