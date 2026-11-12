import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { LoaderCircle, PackageSearch, Search, SlidersHorizontal, X } from "lucide-react";
import { useCatalogue } from "@/hooks/use-materiels";
import { CATEGORIES } from "@/lib/constants";
import { seo } from "@/lib/seo";
import { MaterielCard, MaterielCardSkeleton } from "@/components/MaterielCard";
import { EmptyState, ErrorState } from "@/components/States";
import { PaginationBar } from "@/components/PaginationBar";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface CatalogueSearch {
  q?: string;
  categorie?: string;
  prix_min?: number;
  prix_max?: number;
  page?: number;
}

function num(v: unknown): number | undefined {
  const n = typeof v === "number" ? v : typeof v === "string" && v !== "" ? Number(v) : NaN;
  return Number.isFinite(n) && n >= 0 ? n : undefined;
}

export const Route = createFileRoute("/")({
  validateSearch: (s: Record<string, unknown>): CatalogueSearch => ({
    q: typeof s.q === "string" && s.q ? s.q : undefined,
    categorie: typeof s.categorie === "string" && s.categorie ? s.categorie : undefined,
    prix_min: num(s.prix_min),
    prix_max: num(s.prix_max),
    page: num(s.page) && num(s.page)! >= 1 ? num(s.page) : undefined,
  }),
  head: () =>
    seo(
      "LocaMat",
      "Trouvez et louez du matériel informatique, photo, vidéo, vidéoprojection et sonorisation entre particuliers, en FCFA.",
    ),
  component: Catalogue,
});

