import { createFileRoute, Link } from "@tanstack/react-router";
import { CalendarClock, Hourglass, Inbox, Plus, Wallet } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useState } from "react";
import { useDemandes, useOwnerStats } from "@/hooks/use-locations";
import { formatFCFA, formatRange } from "@/lib/format";
import { seo } from "@/lib/seo";
import type { Location } from "@/types/api";
import { useAuth } from "@/hooks/use-auth";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { DemandeActionButtons, DemandeDialog, useDemandeActions } from "@/components/DemandeDialog";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";

export const Route = createFileRoute("/proprietaire/")({
  head: () => seo("Tableau de bord", "Vos demandes en attente, locations en cours et revenus du mois."),
  component: Dashboard,
});

function Dashboard() {
  const { user } = useAuth();
  const stats = useOwnerStats();
  const pending = useDemandes("en_attente", 1);
  const actions = useDemandeActions();
  const [detail, setDetail] = useState<Location | null>(null);

  return (
    <>
      <PageHeader
        title={`Bonjour ${user?.name.split(" ")[0] ?? ""}`}
        description="Voici l'activité de votre matériel."
        actions={<Button asChild variant="highlight"><Link to="/proprietaire/materiels/nouveau"><Plus aria-hidden /> Ajouter un matériel</Link></Button>}
      />
      {stats.isError ? (
        <ErrorState error={stats.error} onRetry={() => void stats.refetch()} className="mb-8" />
      ) : (
        <div className="mb-10 grid gap-4 sm:grid-cols-3">
          <Stat icon={Hourglass} label="Demandes en attente" value={stats.data?.demandes_en_attente} tone="warning" />
          <Stat icon={CalendarClock} label="Locations en cours" value={stats.data?.locations_en_cours} tone="info" />
          <Stat icon={Wallet} label="Revenus du mois" value={stats.data ? formatFCFA(stats.data.revenus_mois) : undefined} tone="success" />
        </div>
      )}

      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-bold">Dernières demandes en attente</h2>
        <Button asChild variant="link"><Link to="/proprietaire/demandes">Tout voir</Link></Button>
      </div>
      {pending.isPending ? (
        <div className="space-y-3">{Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-20 rounded-xl" />)}</div>
      ) : pending.isError ? (
        <ErrorState error={pending.error} onRetry={() => void pending.refetch()} />
      ) : pending.data.data.length === 0 ? (
        <EmptyState icon={Inbox} title="Aucune demande en attente" description="Les nouvelles demandes de location apparaîtront ici." />
      ) : (
        <ul className="space-y-3">
          {pending.data.data.slice(0, 5).map((d) => (
            <li key={d.id} className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-card sm:flex-row sm:items-center">
              <button type="button" onClick={() => setDetail(d)} className="flex flex-1 items-center gap-3 rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <MaterielPhoto categorie={d.materiel?.categorie} photo={d.materiel?.photo} alt={d.materiel?.nom ?? ""} className="size-14 shrink-0 rounded-lg" iconClassName="size-5" />
                <div className="min-w-0">
                  <p className="truncate font-bold">{d.materiel?.nom}</p>
                  <p className="text-sm text-muted-foreground">{d.locataire?.name} · {formatRange(d.date_debut, d.date_fin)}</p>
                  <p className="text-sm font-semibold">{formatFCFA(d.prix_total)}</p>
                </div>
              </button>
              <DemandeActionButtons d={d} actions={actions} size="sm" />
            </li>
          ))}
        </ul>
      )}
      <DemandeDialog demande={detail} onClose={() => setDetail(null)} actions={actions} />
      {actions.dialogs}
    </>
  );
}

const TONES = {
  warning: "bg-warning/12 text-warning",
  info: "bg-info/12 text-info",
  success: "bg-success/12 text-success",
};

function Stat({ icon: Icon, label, value, tone }: { icon: LucideIcon; label: string; value: string | number | undefined; tone: keyof typeof TONES }) {
  return (
    <div className="rounded-2xl border bg-card p-5 shadow-card">
      <div className={`mb-4 grid size-10 place-items-center rounded-xl ${TONES[tone]}`}>
        <Icon className="size-5" aria-hidden />
      </div>
      <p className="text-sm text-muted-foreground">{label}</p>
      {value === undefined ? <Skeleton className="mt-1 h-8 w-24" /> : <p className="mt-1 text-2xl font-extrabold tabular-nums">{value}</p>}
    </div>
  );
}
