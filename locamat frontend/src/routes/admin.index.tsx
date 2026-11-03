import { createFileRoute, Link } from "@tanstack/react-router";
import {
  Activity,
  ArrowDownRight,
  ArrowRight,
  ClipboardList,
  Coins,
  Package,
  ShieldCheck,
  Users,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useAdminStats } from "@/hooks/use-admin";
import { formatFCFA } from "@/lib/format";
import { seo } from "@/lib/seo";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { Skeleton } from "@/components/ui/skeleton";

export const Route = createFileRoute("/admin/")({
  head: () => seo("Administration", "Tableau de bord et activité de la plateforme LocaMat."),
  component: AdminDashboard,
});

function AdminDashboard() {
  const { data, isPending, isError, error, refetch } = useAdminStats();

  return (
    <>
      <PageHeader
        title="Tableau de bord"
        description="Suivez l’activité de LocaMat et gérez la plateforme en toute sécurité."
      />
      {isPending ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 6 }, (_, index) => (
            <Skeleton key={index} className="h-36 rounded-2xl" />
          ))}
        </div>
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : !data ? (
        <EmptyState
          icon={Activity}
          title="Statistiques indisponibles"
          description="Aucune statistique n’a été retournée."
        />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <MetricCard
              to="/admin/utilisateurs"
              icon={Users}
              label="Comptes"
              value={data.total_utilisateurs}
              detail={`${data.utilisateurs_actifs} actifs · ${data.utilisateurs_suspendus} désactivés`}
              color="primary"
            />
            <MetricCard
              to="/admin/utilisateurs"
              icon={ShieldCheck}
              label="Administrateurs"
              value={data.administrateurs}
              detail={`${data.proprietaires} propriétaires · ${data.locataires} locataires`}
              color="info"
            />
            <MetricCard
              to="/admin/materiels"
              icon={Package}
              label="Matériels"
              value={data.total_materiels}
              detail={`${data.materiels_disponibles} visibles dans le catalogue`}
              color="highlight"
            />
            <MetricCard
              to="/admin/locations"
              icon={ClipboardList}
              label="Locations"
              value={data.locations_total}
              detail={`${data.demandes_en_attente} demandes en attente`}
              color="warning"
            />
            <MetricCard
              to="/admin/locations"
              icon={ArrowDownRight}
              label="Locations confirmées"
              value={data.locations_confirmees}
              detail={`${data.locations_terminees} locations terminées`}
              color="success"
            />
            <MetricCard
              to="/admin/locations"
              icon={Coins}
              label="Revenus réalisés"
              value={formatFCFA(data.revenus_total)}
              detail="Total des locations terminées"
              color="success"
            />
          </div>

          <section className="mt-8 rounded-2xl border bg-card p-5 shadow-card sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div className="flex items-center gap-2 font-bold">
                  <Activity className="size-4 text-primary" aria-hidden />
                  Votre espace de contrôle
                </div>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                  Les comptes peuvent être désactivés et les matériels masqués sans supprimer leur
                  historique. Le dernier administrateur actif est protégé.
                </p>
              </div>
              <Link
                to="/profil"
                className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-primary hover:underline"
              >
                Gérer mon profil <ArrowRight className="size-4" aria-hidden />
              </Link>
            </div>
          </section>
        </>
      )}
    </>
  );
}

function MetricCard({
  to,
  icon: Icon,
  label,
  value,
  detail,
  color,
}: {
  to: "/admin/utilisateurs" | "/admin/materiels" | "/admin/locations";
  icon: LucideIcon;
  label: string;
  value: string | number;
  detail: string;
  color: "primary" | "info" | "highlight" | "warning" | "success";
}) {
  const colors = {
    primary: "bg-primary/10 text-primary",
    info: "bg-info/10 text-info",
    highlight: "bg-highlight/20 text-highlight-foreground",
    warning: "bg-warning/10 text-warning",
    success: "bg-success/10 text-success",
  } as const;

  return (
    <Link
      to={to}
      className="group rounded-2xl border bg-card p-5 shadow-card transition-all hover:-translate-y-0.5 hover:shadow-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <div className="flex items-start justify-between gap-3">
        <span className="text-sm font-semibold text-muted-foreground">{label}</span>
        <span className={`grid size-10 place-items-center rounded-xl ${colors[color]}`}>
          <Icon className="size-5" aria-hidden />
        </span>
      </div>
      <p className="mt-4 text-3xl font-extrabold tabular-nums">{value}</p>
      <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
      <span className="sr-only">Ouvrir {label.toLowerCase()}</span>
    </Link>
  );
}
