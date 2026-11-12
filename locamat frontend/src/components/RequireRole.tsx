import { useEffect, type ReactNode } from "react";
import { Link, useLocation, useNavigate } from "@tanstack/react-router";
import { ShieldAlert } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import type { AccountRole } from "@/types/api";
import { FullPageLoader } from "./FullPageLoader";
import { Button } from "./ui/button";
import { homeFor, userHasRole } from "@/lib/constants";

/**
 * Client-side guard: redirects guests to the login page and shows a 403 for other roles.
 * `loginTo="super-admin"` sends guests to the dedicated super-administrator login page.
 */
export function RequireRole({
  roles,
  loginTo = "public",
  children,
}: {
  roles?: AccountRole[];
  loginTo?: "public" | "super-admin";
  children: ReactNode;
}) {
  const { user, status } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    if (status === "ready" && !user) {
      if (loginTo === "super-admin") {
        void navigate({ to: "/super-admin/connexion", replace: true });
        return;
      }
      void navigate({ to: "/connexion", search: { redirect: location.href }, replace: true });
    }
  }, [status, user, navigate, location.href, loginTo]);

  if (status !== "ready" || !user) return <FullPageLoader />;
  if (roles && !roles.some((role) => userHasRole(user, role))) {
    return <Forbidden home={homeFor(user.role)} />;
  }
  return <>{children}</>;
}

export function Forbidden({ home = "/" }: { home?: string }) {
  return (
    <div className="container-page flex min-h-[60vh] items-center justify-center py-16">
      <div className="max-w-md text-center">
        <div className="mx-auto mb-5 grid size-16 place-items-center rounded-2xl bg-destructive/10 text-destructive">
          <ShieldAlert className="size-8" aria-hidden />
        </div>
        <p className="text-5xl font-extrabold text-primary">403</p>
        <h1 className="mt-3 text-xl font-bold">Accès refusé</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Cette page n'est pas accessible avec votre type de compte.
        </p>
        <Button asChild className="mt-6">
          <Link to={home}>Retour à mon espace</Link>
        </Button>
      </div>
    </div>
  );
}
