import { useState } from "react";
import { Check, CircleCheckBig, Mail, MessageCircle, Phone, X } from "lucide-react";
import { toast } from "sonner";
import {
  useConfirmerLocation,
  useRefuserLocation,
  useTerminerLocation,
} from "@/hooks/use-locations";
import { errorMessage } from "@/lib/api";
import { formatDate, formatFCFA, formatRange, whatsappLink } from "@/lib/format";
import type { Location } from "@/types/api";
import { StatusBadge } from "./StatusBadge";
import { MaterielPhoto } from "./MaterielPhoto";
import { ProfileAvatar } from "./ProfileAvatar";
import { ConfirmDialog } from "./ConfirmDialog";
import { Info, Quote } from "./DetailBits";
import { LoadingButton } from "./SubmitButton";
import { Button } from "./ui/button";
import { Textarea } from "./ui/textarea";
import { Label } from "./ui/label";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "./ui/dialog";

/** Owner actions on a request: confirm, refuse (with optional reason), mark as finished. */
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
        title="Marquer comme terminée ?"
        description="Le matériel a-t-il bien été restitué ?"
        confirmLabel="Oui, terminer"
        loading={terminer.isPending}
        onConfirm={() =>
          terminerId != null &&
          terminer.mutate(terminerId, {
            onSuccess: () => {
              toast.success("Location terminée.");
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
              <DialogDescription>Reçue le {formatDate(d.created_at)}</DialogDescription>
            </DialogHeader>
            <MaterielPhoto
              categorie={d.materiel?.categorie}
              photo={d.materiel?.photo}
              alt={d.materiel?.nom ?? "Matériel"}
              className="aspect-video rounded-xl"
            />
            <dl className="grid grid-cols-2 gap-3 text-sm">
              <Info label="Statut">
                <StatusBadge statut={d.statut} />
              </Info>
              <Info label="Période">{formatRange(d.date_debut, d.date_fin)}</Info>
              <Info label="Durée">
                {d.nb_jours} jour{d.nb_jours > 1 ? "s" : ""}
              </Info>
              <Info label="Prix total">
                <span className="font-bold text-primary">{formatFCFA(d.prix_total)}</span>
              </Info>
            </dl>
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
  if (d.statut === "en_attente")
    return (
      <div className="flex flex-wrap gap-2">
        <LoadingButton
          size={size}
          loading={actions.confirmingId === d.id}
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
    );
  if (d.statut === "confirmee")
    return (
      <Button
        size={size}
        variant="outline"
        onClick={() => {
          actions.askTerminer(d.id);
          onDone?.();
        }}
      >
        <CircleCheckBig aria-hidden /> Marquer comme terminée
      </Button>
    );
  return null;
}
