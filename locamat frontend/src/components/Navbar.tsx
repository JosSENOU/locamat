import { useState } from "react";
import { Link } from "@tanstack/react-router";
import { LogOut, Menu, UserRound } from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { Logo } from "./Logo";
import { ProfileAvatar } from "./ProfileAvatar";
import { Button } from "./ui/button";
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
  if (user?.role === "proprietaire")
    return [
      { to: "/", label: "Catalogue", exact: true },
      { to: "/proprietaire", label: "Tableau de bord", exact: true },
      { to: "/proprietaire/materiels", label: "Mes matériels" },
      { to: "/proprietaire/demandes", label: "Demandes" },
    ];
  if (user?.role === "locataire")
    return [
      { to: "/", label: "Catalogue", exact: true },
      { to: "/mes-locations", label: "Mes locations" },
    ];
  if (user?.role === "admin")
    return [
      { to: "/", label: "Catalogue", exact: true },
      { to: "/admin", label: "Administration" },
    ];
  return [{ to: "/", label: "Catalogue", exact: true }];
}

const linkCls =
  "rounded-lg px-3 py-2 text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground";
const activeCls = "!text-primary bg-primary-soft";

export function Navbar() {
  const { user, logout } = useAuth();
  const links = useLinks();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b bg-background/85 backdrop-blur">
      <div className="container-page flex h-16 items-center gap-4">
        <Link
          to="/"
          aria-label="LocaMat, accueil"
          className="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <Logo />
        </Link>

        <nav aria-label="Navigation principale" className="ml-4 hidden items-center gap-1 md:flex">
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
            <div className="hidden items-center gap-2 sm:flex">
              <Button variant="ghost" asChild>
                <Link to="/connexion">Connexion</Link>
              </Button>
              <Button asChild>
                <Link to="/inscription">Créer un compte</Link>
              </Button>
            </div>
          )}

          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="md:hidden" aria-label="Ouvrir le menu">
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
                      <Link to="/inscription">Créer un compte</Link>
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
