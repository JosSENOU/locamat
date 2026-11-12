import { useState } from "react";
import { Check, CircleCheckBig, Mail, MessageCircle, Phone, X } from "lucide-react";
import { toast } from "sonner";
import {
  useConfirmerLocation,
  useRefuserLocation,
  useTerminerLocation,
} from "@/hooks/use-locations";
import { errorMessage } from "@/lib/api";
import {
  formatDate,
  formatDateTime,
  formatFCFA,
  formatRange,
  isDatePassee,
  whatsappLink,
} from "@/lib/format";
import type { Location } from "@/types/api";
import { RetardBadge, StatusBadge } from "./StatusBadge";
import { HistoriqueLocation } from "./HistoriqueLocation";
import { MaterielPhoto } from "./MaterielPhoto";
import { ProfileAvatar } from "./ProfileAvatar";
import { ConfirmDialog } from "./ConfirmDialog";
import { Info, Quote } from "./DetailBits";
import { LoadingButton } from "./SubmitButton";
import { Button } from "./ui/button";
import { Textarea } from "./ui/textarea";
import { Label } from "./ui/label";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "./ui/dialog";

/** Owner actions on a request: confirm, refuse (with optional reason), verify returned item. */
export function useDemandeActions() {
  const confirmer = useConfirmerLocation();
  const refuser = useRefuserLocation();
  const terminer = useTerminerLocation();
  const [refuseId, setRefuseId] = useState<number | null>(null);
  const [terminerId, setTerminerId] = useState<number | null>(null);
  const [motif, setMotif] = useState("");

  const confirm = (id: number, done?: () => void) =>
    confirmer.mutate(id, {
      onSuccess: () => {
        toast.success("Location confirmée.");
        done?.();
      },
      onError: (e) => toast.error(errorMessage(e)),
    });

  const dialogs = (
    <>
      <ConfirmDialog
        open={refuseId != null}
        onOpenChange={(o) => {
          if (!o) {
            setRefuseId(null);
            setMotif("");
          }
        }}
        title="Refuser cette demande ?"
        description="Le locataire verra le motif si vous en indiquez un."
        confirmLabel="Refuser la demande"
        destructive
        loading={refuser.isPending}
        onConfirm={() =>
          refuseId != null &&
          refuser.mutate(
            { id: refuseId, motif_refus: motif.trim() || undefined },
            {
              onSuccess: () => {
                toast.success("Demande refusée.");
                setRefuseId(null);
                setMotif("");
              },
              onError: (e) => toast.error(errorMessage(e)),
            },
          )
        }
      >
        <div className="space-y-1.5">
          <Label htmlFor="motif">
            Motif <span className="font-normal text-muted-foreground">(facultatif)</span>
          </Label>
          <Textarea
            id="motif"
            value={motif}
            maxLength={1000}
            onChange={(e) => setMotif(e.target.value)}
            placeholder="Ex. : le matériel est en réparation cette semaine."
          />
        </div>
      </ConfirmDialog>
      <ConfirmDialog
        open={terminerId != null}
        onOpenChange={(o) => !o && setTerminerId(null)}
        title="Confirmer la réception du matériel ?"
        description="Vérifiez que le matériel vous a bien été rendu. Cette confirmation clôture la location et fixe, le cas échéant, la pénalité de retard au tarif journalier majoré de 10 % par jour."
        confirmLabel="Vérifier et clôturer"
        loading={terminer.isPending}
        onConfirm={() =>
          terminerId != null &&
          terminer.mutate(terminerId, {
            onSuccess: () => {
              toast.success("Réception confirmée. Location clôturée.");
              setTerminerId(null);
            },
            onError: (e) => toast.error(errorMessage(e)),
          })
        }
      />
    </>
  );

  return {
    confirm,
    confirmingId: confirmer.isPending ? confirmer.variables : undefined,
    askRefuse: setRefuseId,
    askTerminer: setTerminerId,
    dialogs,
  };
}

