import { createFileRoute, Link, Outlet } from "@tanstack/react-router";
import { ClipboardList, LayoutDashboard, Package, Plus } from "lucide-react";
import { RequireRole } from "@/components/RequireRole";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/proprietaire")({
  component: OwnerLayout,
});

const NAV = [
  { to: "/proprietaire", label: "Tableau de bord", icon: LayoutDashboard, exact: true },
  { to: "/proprietaire/materiels", label: "Mes matériels", icon: Package, exact: false },
  { to: "/proprietaire/demandes", label: "Demandes", icon: ClipboardList, exact: false },
] as const;

function OwnerLayout() {
  return (
    <RequireRole roles={["proprietaire"]}>
      <div className="container-page grid gap-8 py-8 lg:grid-cols-[220px_1fr]">
        <aside className="hidden lg:block">
          <nav aria-label="Espace propriétaire" className="sticky top-24 space-y-1">
            {NAV.map((n) => (
              <Link
                key={n.to}
                to={n.to}
                activeOptions={{ exact: n.exact }}
                className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                activeProps={{ className: "!text-primary bg-primary-soft" }}
              >
                <n.icon className="size-4" aria-hidden /> {n.label}
              </Link>
            ))}
            <Button asChild variant="highlight" className="mt-4 w-full">
              <Link to="/proprietaire/materiels/nouveau"><Plus aria-hidden /> Ajouter un matériel</Link>
            </Button>
          </nav>
        </aside>
        <div className="min-w-0 pb-20 lg:pb-0">
          <Outlet />
        </div>
      </div>
      <nav aria-label="Espace propriétaire (mobile)" className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-3 border-t bg-card/95 backdrop-blur lg:hidden">
        {NAV.map((n) => (
          <Link
            key={n.to}
            to={n.to}
            activeOptions={{ exact: n.exact }}
            className="flex flex-col items-center gap-1 py-2.5 text-xs font-semibold text-muted-foreground"
            activeProps={{ className: "!text-primary" }}
          >
            <n.icon className="size-5" aria-hidden /> {n.label}
          </Link>
        ))}
      </nav>
    </RequireRole>
  );
}
