import { useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { CalendarDays, CheckCircle2, MessageCircle, Phone, Receipt } from "lucide-react";
import { toast } from "sonner";
import { useMesLocations, useAnnulerLocation, useRequestProlongation } from "@/hooks/use-locations";
import { errorMessage } from "@/lib/api";
import { STATUTS, STATUT_PLURAL } from "@/lib/constants";
import {
  beninToday,
  formatDate,
  formatDateTime,
  formatFCFA,
  formatRange,
  toApiDate,
  toLocalDate,
  whatsappLink,
} from "@/lib/format";
import { seo } from "@/lib/seo";
import type { Location, Statut } from "@/types/api";
import { RequireRole } from "@/components/RequireRole";
import { StatusTabs } from "@/components/StatusTabs";
import { PaginationBar } from "@/components/PaginationBar";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { StatusBadge, RetardBadge } from "@/components/StatusBadge";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { ProfileAvatar } from "@/components/ProfileAvatar";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Info, Quote } from "@/components/DetailBits";
import { LocalisationCard } from "@/components/Localisation";
import { HistoriqueLocation } from "@/components/HistoriqueLocation";
import { SignalerProprietaire } from "@/components/SignalerProprietaire";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export const Route = createFileRoute("/mes-locations")({
  head: () =>
    seo("Mes locations", "Suivez vos demandes de location : en attente, confirmées, terminées."),
  component: () => (
    <RequireRole roles={["locataire", "proprietaire"]}>
      <MesLocations />
    </RequireRole>
  ),
});

type Filter = Statut | "toutes";

const PAGE_SIZE = 10;

