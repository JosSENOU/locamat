import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import { Link, createFileRoute, useNavigate } from "@tanstack/react-router";
import {
  ArrowRight,
  BadgeCheck,
  CalendarCheck,
  KeyRound,
  LoaderCircle,
  MapPin,
  MessageCircle,
  PackageSearch,
  Search,
  Sparkles,
  X,
} from "lucide-react";
import { useAuth } from "@/hooks/use-auth";
import { useCatalogue, useCategories } from "@/hooks/use-materiels";
import { userHasRole } from "@/lib/constants";
import { categoryImage } from "@/lib/category-images";
import { seo } from "@/lib/seo";
import { MaterielCard, MaterielCardSkeleton } from "@/components/MaterielCard";
import { EmptyState, ErrorState } from "@/components/States";
import { PaginationBar } from "@/components/PaginationBar";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import heroPhoto from "@/assets/images/categorie-photo.jpg";
import heroInformatique from "@/assets/images/categorie-informatique.jpg";
import heroSonorisation from "@/assets/images/categorie-sonorisation.jpg";

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

function scrollToCatalogue() {
  window.requestAnimationFrame(() =>
    document.getElementById("catalogue")?.scrollIntoView({ behavior: "smooth", block: "start" }),
  );
}

const STEPS = [
  {
    icon: Search,
    title: "Trouvez",
    text: "Parcourez le catalogue et filtrez par catégorie ou par prix par jour.",
  },
  {
    icon: CalendarCheck,
    title: "Réservez",
    text: "Choisissez vos dates : le total en FCFA s'affiche avant d'envoyer votre demande.",
  },
  {
    icon: KeyRound,
    title: "Récupérez",
    text: "Après confirmation, récupérez le matériel et profitez-en pendant la durée réservée.",
  },
] as const;

const HERO_PHOTOS = [
  { src: heroPhoto, label: "Photo et vidéo" },
  { src: heroInformatique, label: "Matériel informatique" },
  { src: heroSonorisation, label: "Sonorisation" },
] as const;

