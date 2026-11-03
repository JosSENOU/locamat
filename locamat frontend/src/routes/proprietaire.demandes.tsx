import { useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { Inbox } from "lucide-react";
import { useDemandes } from "@/hooks/use-locations";
import { STATUTS, STATUT_PLURAL } from "@/lib/constants";
import { formatFCFA, formatRange } from "@/lib/format";
import { seo } from "@/lib/seo";
import { cn } from "@/lib/utils";
import type { Location, Statut } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { StatusTabs } from "@/components/StatusTabs";
import { StatusBadge } from "@/components/StatusBadge";
import { PaginationBar } from "@/components/PaginationBar";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { DemandeActionButtons, DemandeDialog, useDemandeActions } from "@/components/DemandeDialog";
import { Skeleton } from "@/components/ui/skeleton";

type Filter = Statut | "toutes";
const ORDER: Statut[] = ["en_attente", "confirmee", "refusee", "terminee", "annulee"];

export const Route = createFileRoute("/proprietaire/demandes")({
  validateSearch: (s: Record<string, unknown>): { statut?: Statut; page?: number } => ({
    statut: STATUTS.includes(s.statut as Statut) ? (s.statut as Statut) : undefined,
    page: Number(s.page) > 1 ? Number(s.page) : undefined,
  }),
  head: () => seo("Demandes reçues", "Confirmez, refusez ou clôturez les demandes de location de votre matériel."),
  component: Demandes,
});

function Demandes() {
  const { statut, page = 1 } = Route.useSearch();
  const navigate = useNavigate({ from: "/proprietaire/demandes" });
  const { data, isPending, isError, error, refetch, isPlaceholderData } = useDemandes(statut, page);
  const actions = useDemandeActions();
  const [detail, setDetail] = useState<Location | null>(null);

  return (
    <>
      <PageHeader title="Demandes reçues" description="Gérez les demandes de location de votre matériel." />
      <StatusTabs<Filter>
        label="Filtrer par statut"
        value={statut ?? "toutes"}
        onChange={(v) => void navigate({ search: { statut: v === "toutes" ? undefined : v } })}
        items={[{ value: "toutes", label: "Toutes" }, ...ORDER.map((s) => ({ value: s as Filter, label: STATUT_PLURAL[s] }))]}
      />
      {isPending ? (
        <div className="space-y-3">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}</div>
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : data.data.length === 0 ? (
        <EmptyState icon={Inbox} title="Aucune demande" description={statut ? "Aucune demande avec ce statut." : "Vous n'avez encore reçu aucune demande de location."} />
      ) : (
        <>
          <ul className={cn("space-y-3", isPlaceholderData && "opacity-60")}>
            {data.data.map((d) => (
              <li key={d.id} className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-card md:flex-row md:items-center">
                <button type="button" onClick={() => setDetail(d)} className="flex flex-1 items-center gap-3 rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                  <MaterielPhoto categorie={d.materiel?.categorie} photo={d.materiel?.photo} alt={d.materiel?.nom ?? ""} className="size-16 shrink-0 rounded-lg" iconClassName="size-5" />
                  <div className="min-w-0 space-y-0.5">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="truncate font-bold">{d.materiel?.nom ?? `Matériel #${d.materiel_id}`}</p>
                      <StatusBadge statut={d.statut} />
                    </div>
                    <p className="text-sm text-muted-foreground">{d.locataire?.name} · {formatRange(d.date_debut, d.date_fin)} · {d.nb_jours} j</p>
                    <p className="text-sm font-semibold">{formatFCFA(d.prix_total)}</p>
                  </div>
                </button>
                <DemandeActionButtons d={d} actions={actions} size="sm" />
              </li>
            ))}
          </ul>
          <PaginationBar meta={data.meta} onPage={(p) => void navigate({ search: (prev) => ({ ...prev, page: p > 1 ? p : undefined }) })} />
        </>
      )}
      <DemandeDialog demande={detail} onClose={() => setDetail(null)} actions={actions} />
      {actions.dialogs}
    </>
  );
}