function MesLocations() {
  const { data, isPending, isError, error, refetch } = useMesLocations();
  const [filter, setFilter] = useState<Filter>("toutes");
  const [page, setPage] = useState(1);
  const [detail, setDetail] = useState<Location | null>(null);
  const [cancelId, setCancelId] = useState<number | null>(null);
  const annuler = useAnnulerLocation();

  const counts = useMemo(() => {
    const c: Record<string, number> = { toutes: data?.length ?? 0 };
    for (const s of STATUTS) c[s] = data?.filter((l) => l.statut === s).length ?? 0;
    return c;
  }, [data]);

  const list = filter === "toutes" ? data : data?.filter((l) => l.statut === filter);
  const total = list?.length ?? 0;
  const lastPage = Math.max(1, Math.ceil(total / PAGE_SIZE));
  const currentPage = Math.min(page, lastPage);
  const pageItems = list ? list.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE) : [];

  const confirmCancel = () => {
    if (cancelId == null) return;
    annuler.mutate(cancelId, {
      onSuccess: () => {
        toast.success("Demande annulée.");
        setCancelId(null);
        setDetail(null);
      },
      onError: (e) => toast.error(errorMessage(e)),
    });
  };

  return (
    <div className="container-page py-8">
      <PageHeader
        title="Mes locations"
        description="Toutes vos demandes et leur avancement."
        actions={
          <Button asChild variant="outline">
            <Link to="/">Parcourir le catalogue</Link>
          </Button>
        }
      />
      <StatusTabs
        label="Filtrer par statut"
        value={filter}
        onChange={(v) => {
          setFilter(v);
          setPage(1);
        }}
        items={[
          { value: "toutes" as Filter, label: "Toutes", count: counts.toutes },
          ...STATUTS.map((s) => ({
            value: s as Filter,
            label: STATUT_PLURAL[s],
            count: counts[s],
          })),
        ]}
      />
      {isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }, (_, i) => (
            <Skeleton key={i} className="h-32 w-full rounded-xl" />
          ))}
        </div>
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : !list || list.length === 0 ? (
        <EmptyState
          icon={CalendarDays}
          title={
            filter === "toutes"
              ? "Aucune location pour l'instant"
              : "Aucune demande dans cette catégorie"
          }
          description="Trouvez le matériel qu'il vous faut et envoyez votre première demande."
          action={
            <Button asChild>
              <Link to="/">Voir le catalogue</Link>
            </Button>
          }
        />
      ) : (
        <>
          <div className="hidden overflow-hidden rounded-xl border bg-card shadow-card md:block">
            <Table>
              <TableHeader>
                <TableRow className="bg-muted/40 hover:bg-muted/40">
                  <TableHead className="px-4">Matériel</TableHead>
                  <TableHead>Qté</TableHead>
                  <TableHead>Période</TableHead>
                  <TableHead>Prix</TableHead>
                  <TableHead>Statut</TableHead>
                  <TableHead className="px-4 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {pageItems.map((l) => (
                  <TableRow key={l.id}>
                    <TableCell className="px-4 py-3">
                      <div className="flex items-center gap-3">
                        <MaterielPhoto
                          categorie={l.materiel?.categorie}
                          photo={l.materiel?.photo}
                          alt={l.materiel?.nom ?? "Matériel"}
                          className="size-12 shrink-0 rounded-lg"
                          iconClassName="size-5"
                        />
                        <div className="min-w-0">
                          <p className="truncate font-semibold">
                            {l.materiel?.nom ?? `Matériel #${l.materiel_id}`}
                          </p>
                          {l.materiel?.proprietaire?.name && (
                            <p className="truncate text-xs text-muted-foreground">
                              {l.materiel.proprietaire.name}
                            </p>
                          )}
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="font-semibold">{l.quantite}</TableCell>
                    <TableCell className="whitespace-nowrap">
                      {formatRange(l.date_debut, l.date_fin)}
                      <span className="block text-xs text-muted-foreground">{l.nb_jours} j</span>
                      <span className="block text-xs text-muted-foreground">
                        Demandée le {formatDateTime(l.created_at)}
                      </span>
                    </TableCell>
                    <TableCell className="whitespace-nowrap font-semibold">
                      {formatFCFA(l.prix_total)}
                      {l.penalite_jours > 0 && (
                        <span className="block text-xs font-semibold text-destructive">
                          + pénalité {formatFCFA(l.penalite_montant)}
                        </span>
                      )}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col items-start gap-1">
                        <StatusBadge statut={l.statut} />
                        {l.en_retard && <RetardBadge />}
                      </div>
                    </TableCell>
                    <TableCell className="px-4">
                      <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" onClick={() => setDetail(l)}>
                          Détails
                        </Button>
                        {l.statut === "en_attente" && (
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive"
                            onClick={() => setCancelId(l.id)}
                          >
                            Annuler
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <ul className="space-y-3 md:hidden">
            {pageItems.map((l) => (
              <li key={l.id}>
                <LocationCard
                  l={l}
                  onOpen={() => setDetail(l)}
                  onCancel={() => setCancelId(l.id)}
                />
              </li>
            ))}
          </ul>
          <PaginationBar
            meta={{
              current_page: currentPage,
              last_page: lastPage,
              total,
              per_page: PAGE_SIZE,
              from: total ? (currentPage - 1) * PAGE_SIZE + 1 : null,
              to: total ? Math.min(total, currentPage * PAGE_SIZE) : null,
            }}
            onPage={setPage}
          />
        </>
      )}

      <Dialog open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
        <DialogContent className="max-h-[90vh] overflow-y-auto rounded-2xl sm:max-w-lg">
          {detail && (
            <>
              <DialogHeader>
                <DialogTitle>{detail.materiel?.nom ?? "Location"}</DialogTitle>
                <DialogDescription>
                  Demande envoyée le {formatDateTime(detail.created_at)}
                </DialogDescription>
              </DialogHeader>
              <MaterielPhoto
                categorie={detail.materiel?.categorie}
                photo={detail.materiel?.photo}
                alt={detail.materiel?.nom ?? "Matériel"}
                className="aspect-video rounded-xl"
              />
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <Info label="Statut">
                  <span className="flex flex-wrap items-center gap-1.5">
                    <StatusBadge statut={detail.statut} />
                    {detail.en_retard && <RetardBadge />}
                  </span>
                </Info>
                <Info label="Période">{formatRange(detail.date_debut, detail.date_fin)}</Info>
                {(detail.date_fin_initiale ?? detail.date_fin).slice(0, 10) !==
                  detail.date_fin.slice(0, 10) && (
                  <Info label="Retour initialement prévu">
                    {formatDate(detail.date_fin_initiale ?? detail.date_fin)}
                  </Info>
                )}
                <Info label="Durée">
                  {detail.nb_jours} jour{detail.nb_jours > 1 ? "s" : ""}
                </Info>
                <Info label="Quantité">
                  {detail.quantite} exemplaire{detail.quantite > 1 ? "s" : ""}
                </Info>
                <Info label="Prix total">
                  <span className="font-bold text-primary">{formatFCFA(detail.prix_total)}</span>
                </Info>
                <Info label="Montant total à payer">
                  <span className="font-bold text-primary">
                    {formatFCFA(detail.montant_total_a_payer)}
                  </span>
                </Info>
              </dl>
              {detail.penalite_jours > 0 && (
                <Quote label="Pénalité de retard" tone="destructive">
                  {detail.penalite_jours} jour{detail.penalite_jours > 1 ? "s" : ""} de retard :{" "}
                  {formatFCFA(detail.penalite_montant)} (prix journalier majoré de 10 % par jour).
                </Quote>
              )}
              {detail.message && <Quote label="Votre message">{detail.message}</Quote>}
              {detail.statut === "terminee" && (
                <Quote label="Réception confirmée par le propriétaire">
                  Le propriétaire a vérifié et confirmé la réception du matériel
                  {detail.terminee_at ? ` le ${formatDate(detail.terminee_at)}` : ""}. La location
                  est terminée.
                </Quote>
              )}
              {detail.statut === "refusee" && (
                <Quote label="Motif du refus" tone="destructive">
                  {detail.motif_refus || "Aucun motif précisé."}
                </Quote>
              )}
              <OwnerContact l={detail} />
              <SignalerProprietaire key={detail.id} location={detail} />
              <ExtensionRequest key={detail.id} location={detail} onUpdated={setDetail} />
              <HistoriqueLocation historiques={detail.historiques} />
              {detail.statut === "en_attente" && (
                <Button
                  variant="outline"
                  className="text-destructive"
                  onClick={() => setCancelId(detail.id)}
                >
                  Annuler la demande
                </Button>
              )}
            </>
          )}
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={cancelId != null}
        onOpenChange={(o) => !o && setCancelId(null)}
        title="Annuler cette demande ?"
        description="Le propriétaire ne pourra plus la confirmer. Cette action est définitive."
        confirmLabel="Annuler la demande"
        cancelLabel="Garder"
        destructive
        loading={annuler.isPending}
        onConfirm={confirmCancel}
      />
    </div>
  );
}

