import { useState } from "react";
import type { FormEvent } from "react";
import { Flag } from "lucide-react";
import { toast } from "sonner";
import { useCreateReclamation, useMesReclamations } from "@/hooks/use-reclamations";
import { errorMessage } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import type { Location, ReclamationMotif, ReclamationStatut } from "@/types/api";
import { Field } from "@/components/FormField";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";

const MOTIFS: { value: ReclamationMotif; label: string }[] = [
  { value: "comportement_inapproprie", label: "Comportement inapproprié" },
  { value: "materiel_non_conforme", label: "Matériel non conforme à l’annonce" },
  { value: "paiement", label: "Problème de paiement" },
  { value: "securite", label: "Problème de sécurité" },
  { value: "autre", label: "Autre problème" },
];

const STATUTS: Record<ReclamationStatut, string> = {
  nouvelle: "Reçue par l’administration",
  en_cours: "En cours d’examen",
  traitee: "Traitée",
  rejetee: "Clôturée",
};

export function SignalerProprietaire({ location }: { location: Location }) {
  const { data: reclamations } = useMesReclamations();
  const createReclamation = useCreateReclamation();
  const [formOpen, setFormOpen] = useState(false);
  const [motif, setMotif] = useState<ReclamationMotif>("autre");
  const [description, setDescription] = useState("");

  const rapports =
    reclamations?.filter((reclamation) => reclamation.location_id === location.id) ?? [];

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    try {
      await createReclamation.mutateAsync({
        location_id: location.id,
        motif,
        description,
      });
      toast.success("Votre signalement a été transmis à l’administration.");
      setDescription("");
      setMotif("autre");
      setFormOpen(false);
    } catch (error) {
      toast.error(errorMessage(error));
    }
  };

  return (
    <section className="space-y-3 rounded-xl border p-4">
      <div>
        <h3 className="text-sm font-bold">Un problème avec le propriétaire ?</h3>
        <p className="mt-1 text-xs text-muted-foreground">
          Votre signalement sera transmis uniquement à l’administration.
        </p>
      </div>
      {rapports.length > 0 && (
        <ul className="space-y-2" aria-label="Suivi de vos signalements">
          {rapports.map((rapport) => (
            <li key={rapport.id} className="rounded-lg bg-muted/50 p-3 text-sm">
              <p className="font-semibold">{STATUTS[rapport.statut]}</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Envoyé le {formatDateTime(rapport.created_at)}
              </p>
              {rapport.note_admin && <p className="mt-2 text-sm">{rapport.note_admin}</p>}
            </li>
          ))}
        </ul>
      )}
      {formOpen ? (
        <form onSubmit={submit} className="space-y-4">
          <Field id={`reclamation-motif-${location.id}`} label="Motif">
            <select
              id={`reclamation-motif-${location.id}`}
              required
              value={motif}
              onChange={(event) => {
                const nextMotif = MOTIFS.find((option) => option.value === event.target.value);
                if (nextMotif) setMotif(nextMotif.value);
              }}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              {MOTIFS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </Field>
          <Field
            id={`reclamation-description-${location.id}`}
            label="Décrivez le problème"
            hint="10 caractères minimum. N’indiquez pas de données bancaires ou de mot de passe."
          >
            <Textarea
              id={`reclamation-description-${location.id}`}
              required
              minLength={10}
              maxLength={3000}
              rows={4}
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </Field>
          <div className="flex flex-wrap gap-2">
            <Button type="submit" disabled={createReclamation.isPending}>
              {createReclamation.isPending ? "Envoi…" : "Envoyer le signalement"}
            </Button>
            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
              Annuler
            </Button>
          </div>
        </form>
      ) : (
        <Button type="button" variant="outline" size="sm" onClick={() => setFormOpen(true)}>
          <Flag aria-hidden />
          Signaler le propriétaire
        </Button>
      )}
    </section>
  );
}
