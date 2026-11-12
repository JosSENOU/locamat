import { useMemo, useState, type ReactNode } from "react";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import type { DateRange } from "react-day-picker";
import { ArrowLeft, CalendarRange, Info } from "lucide-react";
import { toast } from "sonner";
import { useMateriel, usePeriodes } from "@/hooks/use-materiels";
import { useCreateLocation } from "@/hooks/use-locations";
import { useAuth } from "@/hooks/use-auth";
import { ApiError, errorMessage } from "@/lib/api";
import { periodesToRanges, overlapsBooked } from "@/lib/booking";
import { countDays, formatDateObj, formatFCFA, formatRange, toApiDate } from "@/lib/format";
import { userCanRent } from "@/lib/constants";
import type { ProprietaireLite } from "@/types/api";
import { seo } from "@/lib/seo";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { ProfileAvatar } from "@/components/ProfileAvatar";
import { DispoBadge, EtatBadge } from "@/components/Badges";
import { PriceTag } from "@/components/PriceTag";
import { LocalisationCard, LocalisationInline } from "@/components/Localisation";
import { ErrorState } from "@/components/States";
import { DateRangePicker } from "@/components/DateRangePicker";
import { LoadingButton } from "@/components/SubmitButton";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";

export const Route = createFileRoute("/materiels/$id")({
  head: () =>
    seo(
      "Détail du matériel",
      "Photos, état, tarif et disponibilités de ce matériel à louer sur LocaMat.",
    ),
  component: MaterielDetail,
});

function MaterielDetail() {
  const { id } = Route.useParams();
  const mid = Number(id);
  const { data: m, isPending, isError, error, refetch } = useMateriel(mid);

  if (isPending) return <DetailSkeleton />;
  if (isError)
    return (
      <div className="container-page py-10">
        <ErrorState
          error={error}
          title={
            error instanceof ApiError && error.status === 404
              ? "Ce matériel n'existe pas"
              : undefined
          }
          onRetry={() => void refetch()}
        />
      </div>
    );

  return (
    <div className="container-page py-8">
      <Link
        to="/"
        className="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft className="size-4" aria-hidden /> Retour au catalogue
      </Link>
      <div className="grid gap-8 lg:grid-cols-[1fr_400px]">
        <div className="space-y-6">
          <MaterielPhoto
            categorie={m.categorie}
            photo={m.photo}
            photos={m.photos}
            alt={m.nom}
            className="aspect-[4/3] rounded-2xl border"
            iconClassName="size-14"
          />
          <div>
            <p className="text-sm font-bold uppercase tracking-wider text-primary">{m.categorie}</p>
            <h1 className="mt-1 text-3xl font-extrabold">{m.nom}</h1>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <EtatBadge etat={m.etat} />
              <DispoBadge disponible={m.disponible} />
            </div>
            {m.etats_exemplaires && m.etats_exemplaires.length > 1 && (
              <div className="mt-4 rounded-xl border bg-card p-4">
                <h2 className="text-sm font-bold">État de chaque exemplaire</h2>
                <ul className="mt-2 space-y-2">
                  {m.etats_exemplaires.map((etat, index) => (
                    <li
                      key={`${m.id}-exemplaire-${index}`}
                      className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2"
                    >
                      <span className="text-sm">Exemplaire {index + 1}</span>
                      <EtatBadge etat={etat} />
                    </li>
                  ))}
                </ul>
              </div>
            )}
            <p className="mt-2 text-sm text-muted-foreground">
              {m.quantite_disponible ?? m.quantite} exemplaire
              {(m.quantite_disponible ?? m.quantite) > 1 ? "s" : ""} disponible
              {(m.quantite_disponible ?? m.quantite) > 1 ? "s" : ""} sur {m.quantite}.
            </p>
            {m.locations_count !== undefined && (
              <p className="mt-1 text-sm font-medium text-muted-foreground">
                {m.locations_count} location{m.locations_count > 1 ? "s" : ""} effectuée
                {m.locations_count > 1 ? "s" : ""} pour ce matériel.
              </p>
            )}
          </div>
          <div className="rounded-2xl border bg-card p-5">
            <h2 className="mb-2 font-bold">Description</h2>
            <p className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
              {m.description || "Le propriétaire n'a pas ajouté de description."}
            </p>
          </div>
          {m.proprietaire && (
            <div className="flex items-center gap-3 rounded-2xl border bg-card p-5">
              <ProfileAvatar
                name={m.proprietaire.name}
                photo={m.proprietaire.photo ?? null}
                className="size-10 text-sm"
              />
              <div>
                <p className="text-xs text-muted-foreground">Proposé par</p>
                <p className="font-semibold">{m.proprietaire.name}</p>
                <LocalisationInline
                  proprietaire={m.proprietaire}
                  className="mt-0.5 text-xs text-muted-foreground"
                />
              </div>
            </div>
          )}
        </div>
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <BookingCard
            materielId={m.id}
            proprietaireId={m.proprietaire_id}
            prix={m.prix_journalier}
            quantiteMax={Math.max(1, m.quantite_disponible ?? m.quantite)}
            disponible={m.disponible}
            nom={m.nom}
            proprietaire={m.proprietaire}
          />
        </aside>
      </div>
    </div>
  );
}

