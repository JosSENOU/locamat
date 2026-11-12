import { createFileRoute, Link, Outlet, useLocation } from "@tanstack/react-router";
import {
  BookOpenCheck,
  LayoutDashboard,
  LogOut,
  ShieldCheck,
  Trash2,
  UserCog,
  Users,
} from "lucide-react";
import { RequireRole } from "@/components/RequireRole";
import { Logo } from "@/components/Logo";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/super-admin")({
  component: SuperAdminLayout,
});

const NAV = [
  { to: "/super-admin", label: "Vue d’ensemble", icon: LayoutDashboard, exact: true },
  { to: "/super-admin/journal", label: "Historique", icon: BookOpenCheck, exact: false },
  { to: "/super-admin/utilisateurs", label: "Utilisateurs", icon: Users, exact: false },
  { to: "/super-admin/corbeille", label: "Corbeille", icon: Trash2, exact: false },
  { to: "/super-admin/administrateurs", label: "Administrateurs", icon: UserCog, exact: false },
] as const;

/**
 * Espace distinct du site public : sa propre page de connexion (/super-admin/connexion),
 * sa propre barre de navigation et aucun lien vers le catalogue.
 */
function SuperAdminLayout() {
  const { pathname } = useLocation();

  // La page de connexion reste accessible sans session.
  if (pathname.replace(/\/+$/, "") === "/super-admin/connexion") return <Outlet />;

  return (
    <RequireRole roles={["super_admin"]} loginTo="super-admin">
      <SuperAdminShell />
    </RequireRole>
  );
}

function SuperAdminShell() {
  const { user, logout } = useAuth();

  return (
    <div className="flex min-h-screen flex-col bg-muted/30">
      <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur-xl">
        <div className="container-page flex h-16 items-center gap-3">
          <Link
            to="/super-admin"
            aria-label="LocaMat, espace super-administrateur"
            className="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <Logo />
          </Link>
          <span className="hidden items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary sm:inline-flex">
            <ShieldCheck className="size-3.5" aria-hidden />
            Super-administration
          </span>
          <div className="ml-auto flex items-center gap-2">
            <span className="hidden max-w-40 truncate text-sm font-semibold md:inline">
              {user?.name}
            </span>
            <Button variant="outline" size="sm" onClick={() => void logout()}>
              <LogOut aria-hidden /> Déconnexion
            </Button>
          </div>
        </div>
        <nav
          aria-label="Espace super-administrateur"
          className="container-page flex gap-1 overflow-x-auto pb-2"
        >
          {NAV.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              activeOptions={{ exact: item.exact }}
              className="flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              activeProps={{ className: "!text-primary bg-primary-soft" }}
            >
              <item.icon className="size-4" aria-hidden />
              {item.label}
            </Link>
          ))}
        </nav>
      </header>
      <main className="container-page flex-1 py-8">
        <Outlet />
      </main>
    </div>
  );
}
