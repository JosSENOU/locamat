import { createFileRoute, Link, Outlet } from "@tanstack/react-router";
import { ClipboardList, LayoutDashboard, Package, Users } from "lucide-react";
import { RequireRole } from "@/components/RequireRole";

export const Route = createFileRoute("/admin")({
  component: AdminLayout,
});

const NAV = [
  { to: "/admin", label: "Vue d’ensemble", icon: LayoutDashboard, exact: true },
  { to: "/admin/utilisateurs", label: "Comptes", icon: Users, exact: false },
  { to: "/admin/materiels", label: "Matériels", icon: Package, exact: false },
  { to: "/admin/locations", label: "Locations", icon: ClipboardList, exact: false },
] as const;

function AdminLayout() {
  return (
    <RequireRole roles={["admin"]}>
      <div className="container-page grid gap-8 py-8 lg:grid-cols-[230px_1fr]">
        <aside className="hidden lg:block">
          <nav aria-label="Espace administration" className="sticky top-24 space-y-1">
            {NAV.map((item) => (
              <Link
                key={item.to}
                to={item.to}
                activeOptions={{ exact: item.exact }}
                className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                activeProps={{ className: "!text-primary bg-primary-soft" }}
              >
                <item.icon className="size-4" aria-hidden />
                {item.label}
              </Link>
            ))}
            <div className="mt-5 rounded-xl border bg-card p-4">
              <p className="text-sm font-bold">Administration LocaMat</p>
              <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                Les changements de rôle et de visibilité sont immédiats. Les données ne sont jamais
                supprimées.
              </p>
            </div>
          </nav>
        </aside>
        <main className="min-w-0 pb-20 lg:pb-0">
          <Outlet />
        </main>
      </div>
      <nav
        aria-label="Espace administration (mobile)"
        className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t bg-card/95 backdrop-blur lg:hidden"
      >
        {NAV.map((item) => (
          <Link
            key={item.to}
            to={item.to}
            activeOptions={{ exact: item.exact }}
            className="flex flex-col items-center gap-1 py-2.5 text-[11px] font-semibold text-muted-foreground"
            activeProps={{ className: "!text-primary" }}
          >
            <item.icon className="size-5" aria-hidden />
            {item.label}
          </Link>
        ))}
      </nav>
    </RequireRole>
  );
}