function BookingCard({
  materielId,
  proprietaireId,
  prix,
  quantiteMax,
  disponible,
  nom,
  proprietaire,
}: {
  materielId: number;
  proprietaireId: number;
  prix: string;
  quantiteMax: number;
  disponible: boolean;
  nom: string;
  proprietaire?: ProprietaireLite | undefined;
}) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const periodes = usePeriodes(materielId);
  const booked = useMemo(() => periodesToRanges(periodes.data ?? []), [periodes.data]);
  const [range, setRange] = useState<DateRange | undefined>();
  const [message, setMessage] = useState("");
  const [quantiteText, setQuantiteText] = useState("1");
  const [formError, setFormError] = useState<string | null>(null);
  const create = useCreateLocation();

  const quantite = Number.parseInt(quantiteText, 10);
  const quantiteValide = Number.isInteger(quantite) && quantite >= 1 && quantite <= quantiteMax;
  const from = range?.from;
  const to = range?.to ?? range?.from;
  const days = from && to ? countDays(from, to) : 0;
  const total = days * Number.parseFloat(prix) * (quantiteValide ? quantite : 1);
  const overlap = from && to ? overlapsBooked(from, to, booked) : false;

  const isOwner = user?.id === proprietaireId;
  const canBook = disponible && !isOwner && (!user || userCanRent(user));

  const submit = () => {
    if (!quantiteValide) {
      setFormError(
        `Indiquez une quantité entre 1 et ${quantiteMax} exemplaire${quantiteMax > 1 ? "s" : ""}.`,
      );
      return;
    }
    if (!from || !to) {
      setFormError("Choisissez une date de début et une date de fin.");
      return;
    }
    if (overlap) {
      setFormError("Ces dates chevauchent une période déjà réservée.");
      return;
    }
    setFormError(null);
    create.mutate(
      {
        materiel_id: materielId,
        quantite,
        date_debut: toApiDate(from),
        date_fin: toApiDate(to),
        message: message.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Demande envoyée", {
            description: `Votre demande pour « ${nom} » attend la réponse du propriétaire.`,
          });
          void navigate({ to: "/mes-locations" });
        },
        onError: (e) => {
          const msg =
            e instanceof ApiError && e.hasFieldErrors ? Object.values(e.errors)[0]?.[0] : undefined;
          setFormError(msg ?? errorMessage(e));
          toast.error(msg ?? errorMessage(e));
          void periodes.refetch();
        },
      },
    );
  };

  return (
    <div className="rounded-2xl border bg-card p-5 shadow-elevated">
      <PriceTag value={prix} size="lg" />
      <LocalisationCard proprietaire={proprietaire} className="mt-4" />
      <div className="mt-5">
        <h2 className="mb-2 flex items-center gap-2 text-sm font-bold">
          <CalendarRange className="size-4 text-primary" aria-hidden /> Choisissez vos dates
        </h2>
        {periodes.isPending ? (
          <Skeleton className="h-80 w-full rounded-xl" />
        ) : (
          <DateRangePicker value={range} onChange={setRange} booked={booked} disabled={!canBook} />
        )}
        {periodes.isError && (
          <p className="mt-2 text-xs text-destructive">
            Impossible de charger les périodes réservées.
          </p>
        )}
      </div>

      {booked.length > 0 && (
        <details className="mt-3 text-xs text-muted-foreground">
          <summary className="cursor-pointer font-semibold">
            Périodes déjà réservées ({booked.length})
          </summary>
          <ul className="mt-2 space-y-1">
            {(periodes.data ?? []).map((p) => (
              <li key={p.date_debut + p.date_fin}>{formatRange(p.date_debut, p.date_fin)}</li>
            ))}
          </ul>
        </details>
      )}

      {from && to && (
        <div className="mt-4 space-y-2 rounded-xl bg-primary-soft p-4 text-sm">
          <div className="flex justify-between">
            <span className="text-muted-foreground">Période</span>
            <span className="font-semibold">
              {formatDateObj(from)} → {formatDateObj(to)}
            </span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground">
              {days} jour{days > 1 ? "s" : ""}
              {quantiteValide && quantite > 1 ? ` × ${quantite} exemplaires` : ""} ×{" "}
              {formatFCFA(prix)}
            </span>
          </div>
          <div className="flex justify-between border-t border-primary/20 pt-2 text-base">
            <span className="font-bold">Total</span>
            <span className="font-extrabold text-primary">{formatFCFA(total)}</span>
          </div>
        </div>
      )}

      {!disponible ? (
        <Notice>Ce matériel est actuellement indisponible à la location.</Notice>
      ) : isOwner ? (
        <Notice>C'est votre matériel : vous ne pouvez pas le louer.</Notice>
      ) : !user ? (
        <Button asChild size="lg" className="mt-5 w-full">
          <Link to="/connexion" search={{ redirect: `/materiels/${materielId}` }}>
            Se connecter pour réserver
          </Link>
        </Button>
      ) : !userCanRent(user) ? (
        <Notice>
          Seuls les comptes locataires et propriétaires peuvent faire une demande de location.
        </Notice>
      ) : (
        <div className="mt-5 space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="quantite" className="text-sm font-semibold">
              Quantité souhaitée
            </Label>
            <Input
              id="quantite"
              type="number"
              inputMode="numeric"
              min={1}
              max={quantiteMax}
              step={1}
              value={quantiteText}
              onChange={(e) => setQuantiteText(e.target.value)}
              aria-describedby="quantite-aide"
              aria-invalid={!quantiteValide}
            />
            <p id="quantite-aide" className="text-xs text-muted-foreground">
              {quantiteMax} exemplaire{quantiteMax > 1 ? "s" : ""} disponible
              {quantiteMax > 1 ? "s" : ""} au maximum.
            </p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="message" className="text-sm font-semibold">
              Message au propriétaire{" "}
              <span className="font-normal text-muted-foreground">(facultatif)</span>
            </Label>
            <Textarea
              id="message"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              maxLength={1000}
              rows={3}
              placeholder="Ex. : c'est pour un mariage samedi, je peux passer le récupérer vendredi soir."
            />
          </div>
          {formError && (
            <p role="alert" className="text-sm font-medium text-destructive">
              {formError}
            </p>
          )}
          <LoadingButton
            size="lg"
            className="w-full"
            loading={create.isPending}
            onClick={submit}
            disabled={!canBook}
          >
            Envoyer la demande
          </LoadingButton>
          <p className="text-center text-xs text-muted-foreground">
            Vous ne serez débité de rien : le propriétaire doit d'abord confirmer.
          </p>
        </div>
      )}
    </div>
  );
}

function Notice({ children }: { children: ReactNode }) {
  return (
    <p className="mt-5 flex gap-2 rounded-xl bg-muted p-3 text-sm text-muted-foreground">
      <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
      {children}
    </p>
  );
}

function DetailSkeleton() {
  return (
    <div className="container-page grid gap-8 py-8 lg:grid-cols-[1fr_400px]" aria-busy>
      <div className="space-y-4">
        <Skeleton className="aspect-[4/3] w-full rounded-2xl" />
        <Skeleton className="h-8 w-2/3" />
        <Skeleton className="h-24 w-full" />
      </div>
      <Skeleton className="h-[520px] w-full rounded-2xl" />
    </div>
  );
}