function ExtensionRequest({
  location,
  onUpdated,
}: {
  location: Location;
  onUpdated: (location: Location) => void;
}) {
  const [dateFin, setDateFin] = useState("");
  const mutation = useRequestProlongation();
  const isPending = location.prolongation_statut === "en_attente";
  const dateDemandee = location.prolongation_date_fin?.slice(0, 10);
  const dateMinimale = toLocalDate(location.date_fin);
  dateMinimale.setDate(dateMinimale.getDate() + 1);
  const dateMinimaleApresEcheance = toApiDate(dateMinimale);
  const aujourdHui = beninToday();
  const dateLimiteAtteinte = aujourdHui > location.date_fin.slice(0, 10);
  const minDateFin =
    dateMinimaleApresEcheance > aujourdHui ? dateMinimaleApresEcheance : aujourdHui;

  if (location.statut !== "confirmee") return null;

  if (isPending && dateDemandee) {
    return (
      <div className="rounded-xl border border-primary/30 bg-primary-soft p-4 text-sm">
        <p className="mb-2">
          Date de dépôt actuelle : <strong>{formatDate(location.date_fin)}</strong> → demandée :{" "}
          <strong>{formatDate(dateDemandee)}</strong>
        </p>
        Demande de prolongation en attente jusqu’au {formatDate(dateDemandee)}. Le tarif inclut une
        majoration de 10 % par jour ajouté. Supplément demandé :{" "}
        <strong>{formatFCFA(location.prolongation_montant ?? "0")}</strong>.
      </div>
    );
  }

  if (dateLimiteAtteinte) {
    return (
      <div className="rounded-xl border bg-muted/40 p-4 text-sm text-muted-foreground">
        <p className="mb-2 text-foreground">
          Date de dépôt prévue : <strong>{formatDate(location.date_fin)}</strong>
        </p>
        La date limite est atteinte. Vous ne pouvez plus demander de prolongation ; le propriétaire
        vérifiera le retour et la pénalité de retard sera calculée à ce moment.
        {location.prolongation_statut === "refusee" && (
          <p className="mt-2 text-destructive">Votre demande de prolongation a été refusée.</p>
        )}
      </div>
    );
  }

  const demander = () => {
    if (!dateFin || dateFin <= location.date_fin.slice(0, 10)) {
      toast.error("Choisissez une date postérieure à la date de retour actuelle.");
      return;
    }
    mutation.mutate(
      { id: location.id, date_fin: dateFin },
      {
        onSuccess: (updated) => {
          onUpdated(updated);
          setDateFin("");
          toast.success("Demande de prolongation envoyée au propriétaire.");
        },
        onError: (error) => toast.error(errorMessage(error)),
      },
    );
  };

  return (
    <div className="space-y-3 rounded-xl border p-4">
      <div>
        <h3 className="font-semibold">Prolonger cette location</h3>
        <p className="mt-1 text-sm">
          Date de dépôt prévue : <strong>{formatDate(location.date_fin)}</strong>
        </p>
        <p className="mt-1 text-sm text-muted-foreground">
          Chaque jour ajouté est facturé au prix journalier du matériel, majoré de 10 %.
        </p>
        {location.prolongation_statut === "refusee" && (
          <p className="mt-1 text-sm text-destructive">
            Votre demande précédente a été refusée. Vous pouvez en soumettre une nouvelle.
          </p>
        )}
        {location.prolongation_statut === "confirmee" && dateDemandee && (
          <p className="mt-1 text-sm text-muted-foreground">
            Prolongation acceptée jusqu’au {formatDate(dateDemandee)}.
          </p>
        )}
      </div>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <label className="flex-1 space-y-1.5 text-sm font-medium" htmlFor="extension-date">
          Nouvelle date de retour
          <Input
            id="extension-date"
            type="date"
            min={minDateFin}
            value={dateFin}
            onChange={(event) => setDateFin(event.target.value)}
          />
        </label>
        <Button onClick={demander} disabled={mutation.isPending || !dateFin}>
          Demander la prolongation
        </Button>
      </div>
    </div>
  );
}