export function DemandeDialog({
  demande,
  onClose,
  actions,
}: {
  demande: Location | null;
  onClose: () => void;
  actions: ReturnType<typeof useDemandeActions>;
}) {
  const d = demande;
  const tel = d?.locataire?.telephone;
  const wa = d
    ? whatsappLink(
        tel,
        `Bonjour ${d.locataire?.name ?? ""}, je vous contacte au sujet de votre demande de location « ${d.materiel?.nom ?? ""} » ${formatRange(d.date_debut, d.date_fin)} sur LocaMat.`,
      )
    : null;

  return (
    <Dialog open={!!d} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto rounded-2xl sm:max-w-lg">
        {d && (
          <>
            <DialogHeader>
              <DialogTitle>{d.materiel?.nom ?? `Demande #${d.id}`}</DialogTitle>
              <DialogDescription>Demande reçue le {formatDateTime(d.created_at)}</DialogDescription>
            </DialogHeader>
            <MaterielPhoto
              categorie={d.materiel?.categorie}
              photo={d.materiel?.photo}
              alt={d.materiel?.nom ?? "Matériel"}
              className="aspect-video rounded-xl"
            />
            <dl className="grid grid-cols-2 gap-3 text-sm">
              <Info label="Statut">
                <span className="flex flex-wrap items-center gap-1.5">
                  <StatusBadge statut={d.statut} />
                  {d.en_retard && <RetardBadge />}
                </span>
              </Info>
              <Info label="Période">{formatRange(d.date_debut, d.date_fin)}</Info>
              {(d.date_fin_initiale ?? d.date_fin).slice(0, 10) !== d.date_fin.slice(0, 10) && (
                <Info label="Retour initialement prévu">
                  {formatDate(d.date_fin_initiale ?? d.date_fin)}
                </Info>
              )}
              <Info label="Durée">
                {d.nb_jours} jour{d.nb_jours > 1 ? "s" : ""}
              </Info>
              <Info label="Quantité demandée">
                {d.quantite} exemplaire{d.quantite > 1 ? "s" : ""}
              </Info>
              <Info label="Prix total">
                <span className="font-bold text-primary">{formatFCFA(d.prix_total)}</span>
              </Info>
              <Info label="Montant total à payer">
                <span className="font-bold text-primary">
                  {formatFCFA(d.montant_total_a_payer)}
                </span>
              </Info>
            </dl>
            {d.penalite_jours > 0 && (
              <Quote label="Pénalité de retard" tone="destructive">
                {d.penalite_jours} jour{d.penalite_jours > 1 ? "s" : ""} de retard :{" "}
                {formatFCFA(d.penalite_montant)} (prix journalier majoré de 10 % par jour).
              </Quote>
            )}
            <div className="rounded-xl border bg-muted/40 p-4 text-sm">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Locataire
              </p>
              <div className="mt-2 flex items-center gap-3">
                <ProfileAvatar
                  name={d.locataire?.name ?? "Locataire"}
                  photo={d.locataire?.photo ?? null}
                  className="size-10 text-xs"
                />
                <p className="font-semibold">{d.locataire?.name ?? "—"}</p>
              </div>
              <div className="mt-2 space-y-1 text-muted-foreground">
                {tel && (
                  <p className="flex items-center gap-2">
                    <Phone className="size-4" aria-hidden />
                    <a href={`tel:${tel}`} className="hover:text-foreground">
                      {tel}
                    </a>
                  </p>
                )}
                {d.locataire?.email && (
                  <p className="flex items-center gap-2">
                    <Mail className="size-4" aria-hidden />
                    <a href={`mailto:${d.locataire.email}`} className="hover:text-foreground">
                      {d.locataire.email}
                    </a>
                  </p>
                )}
              </div>
              {wa && (
                <Button variant="whatsapp" size="sm" className="mt-3" asChild>
                  <a href={wa} target="_blank" rel="noreferrer">
                    <MessageCircle aria-hidden /> Contacter sur WhatsApp
                  </a>
                </Button>
              )}
            </div>
            {d.message && <Quote label="Message du locataire">{d.message}</Quote>}
            {d.statut === "refusee" && d.motif_refus && (
              <Quote label="Motif du refus" tone="destructive">
                {d.motif_refus}
              </Quote>
            )}
            <HistoriqueLocation historiques={d.historiques} />
            <DemandeActionButtons d={d} actions={actions} onDone={onClose} />
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

export function DemandeActionButtons({
  d,
  actions,
  onDone,
  size = "default",
}: {
  d: Location;
  actions: ReturnType<typeof useDemandeActions>;
  onDone?: () => void;
  size?: "default" | "sm";
}) {
  if (d.statut === "en_attente") {
    const datePassee = isDatePassee(d.date_debut);
    return (
      <div className="space-y-2">
        <div className="flex flex-wrap gap-2">
          <LoadingButton
            size={size}
            loading={actions.confirmingId === d.id}
            disabled={datePassee}
            title={datePassee ? "La date de début est déjà passée." : undefined}
            onClick={() => actions.confirm(d.id, onDone)}
          >
            <Check aria-hidden /> Confirmer
          </LoadingButton>
          <Button
            size={size}
            variant="outline"
            className="text-destructive"
            onClick={() => {
              actions.askRefuse(d.id);
              onDone?.();
            }}
          >
            <X aria-hidden /> Refuser
          </Button>
        </div>
        {datePassee && (
          <p className="text-xs text-destructive">
            La date de début est passée : cette demande ne peut plus être confirmée, seulement
            refusée.
          </p>
        )}
      </div>
    );
  }
  if (d.statut === "confirmee") {
    const retourPossible = isDatePassee(d.date_fin);
    return (
      <div className="space-y-2">
        <Button
          size={size}
          variant="success"
          disabled={!retourPossible}
          title={
            !retourPossible ? "Disponible à partir du lendemain de la date limite." : undefined
          }
          onClick={() => {
            actions.askTerminer(d.id);
            onDone?.();
          }}
        >
          <CircleCheckBig aria-hidden /> Vérifier et confirmer le retour
        </Button>
        {!retourPossible && (
          <p className="text-xs text-muted-foreground">
            Vous pourrez confirmer le retour après le {formatDate(d.date_fin)}.
          </p>
        )}
      </div>
    );
  }
  return null;
}
