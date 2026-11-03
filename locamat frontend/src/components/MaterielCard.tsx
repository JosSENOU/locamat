import { Link } from "@tanstack/react-router";
import { UserRound } from "lucide-react";
import type { Materiel } from "@/types/api";
import { MaterielPhoto } from "./MaterielPhoto";
import { EtatBadge } from "./Badges";
import { PriceTag } from "./PriceTag";
import { Skeleton } from "./ui/skeleton";

export function MaterielCard({ materiel }: { materiel: Materiel }) {
  return (
    <Link
      to="/materiels/$id"
      params={{ id: String(materiel.id) }}
      className="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:shadow-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <div className="relative">
        <MaterielPhoto
          categorie={materiel.categorie}
          photo={materiel.photo}
          alt={materiel.nom}
          className="aspect-[4/3] transition-transform duration-300 [&_img]:group-hover:scale-105 [&_img]:transition-transform [&_img]:duration-500"
        />
        <EtatBadge etat={materiel.etat} className="absolute left-3 top-3 shadow-sm backdrop-blur" />
      </div>
      <div className="flex flex-1 flex-col gap-2 p-4">
        <p className="text-xs font-semibold uppercase tracking-wider text-primary">{materiel.categorie}</p>
        <h3 className="line-clamp-2 font-bold leading-snug text-foreground">{materiel.nom}</h3>
        <div className="mt-auto flex items-end justify-between gap-2 pt-2">
          <PriceTag value={materiel.prix_journalier} />
        </div>
        {materiel.proprietaire && (
          <p className="flex items-center gap-1.5 border-t pt-3 text-xs text-muted-foreground">
            <UserRound className="size-3.5" aria-hidden />
            {materiel.proprietaire.name}
          </p>
        )}
      </div>
    </Link>
  );
}

export function MaterielCardSkeleton() {
  return (
    <div className="overflow-hidden rounded-xl border bg-card shadow-card" aria-hidden>
      <Skeleton className="aspect-[4/3] rounded-none" />
      <div className="space-y-3 p-4">
        <Skeleton className="h-3 w-20" />
        <Skeleton className="h-5 w-4/5" />
        <Skeleton className="h-6 w-28" />
        <Skeleton className="h-3 w-24" />
      </div>
    </div>
  );
}
