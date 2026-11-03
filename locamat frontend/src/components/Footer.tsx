import { Link } from "@tanstack/react-router";
import { Logo } from "./Logo";

export function Footer() {
  return (
    <footer className="mt-16 border-t bg-card">
      <div className="container-page flex flex-col gap-6 py-10 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-2">
          <Logo />
          <p className="max-w-sm text-sm text-muted-foreground">
            Le matériel qui dort chez les uns sert les projets des autres. Location entre particuliers, simple et suivie.
          </p>
        </div>
        <nav aria-label="Liens de pied de page" className="flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-muted-foreground">
          <Link to="/" className="hover:text-foreground">Catalogue</Link>
          <Link to="/inscription" className="hover:text-foreground">Proposer mon matériel</Link>
          <Link to="/connexion" className="hover:text-foreground">Connexion</Link>
        </nav>
      </div>
      <div className="border-t py-4 text-center text-xs text-muted-foreground">
        © {new Date().getFullYear()} LocaMat. Tous droits réservés.
      </div>
    </footer>
  );
}
