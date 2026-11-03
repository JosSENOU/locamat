import { cn } from "@/lib/utils";
import { STATUT_LABELS } from "@/lib/constants";
import type { Statut } from "@/types/api";

const styles: Record<Statut, string> = {
  en_attente: "bg-warning/12 text-warning ring-warning/30",
  confirmee: "bg-success/12 text-success ring-success/30",
  refusee: "bg-destructive/10 text-destructive ring-destructive/30",
  annulee: "bg-neutral/12 text-neutral ring-neutral/30",
  terminee: "bg-info/12 text-info ring-info/30",
};

export function StatusBadge({ statut, className }: { statut: Statut; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset",
        styles[statut],
        className,
      )}
    >
      <span aria-hidden className="size-1.5 rounded-full bg-current" />
      {STATUT_LABELS[statut]}
    </span>
  );
}
