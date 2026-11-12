import { History } from "lucide-react";
import { formatDateTime } from "@/lib/format";
import type { LocationHistorique } from "@/types/api";
import { StatusBadge } from "./StatusBadge";

const EVENEMENTS: Record<LocationHistorique["evenement"], string> = {
  creation: "Demande envoyée",
  confirmation: "Demande confirmée",
  refus: "Demande refusée",
  annulation: "Demande annulée",
  prolongation_demandee: "Prolongation demandée",
  prolongation_acceptee: "Prolongation acceptée",
  prolongation_refusee: "Prolongation refusée",
  retour_signale: "Retour signalé",
  terminee: "Retour confirmé, location terminée",
};

/** Chronologie datée des statuts d'une location (du plus ancien au plus récent). */
export function HistoriqueLocation({ historiques }: { historiques?: LocationHistorique[] }) {
  if (!historiques || historiques.length === 0) return null;

  return (
    <section aria-labelledby="historique-titre" className="rounded-xl border p-4">
      <h3
        id="historique-titre"
        className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
      >
        <History className="size-4" aria-hidden /> Historique
      </h3>
      <ol className="mt-3">
        {historiques.map((h, index) => (
          <li key={h.id} className="relative flex gap-3 pb-4 last:pb-0">
            {index < historiques.length - 1 && (
              <span aria-hidden className="absolute left-[5px] top-3 h-full w-px bg-border" />
            )}
            <span
              aria-hidden
              className="relative mt-1.5 size-[11px] shrink-0 rounded-full border-2 border-primary bg-background"
            />
            <div className="min-w-0 space-y-0.5 text-sm">
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-semibold">{EVENEMENTS[h.evenement] ?? h.evenement}</p>
                <StatusBadge statut={h.statut} />
              </div>
              <p className="text-xs text-muted-foreground">
                {formatDateTime(h.created_at)}
                {h.acteur ? ` · ${h.acteur.name}` : ""}
              </p>
              {h.note && <p className="text-xs text-muted-foreground">{h.note}</p>}
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