function Catalogue() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/" });
  const { user } = useAuth();
  const categories = useCategories();
  const { data, isPending, isError, error, refetch, isPlaceholderData, isFetching } =
    useCatalogue(search);

  const [q, setQ] = useState(search.q ?? "");
  const [min, setMin] = useState(search.prix_min?.toString() ?? "");
  const [max, setMax] = useState(search.prix_max?.toString() ?? "");
  const [priceError, setPriceError] = useState<string | null>(null);
  const [heroPhotoIndex, setHeroPhotoIndex] = useState(0);
  const currentHero = HERO_PHOTOS[heroPhotoIndex] ?? HERO_PHOTOS[0];

  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const timer = window.setInterval(
      () => setHeroPhotoIndex((current) => (current + 1) % HERO_PHOTOS.length),
      5000,
    );
    return () => window.clearInterval(timer);
  }, []);

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

  const onSearchSubmit = (e: FormEvent) => {
    e.preventDefault();
    update({ q: q.trim() || undefined });
    scrollToCatalogue();
  };

  const onPriceSubmit = (e: FormEvent) => {
    e.preventDefault();
    const pmin = min === "" ? undefined : Number(min);
    const pmax = max === "" ? undefined : Number(max);
    if (pmin !== undefined && pmax !== undefined && pmax < pmin) {
      setPriceError("Le prix maximum doit être supérieur ou égal au prix minimum.");
      return;
    }
    setPriceError(null);
    update({ prix_min: pmin, prix_max: pmax });
  };

  const hasFilters = !!(
    search.q ||
    search.categorie ||
    search.prix_min !== undefined ||
    search.prix_max !== undefined
  );

  const reset = () => {
    setQ("");
    setMin("");
    setMax("");
    setPriceError(null);
    void navigate({ search: {} });
  };

  const pickCategory = (c: string) => {
    update({ categorie: search.categorie === c ? undefined : c });
    scrollToCatalogue();
  };

  const isOwner = userHasRole(user, "proprietaire");

  return (
    <>
      {/* ------------------------------------------------------------ HERO */}
      <section className="relative isolate overflow-hidden bg-[#f5f5ef]">
        <div
          aria-hidden
          className="pointer-events-none absolute -right-36 -top-40 size-[34rem] rounded-full bg-highlight/15 blur-3xl"
        />
        <div
          aria-hidden
          className="pointer-events-none absolute -bottom-56 -left-36 size-[32rem] rounded-full bg-primary/10 blur-3xl"
        />

        <div className="container-page relative grid items-center gap-8 pb-12 pt-9 sm:gap-10 sm:pb-16 sm:pt-14 lg:min-h-[620px] lg:grid-cols-[1.03fr_0.97fr] lg:gap-6 lg:pb-20 lg:pt-16">
          <div className="relative z-10 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-4 motion-safe:duration-700">
            <p className="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-white/80 px-3.5 py-2 text-xs font-bold text-primary shadow-sm">
              <Sparkles className="size-3.5 text-highlight" aria-hidden />
              La location, simplement
            </p>

            <h1 className="mt-6 max-w-2xl text-4xl font-extrabold leading-[1.04] tracking-tight text-foreground sm:text-5xl lg:text-[4.25rem]">
              Louez le bon matériel,
              <br />
              <span className="text-primary">juste le temps qu’il vous faut.</span>
            </h1>

            <p className="mt-5 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-lg">
              Trouvez du matériel près de chez vous, choisissez vos dates et échangez directement
              avec son propriétaire.
            </p>

            <form
              onSubmit={onSearchSubmit}
              role="search"
              className="mt-7 flex max-w-xl items-center gap-2 rounded-2xl border border-border/80 bg-white p-2 shadow-[0_18px_50px_-24px_rgba(12,65,58,0.3)] transition-shadow focus-within:ring-4 focus-within:ring-primary/10 sm:rounded-full"
            >
              <div className="relative min-w-0 flex-1">
                {isFetching ? (
                  <LoaderCircle
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-primary"
                    aria-hidden
                  />
                ) : (
                  <Search
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary"
                    aria-hidden
                  />
                )}
                <Input
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  placeholder="Que souhaitez-vous louer ?"
                  aria-label="Rechercher un matériel à louer par nom ou mot-clé"
                  aria-busy={isFetching}
                  className="h-11 border-0 bg-transparent pl-10 pr-8 shadow-none focus-visible:ring-0"
                />
                {q && (
                  <button
                    type="button"
                    className="absolute right-0 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
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
              <Button
                type="submit"
                size="lg"
                className="h-11 shrink-0 rounded-xl px-4 sm:rounded-full sm:px-6"
              >
                Trouver du matériel
              </Button>
            </form>

            <div className="mt-5 flex flex-wrap items-center gap-2 text-sm">
              <span className="mr-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Explorer
              </span>
              {(categories.data ?? []).slice(0, 4).map(({ name }) => (
                <button
                  key={name}
                  type="button"
                  onClick={() => pickCategory(name)}
                  className="rounded-full border border-primary/15 bg-white/80 px-3 py-1.5 text-xs font-semibold text-foreground transition-all hover:border-primary/40 hover:bg-primary-soft hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                  {name}
                </button>
              ))}
            </div>

            <div className="mt-7 flex flex-wrap gap-x-5 gap-y-3 border-t border-foreground/10 pt-5 text-xs font-medium text-muted-foreground sm:text-sm">
              <span className="inline-flex items-center gap-2">
                <CalendarCheck className="size-4 text-primary" aria-hidden />
                Dates choisies librement
              </span>
              <span className="inline-flex items-center gap-2">
                <BadgeCheck className="size-4 text-primary" aria-hidden />
                Échanges directs
              </span>
              <span className="inline-flex items-center gap-2">
                <MapPin className="size-4 text-primary" aria-hidden />
                Partout au Bénin
              </span>
            </div>
          </div>

          <div
            role="group"
            aria-label="Photos de matériel à louer"
            className="relative mx-auto w-full max-w-[540px]"
          >
            <div className="absolute -inset-4 rounded-[2.5rem] bg-primary/5 sm:-inset-6" />
            <div className="relative h-[260px] overflow-hidden rounded-[1.75rem] bg-primary/10 shadow-[0_30px_70px_-30px_rgba(12,65,58,0.45)] sm:h-[360px] sm:rounded-[2.25rem] lg:h-[450px]">
              <img
                key={currentHero.src}
                src={currentHero.src}
                alt=""
                className="absolute inset-0 size-full animate-in fade-in object-cover duration-700 motion-reduce:animate-none"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-[#102f2b]/65 via-transparent to-transparent" />
              <div className="absolute bottom-5 left-5 right-5 flex items-end justify-between gap-3 text-white sm:bottom-7 sm:left-7 sm:right-7">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">
                    {currentHero.label}
                  </p>
                  <p className="mt-1 text-xl font-extrabold sm:text-2xl">
                    Louez ce qu’il vous faut.
                  </p>
                  <div className="mt-3 flex items-center gap-2">
                    {HERO_PHOTOS.map((photo, index) => (
                      <button
                        key={photo.label}
                        type="button"
                        aria-label={`Afficher la photo : ${photo.label}`}
                        aria-pressed={heroPhotoIndex === index}
                        onClick={() => setHeroPhotoIndex(index)}
                        className={cn(
                          "h-2.5 rounded-full bg-white transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary",
                          heroPhotoIndex === index ? "w-6" : "w-2.5 opacity-65 hover:opacity-100",
                        )}
                      />
                    ))}
                  </div>
                </div>
                <button
                  type="button"
                  aria-label="Afficher la photo suivante"
                  onClick={() => setHeroPhotoIndex((current) => (current + 1) % HERO_PHOTOS.length)}
                  className="grid size-11 shrink-0 place-items-center rounded-full bg-white/15 backdrop-blur-md transition-colors hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                  <ArrowRight className="size-5" />
                </button>
              </div>
            </div>

            <div className="absolute -left-2 top-5 flex items-center gap-3 rounded-2xl border border-white/70 bg-white/95 p-2.5 pr-4 shadow-elevated sm:-left-7 sm:top-12 sm:gap-3.5 sm:p-3 sm:pr-5">
              <img
                src={categoryImage("Informatique") ?? undefined}
                alt=""
                className="size-11 rounded-xl object-cover sm:size-14"
              />
              <span className="text-xs font-bold leading-snug text-foreground sm:text-sm">
                Du matériel à louer
                <span className="mt-0.5 block font-medium text-muted-foreground">
                  pour tous vos projets
                </span>
              </span>
            </div>

            <div className="absolute -right-1 bottom-16 flex items-center gap-2.5 rounded-2xl border border-white/70 bg-white/95 px-3 py-2.5 shadow-elevated sm:-right-5 sm:bottom-20 sm:gap-3 sm:px-4 sm:py-3">
              <span className="grid size-9 place-items-center rounded-xl bg-[#e8f4eb] text-[#28844f] sm:size-10">
                <MessageCircle className="size-4 sm:size-[18px]" />
              </span>
              <span className="text-xs font-bold leading-snug text-foreground sm:text-sm">
                Contact direct
                <span className="block font-medium text-muted-foreground">
                  avec le propriétaire
                </span>
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* ------------------------------------------------------ CATÉGORIES */}
      <section
        className="container-page relative z-10 py-8 sm:py-10"
        aria-labelledby="titre-categories"
      >
        <div className="rounded-3xl border bg-card p-4 shadow-elevated sm:p-6">
          <div className="mb-4 flex items-end justify-between gap-3">
            <div>
              <h2 id="titre-categories" className="text-xl font-extrabold sm:text-2xl">
                Explorer par catégorie
              </h2>
              <p className="text-sm text-muted-foreground">Un clic pour filtrer le catalogue.</p>
            </div>
            {search.categorie && (
              <Button variant="ghost" size="sm" onClick={() => update({ categorie: undefined })}>
                <X aria-hidden /> Toutes
              </Button>
            )}
          </div>
          {categories.isError ? (
            <ErrorState error={categories.error} onRetry={() => void categories.refetch()} />
          ) : categories.isPending ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {Array.from({ length: 4 }, (_, index) => (
                <div key={index} className="aspect-[4/3] animate-pulse rounded-2xl bg-muted" />
              ))}
            </div>
          ) : categories.data.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              Aucune catégorie n’est disponible pour le moment.
            </p>
          ) : (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {categories.data.map(({ name }) => {
                const active = search.categorie === name;
                const img = categoryImage(name);
                return (
                  <button
                    key={name}
                    type="button"
                    onClick={() => pickCategory(name)}
                    aria-pressed={active}
                    className={cn(
                      "group relative aspect-[4/3] overflow-hidden rounded-2xl bg-muted text-left transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                      active
                        ? "ring-2 ring-primary ring-offset-2"
                        : "hover:-translate-y-0.5 hover:shadow-card",
                    )}
                  >
                    {img && (
                      <img
                        src={img}
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-110"
                      />
                    )}
                    <span
                      aria-hidden
                      className={cn(
                        "absolute inset-0 bg-gradient-to-t via-black/15 to-transparent transition-colors",
                        active ? "from-primary/90" : "from-black/75",
                      )}
                    />
                    <span className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 p-3 text-sm font-bold text-white sm:text-base">
                      {name}
                      <ArrowRight
                        className="size-4 -translate-x-1 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                        aria-hidden
                      />
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </section>

      {/* ------------------------------------------------- COMMENT ÇA MARCHE */}
      <section className="container-page py-16 sm:py-20" aria-labelledby="titre-etapes">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-xs font-bold uppercase tracking-widest text-primary">
            Simple et suivi
          </p>
          <h2 id="titre-etapes" className="mt-2 text-2xl font-extrabold sm:text-4xl">
            Louer, c'est trois étapes
          </h2>
        </div>
        <ol className="mt-10 grid gap-5 md:grid-cols-3">
          {STEPS.map((s, i) => (
            <li
              key={s.title}
              className="relative overflow-hidden rounded-2xl border bg-card p-6 shadow-card"
            >
              <span
                aria-hidden
                className="absolute -right-2 -top-4 select-none text-8xl font-extrabold text-primary/[0.07]"
              >
                {i + 1}
              </span>
              <span className="grid size-12 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
                <s.icon className="size-5" aria-hidden />
              </span>
              <h3 className="mt-5 text-lg font-bold">{s.title}</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{s.text}</p>
            </li>
          ))}
        </ol>
      </section>

      {/* -------------------------------------------------------- CATALOGUE */}
      <section
        id="catalogue"
        className="container-page scroll-mt-20 pb-6"
        aria-labelledby="titre-catalogue"
      >
        <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 id="titre-catalogue" className="text-2xl font-extrabold sm:text-3xl">
              Matériel disponible
            </h2>
            <p className="mt-1 text-sm text-muted-foreground" aria-live="polite" role="status">
              {data
                ? `${data.meta.total} matériel${data.meta.total > 1 ? "s" : ""} disponible${data.meta.total > 1 ? "s" : ""}`
                : "\u00a0"}
            </p>
          </div>

          <form
            onSubmit={onPriceSubmit}
            className="flex flex-wrap items-center gap-2"
            aria-label="Filtrer par prix"
          >
            <Input
              type="number"
              min={0}
              inputMode="numeric"
              value={min}
              onChange={(e) => setMin(e.target.value)}
              placeholder="Prix min"
              aria-label="Prix minimum par jour (FCFA)"
              className="h-10 w-28 bg-card"
            />
            <span aria-hidden className="text-muted-foreground">
              –
            </span>
            <Input
              type="number"
              min={0}
              inputMode="numeric"
              value={max}
              onChange={(e) => setMax(e.target.value)}
              placeholder="Prix max"
              aria-label="Prix maximum par jour (FCFA)"
              aria-invalid={priceError ? true : undefined}
              className="h-10 w-28 bg-card"
            />
            <Button type="submit" variant="outline">
              Appliquer
            </Button>
            {hasFilters && (
              <Button type="button" variant="ghost" onClick={reset}>
                <X aria-hidden /> Effacer
              </Button>
            )}
          </form>
        </div>
        {priceError && (
          <p role="alert" className="-mt-3 mb-4 text-xs font-medium text-destructive lg:text-right">
            {priceError}
          </p>
        )}

        <div className="-mx-1 mb-6 flex items-center gap-2 overflow-x-auto px-1 pb-1">
          <CategoryChip active={!search.categorie} onClick={() => update({ categorie: undefined })}>
            Toutes
          </CategoryChip>
          {(categories.data ?? []).map(({ name }) => (
            <CategoryChip
              key={name}
              active={search.categorie === name}
              onClick={() => update({ categorie: name })}
            >
              {name}
            </CategoryChip>
          ))}
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
            description={
              hasFilters
                ? "Essayez d'autres mots-clés ou élargissez la fourchette de prix."
                : "Aucun matériel n'est disponible pour le moment. Revenez bientôt !"
            }
            action={
              hasFilters ? (
                <Button variant="outline" onClick={reset}>
                  Effacer les filtres
                </Button>
              ) : undefined
            }
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
                scrollToCatalogue();
              }}
            />
          </>
        )}
      </section>

      {/* --------------------------------------------------------- CTA */}
      {(!user || isOwner) && (
        <section className="container-page pt-10" aria-labelledby="titre-cta">
          <div className="relative overflow-hidden rounded-3xl bg-hero px-6 py-10 sm:px-12 sm:py-14">
            <div
              aria-hidden
              className="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-highlight/25 blur-3xl"
            />
            <div className="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
              <div className="max-w-xl">
                <h2
                  id="titre-cta"
                  className="text-2xl font-extrabold text-primary-foreground sm:text-3xl"
                >
                  Du matériel qui dort dans un placard ?
                </h2>
                <p className="mt-2 text-primary-foreground/80">
                  Publiez-le gratuitement, fixez votre prix par jour et acceptez uniquement les
                  demandes qui vous conviennent.
                </p>
              </div>
              {isOwner ? (
                <Button asChild variant="highlight" size="lg" className="self-start md:self-auto">
                  <Link to="/proprietaire/materiels/nouveau">
                    Ajouter un matériel <ArrowRight aria-hidden />
                  </Link>
                </Button>
              ) : (
                <Button asChild variant="highlight" size="lg" className="self-start md:self-auto">
                  <Link to="/inscription" search={{ role: "proprietaire" }}>
                    Proposer mon matériel <ArrowRight aria-hidden />
                  </Link>
                </Button>
              )}
            </div>
          </div>
        </section>
      )}
    </>
  );
}

function Grid({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        "grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4",
        className,
      )}
    >
      {children}
    </div>
  );
}

function CategoryChip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "shrink-0 rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        active
          ? "border-primary bg-primary text-primary-foreground"
          : "bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground",
      )}
    >
      {children}
    </button>
  );
}