function LocationCard({
  l,
  onOpen,
  onCancel,
}: {
  l: Location;
  onOpen: () => void;
  onCancel: () => void;
}) {
  return (
    <article className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-card transition-shadow hover:shadow-elevated sm:flex-row">
      <button
        type="button"
        onClick={onOpen}
        className="flex flex-1 gap-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-lg"
        aria-label={`Voir le détail de ${l.materiel?.nom ?? "la location"}`}
      >
        <MaterielPhoto
          categorie={l.materiel?.categorie}
          photo={l.materiel?.photo}
          alt={l.materiel?.nom ?? "Matériel"}
          className="size-20 shrink-0 rounded-lg sm:size-24"
          iconClassName="size-6"
        />
        <div className="min-w-0 flex-1 space-y-1.5">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="truncate font-bold">
              {l.materiel?.nom ?? `Matériel #${l.materiel_id}`}
            </h3>
            <StatusBadge statut={l.statut} />
            {l.en_retard && <RetardBadge />}
          </div>
          {l.materiel?.proprietaire?.name && (
            <p className="truncate text-sm font-medium">
              Propriétaire : {l.materiel.proprietaire.name}
            </p>
          )}
          <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
            <CalendarDays className="size-4" aria-hidden /> {formatRange(l.date_debut, l.date_fin)}{" "}
            · {l.nb_jours} j
          </p>
          <p className="text-xs text-muted-foreground">
            Demandée le {formatDateTime(l.created_at)}
          </p>
          {l.statut === "terminee" && (
            <p className="flex items-center gap-1 text-xs font-medium text-primary">
              <CheckCircle2 className="size-4" aria-hidden />
              Réception confirmée par le propriétaire
              {l.terminee_at ? ` le ${formatDate(l.terminee_at)}` : ""}
            </p>
          )}
          <p className="flex items-center gap-1.5 text-sm">
            <Receipt className="size-4 text-muted-foreground" aria-hidden />{" "}
            <span className="font-bold">{formatFCFA(l.prix_total)}</span>
          </p>
          {l.penalite_jours > 0 && (
            <p className="text-xs font-semibold text-destructive">
              Retard : {l.penalite_jours} jour{l.penalite_jours > 1 ? "s" : ""} · pénalité{" "}
              {formatFCFA(l.penalite_montant)}
            </p>
          )}
          {l.statut === "refusee" && l.motif_refus && (
            <p className="text-sm text-destructive">Motif : {l.motif_refus}</p>
          )}
          {l.message && (
            <p className="line-clamp-1 text-xs italic text-muted-foreground">« {l.message} »</p>
          )}
        </div>
      </button>
      <div className="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end sm:justify-center">
        {l.statut === "en_attente" && (
          <Button variant="outline" size="sm" className="text-destructive" onClick={onCancel}>
            Annuler la demande
          </Button>
        )}
        {(l.statut === "confirmee" || l.statut === "terminee") && <ContactButtons l={l} compact />}
        {l.statut === "confirmee" && (
          <p className="max-w-48 text-xs text-muted-foreground">
            Le propriétaire vérifiera et confirmera la réception du matériel.
          </p>
        )}
      </div>
    </article>
  );
}