function Catalogue() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/" });
  const { data, isPending, isError, error, refetch, isPlaceholderData, isFetching } =
    useCatalogue(search);

  const [q, setQ] = useState(search.q ?? "");
  const [min, setMin] = useState(search.prix_min?.toString() ?? "");
  const [max, setMax] = useState(search.prix_max?.toString() ?? "");
  const [priceError, setPriceError] = useState<string | null>(null);

  useEffect(() => setQ(search.q ?? ""), [search.q]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const keyword = q.trim() || undefined;
      if (keyword !== search.q)
        void navigate({ search: (previous) => ({ ...previous, q: keyword, page: undefined }) });
    }, 250);
    return () => window.clearTimeout(timer);
  }, [q, search.q, navigate]);

  const update = (patch: Partial<CatalogueSearch>) =>
    void navigate({ search: (prev) => ({ ...prev, ...patch, page: patch.page }) });

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    const pmin = min === "" ? undefined : Number(min);
    const pmax = max === "" ? undefined : Number(max);
    if (pmin !== undefined && pmax !== undefined && pmax < pmin) {
      setPriceError("Le prix maximum doit être supérieur ou égal au prix minimum.");
      return;
    }
    setPriceError(null);
    update({ q: q.trim() || undefined, prix_min: pmin, prix_max: pmax });
  };

  const hasFilters = !!(search.q || search.categorie || search.prix_min !== undefined || search.prix_max !== undefined);
  const reset = () => {
    setQ("");
    setMin("");
    setMax("");
    setPriceError(null);
    void navigate({ search: {} });
  };

  return (
    <>
      <section className="bg-hero border-b">
        <div className="container-page py-12 sm:py-16">
          <p className="mb-3 inline-flex rounded-full bg-highlight px-3 py-1 text-xs font-bold uppercase tracking-wider text-highlight-foreground">
            Location entre particuliers
          </p>
          <h1 className="max-w-2xl text-3xl font-extrabold leading-tight text-primary-foreground sm:text-5xl">
            Le bon matériel, <span className="text-highlight">juste le temps</span> qu'il vous faut.
          </h1>
          <p className="mt-4 max-w-xl text-primary-foreground/80 sm:text-lg">
            Ordinateurs, appareils photo, vidéoprojecteurs, sono : louez près de chez vous, au jour.
          </p>

          <form onSubmit={onSubmit} className="mt-8 rounded-2xl border bg-card p-3 shadow-elevated sm:p-4" role="search">
            <div className="flex flex-col gap-3 lg:flex-row">
              <div className="relative flex-1">
                {isFetching ? (
                  <LoaderCircle
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                    aria-hidden
                  />
                ) : (
                  <Search
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                  />
                )}
                <Input
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  placeholder="Nom ou mot-clé : appareil photo, enceinte, MacBook…"
                  aria-label="Rechercher un matériel par nom ou mot-clé"
                  aria-busy={isFetching}
                  className="h-11 pr-10 pl-9"
                />
                {q && (
                  <button
                    type="button"
                    className="absolute right-1 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    onClick={() => {
                      setQ("");
                      update({ q: undefined });
                    }}
                    aria-label="Effacer la recherche"
                  >
                    <X className="size-4" aria-hidden />
                  </button>
                )}
              </div>
              <div className="flex gap-2">
                <Input
                  type="number"
                  min={0}
                  inputMode="numeric"
                  value={min}
                  onChange={(e) => setMin(e.target.value)}
                  placeholder="Prix min"
                  aria-label="Prix minimum par jour (FCFA)"
                  className="h-11 lg:w-32"
                />
                <Input
                  type="number"
                  min={0}
                  inputMode="numeric"
                  value={max}
                  onChange={(e) => setMax(e.target.value)}
                  placeholder="Prix max"
                  aria-label="Prix maximum par jour (FCFA)"
                  aria-invalid={priceError ? true : undefined}
                  className="h-11 lg:w-32"
                />
              </div>
              <Button type="submit" size="lg" className="h-11">
                <SlidersHorizontal aria-hidden /> Rechercher
              </Button>
            </div>
            {priceError && (
              <p role="alert" className="mt-2 text-xs font-medium text-destructive">
                {priceError}
              </p>
            )}
          </form>
        </div>
      </section>

      <section className="container-page py-8">
        <div className="-mx-1 mb-6 flex items-center gap-2 overflow-x-auto px-1 pb-1">
          <CategoryChip active={!search.categorie} onClick={() => update({ categorie: undefined })}>
            Toutes
          </CategoryChip>
          {CATEGORIES.map((c) => (
            <CategoryChip key={c} active={search.categorie === c} onClick={() => update({ categorie: c })}>
              {c}
            </CategoryChip>
          ))}
        </div>

        <div className="mb-4 flex items-center justify-between gap-3">
          <p className="text-sm text-muted-foreground" aria-live="polite" role="status">
            {data ? `${data.meta.total} matériel${data.meta.total > 1 ? "s" : ""} disponible${data.meta.total > 1 ? "s" : ""}` : "\u00a0"}
          </p>
          {hasFilters && (
            <Button variant="ghost" size="sm" onClick={reset}>
              <X aria-hidden /> Effacer les filtres
            </Button>
          )}
        </div>

        {isPending ? (
          <Grid>
            {Array.from({ length: 8 }, (_, i) => (
              <MaterielCardSkeleton key={i} />
            ))}
          </Grid>
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : data.data.length === 0 ? (
          <EmptyState
            icon={PackageSearch}
            title="Aucun matériel trouvé"
            description={hasFilters ? "Essayez d'autres mots-clés ou élargissez la fourchette de prix." : "Aucun matériel n'est disponible pour le moment. Revenez bientôt !"}
            action={hasFilters ? <Button variant="outline" onClick={reset}>Effacer les filtres</Button> : undefined}
          />
        ) : (
          <>
            <Grid className={cn(isPlaceholderData && "opacity-60 transition-opacity")}>
              {data.data.map((m) => (
                <MaterielCard key={m.id} materiel={m} />
              ))}
            </Grid>
            <PaginationBar
              meta={data.meta}
              onPage={(p) => {
                update({ page: p === 1 ? undefined : p });
                window.scrollTo({ top: 0, behavior: "smooth" });
              }}
            />
          </>
        )}
      </section>
    </>
  );
}

function Grid({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn("grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4", className)}>{children}</div>;
}

function CategoryChip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "shrink-0 rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        active ? "border-primary bg-primary text-primary-foreground" : "bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground",
      )}
    >
      {children}
    </button>
  );
}
