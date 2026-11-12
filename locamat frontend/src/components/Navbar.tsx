import { useState } from "react";
import { Link } from "@tanstack/react-router";
import { LogOut, Menu, UserRound } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { Logo } from "./Logo";
import { NotificationBell } from "./NotificationBell";
import { ProfileAvatar } from "./ProfileAvatar";
import { Button } from "./ui/button";
import { userCanRent, userHasRole } from "@/lib/constants";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "./ui/sheet";

interface NavLink {
  to: string;
  label: string;
  exact?: boolean;
}

function useLinks(): NavLink[] {
  const { user } = useAuth();
  const links: NavLink[] = [{ to: "/", label: "Louer du matériel", exact: true }];

  if (userHasRole(user, "proprietaire")) {
    links.push(
      { to: "/proprietaire", label: "Tableau de bord", exact: true },
      { to: "/proprietaire/materiels", label: "Mes matériels" },
      { to: "/proprietaire/demandes", label: "Demandes" },
    );
  }
  if (userCanRent(user)) {
    links.push({ to: "/mes-locations", label: "Mes locations" });
  }
  if (userHasRole(user, "admin")) {
    links.push({ to: "/admin", label: "Administration" });
  }
  if (userHasRole(user, "super_admin")) {
    links.push({ to: "/super-admin", label: "Super-administration" });
  }

  return links;
}

const linkCls =
  "rounded-lg px-3 py-2 text-sm font-semibold text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary";
const activeCls = "!text-primary bg-primary-soft";

export function Navbar() {
  const { user, logout } = useAuth();
  const links = useLinks();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-primary/10 bg-background/95 shadow-sm backdrop-blur-xl">
      <div className="container-page flex h-[4.5rem] items-center gap-3 sm:gap-4">
        <Link
          to="/"
          aria-label="LocaMat, accueil"
          className="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <Logo />
        </Link>

        <nav
          aria-label="Navigation principale"
          className="ml-2 hidden items-center gap-0.5 xl:flex"
        >
          {links.map((l) => (
            <Link
              key={l.to}
              to={l.to}
              className={linkCls}
              activeProps={{ className: activeCls }}
              activeOptions={{ exact: l.exact }}
            >
              {l.label}
            </Link>
          ))}
        </nav>

        <div className="ml-auto flex items-center gap-2">
          {user && (userHasRole(user, "proprietaire") || userCanRent(user)) && <NotificationBell />}
          {user ? (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="gap-2 px-2" aria-label="Menu du compte">
                  <ProfileAvatar name={user.name} photo={user.photo} className="size-8 text-xs" />
                  <span className="hidden max-w-32 truncate text-sm font-semibold sm:inline">
                    {user.name}
                  </span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                  <p className="truncate text-sm font-semibold">{user.name}</p>
                  <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                  <Link to="/profil">
                    <UserRound aria-hidden /> Profil
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => void logout()}>
                  <LogOut aria-hidden /> Déconnexion
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          ) : (
            <div className="hidden items-center gap-2 xl:flex">
              <Button variant="ghost" asChild>
                <Link to="/connexion">Connexion</Link>
              </Button>
              <Button asChild>
                <Link to="/inscription" search={{ role: "locataire" }}>
                  M'inscrire pour louer
                </Link>
              </Button>
            </div>
          )}

          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="xl:hidden" aria-label="Ouvrir le menu">
                <Menu />
              </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-72">
              <SheetHeader>
                <SheetTitle>
                  <Logo />
                </SheetTitle>
              </SheetHeader>
              <nav aria-label="Navigation mobile" className="mt-6 flex flex-col gap-1 px-4">
                {links.map((l) => (
                  <Link
                    key={l.to}
                    to={l.to}
                    onClick={() => setOpen(false)}
                    className={linkCls}
                    activeProps={{ className: activeCls }}
                    activeOptions={{ exact: l.exact }}
                  >
                    {l.label}
                  </Link>
                ))}
                {!user && (
                  <div className="mt-4 flex flex-col gap-2">
                    <Button variant="outline" asChild onClick={() => setOpen(false)}>
                      <Link to="/connexion">Connexion</Link>
                    </Button>
                    <Button asChild onClick={() => setOpen(false)}>
                      <Link to="/inscription" search={{ role: "locataire" }}>
                        M'inscrire pour louer
                      </Link>
                    </Button>
                  </div>
                )}
              </nav>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </header>
  );
}