function ContactButtons({ l, compact = false }: { l: Location; compact?: boolean }) {
  const tel = l.materiel?.proprietaire?.telephone;
  if (!tel)
    return compact ? null : (
      <p className="text-sm text-muted-foreground">
        Le propriétaire n'a pas renseigné de téléphone.
      </p>
    );
  const wa = whatsappLink(
    tel,
    `Bonjour, je vous contacte au sujet de ma location « ${l.materiel?.nom ?? ""} » ${formatRange(l.date_debut, l.date_fin)} sur LocaMat.`,
  );
  return (
    <div className="flex flex-wrap gap-2">
      <Button variant="outline" size="sm" asChild>
        <a href={`tel:${tel}`}>
          <Phone aria-hidden /> {compact ? "Appeler" : tel}
        </a>
      </Button>
      {wa && (
        <Button variant="whatsapp" size="sm" asChild>
          <a href={wa} target="_blank" rel="noreferrer">
            <MessageCircle aria-hidden /> WhatsApp
          </a>
        </Button>
      )}
    </div>
  );
}

function OwnerContact({ l }: { l: Location }) {
  const visible = l.statut === "confirmee" || l.statut === "terminee";
  return (
    <div className="rounded-xl border bg-muted/40 p-4">
      <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
        Propriétaire
      </p>
      <div className="mt-2 flex items-center gap-3">
        <ProfileAvatar
          name={l.materiel?.proprietaire?.name ?? "Propriétaire"}
          photo={l.materiel?.proprietaire?.photo ?? null}
          className="size-10 text-xs"
        />
        <p className="font-semibold">{l.materiel?.proprietaire?.name ?? "—"}</p>
      </div>
      <LocalisationCard
        proprietaire={l.materiel?.proprietaire}
        adresse={l.materiel?.proprietaire?.adresse}
        confirmed={visible}
        className="mt-3"
      />
      {visible ? (
        <div className="mt-3">
          <ContactButtons l={l} />
        </div>
      ) : (
        <p className="mt-2 text-sm text-muted-foreground">
          Les coordonnées du propriétaire seront visibles une fois la demande confirmée.
        </p>
      )}
    </div>
  );
}
