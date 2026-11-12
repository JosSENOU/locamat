import { createFileRoute, Link } from "@tanstack/react-router";
import { ArrowRight, BookOpenCheck, Trash2, UserCog, Users } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useSuperAdminApercu } from "@/hooks/use-super-admin";
import { formatRetention } from "@/lib/format";
import { seo } from "@/lib/seo";
import { PageHeader } from "@/components/States";

export const Route = createFileRoute("/super-admin/")({
  head: () => seo("Super-administration", "Vue d’ensemble de la plateforme LocaMat."),
  component: SuperAdminDashboard,
});

type ShortcutTarget =
  | "/super-admin/journal"
  | "/super-admin/utilisateurs"
  | "/super-admin/administrateurs"
  | "/super-admin/corbeille";

function SuperAdminDashboard() {
  const apercu = useSuperAdminApercu();

  return (
    <>
      <PageHeader
        title="Vue d’ensemble"
        description="Historique de la plateforme, utilisateurs, administrateurs et corbeille."
      />

      <div className="grid gap-4 md:grid-cols-2">
        <ShortcutCard
          to="/super-admin/journal"
          icon={BookOpenCheck}
          title="Historique"
          description="Tout ce qui se passe sur la plateforme : actions des utilisateurs et des administrateurs."
        />
        <ShortcutCard
          to="/super-admin/utilisateurs"
          icon={Users}
          title="Utilisateurs"
          description="Ouvrez l’historique de chaque utilisateur : ce qu’il a fait et ce qui lui est arrivé."
        />
        <ShortcutCard
          to="/super-admin/administrateurs"
          icon={UserCog}
          title="Administrateurs"
          description={
            apercu.data
              ? `${apercu.data.administrateurs} compte(s) administrateur — créez-les et suivez leurs actions.`
              : "Créez et gérez les comptes administrateurs."
          }
        />
        <ShortcutCard
          to="/super-admin/corbeille"
          icon={Trash2}
          title="Corbeille"
          description={
            apercu.data
              ? `${apercu.data.corbeille.utilisateurs} compte(s), ${apercu.data.corbeille.materiels} matériel(s) et ${apercu.data.corbeille.categories} catégorie(s) restaurables pendant ${formatRetention(apercu.data.retention_days)}.`
              : "Restaurez les éléments supprimés par erreur par un administrateur."
          }
        />
      </div>
    </>
  );
}

function ShortcutCard({
  to,
  icon: Icon,
  title,
  description,
}: {
  to: ShortcutTarget;
  icon: LucideIcon;
  title: string;
  description: string;
}) {
  return (
    <Link
      to={to}
      className="group flex items-center gap-4 rounded-2xl border bg-card p-5 shadow-card transition-all hover:-translate-y-0.5 hover:shadow-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
        <Icon className="size-5" aria-hidden />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-bold">{title}</span>
        <span className="text-sm text-muted-foreground">{description}</span>
      </span>
      <ArrowRight
        className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
        aria-hidden
      />
    </Link>
  );
}
