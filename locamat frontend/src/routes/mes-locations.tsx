import { useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { CalendarDays, MessageCircle, Phone, Receipt } from "lucide-react";
import { toast } from "sonner";
import { useMesLocations, useAnnulerLocation } from "@/hooks/use-locations";
import { errorMessage } from "@/lib/api";
import { STATUTS, STATUT_PLURAL } from "@/lib/constants";
import { formatDate, formatFCFA, formatRange, whatsappLink } from "@/lib/format";
import { seo } from "@/lib/seo";
import type { Location, Statut } from "@/types/api";
import { RequireRole } from "@/components/RequireRole";
import { StatusTabs } from "@/components/StatusTabs";
import { StatusBadge } from "@/components/StatusBadge";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { ProfileAvatar } from "@/components/ProfileAvatar";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { Button } from "@/components/ui/button";
import { Info, Quote } from "@/components/DetailBits";
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
    <RequireRole roles={["locataire"]}>
      <MesLocations />
    </RequireRole>
  ),
});

type Filter = Statut | "toutes";

function MesLocations() {
  const { data, isPending, isError, error, refetch } = useMesLocations();
  const [filter, setFilter] = useState<Filter>("toutes");
  const [detail, setDetail] = useState<Location | null>(null);
  const [cancelId, setCancelId] = useState<number | null>(null);
  const annuler = useAnnulerLocation();

  const counts = useMemo(() => {
    const c: Record<string, number> = { toutes: data?.length ?? 0 };
    for (const s of STATUTS) c[s] = data?.filter((l) => l.statut === s).length ?? 0;
    return c;
  }, [data]);

  const list = filter === "toutes" ? data : data?.filter((l) => l.statut === filter);

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
        onChange={setFilter}
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
        <ul className="space-y-3">
          {list.map((l) => (
            <li key={l.id}>
              <LocationCard l={l} onOpen={() => setDetail(l)} onCancel={() => setCancelId(l.id)} />
            </li>
          ))}
        </ul>
      )}

      <Dialog open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
        <DialogContent className="max-h-[90vh] overflow-y-auto rounded-2xl sm:max-w-lg">
          {detail && (
            <>
              <DialogHeader>
                <DialogTitle>{detail.materiel?.nom ?? "Location"}</DialogTitle>
                <DialogDescription>
                  Demande envoyée le {formatDate(detail.created_at)}
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
                  <StatusBadge statut={detail.statut} />
                </Info>
                <Info label="Période">{formatRange(detail.date_debut, detail.date_fin)}</Info>
                <Info label="Durée">
                  {detail.nb_jours} jour{detail.nb_jours > 1 ? "s" : ""}
                </Info>
                <Info label="Prix total">
                  <span className="font-bold text-primary">{formatFCFA(detail.prix_total)}</span>
                </Info>
              </dl>
              {detail.message && <Quote label="Votre message">{detail.message}</Quote>}
              {detail.statut === "refusee" && (
                <Quote label="Motif du refus" tone="destructive">
                  {detail.motif_refus || "Aucun motif précisé."}
                </Quote>
              )}
              <OwnerContact l={detail} />
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
          </div>
          <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
            <CalendarDays className="size-4" aria-hidden /> {formatRange(l.date_debut, l.date_fin)}{" "}
            · {l.nb_jours} j
          </p>
          <p className="flex items-center gap-1.5 text-sm">
            <Receipt className="size-4 text-muted-foreground" aria-hidden />{" "}
            <span className="font-bold">{formatFCFA(l.prix_total)}</span>
          </p>
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
