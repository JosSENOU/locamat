import { useEffect, useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Info } from "lucide-react";
import { toast } from "sonner";
import { useCategories, useSaveMateriel } from "@/hooks/use-materiels";
import { ApiError } from "@/lib/api";
import { applyServerErrors } from "@/lib/form-errors";
import { ETATS } from "@/lib/constants";
import { materielSchema, type MaterielFormInput, type MaterielFormValues } from "@/lib/schemas";
import type { Materiel } from "@/types/api";
import { Field, fieldAria } from "./FormField";
import { MaterielPhotosUpload } from "./MaterielPhotosUpload";
import { LoadingButton } from "./SubmitButton";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Textarea } from "./ui/textarea";
import { Switch } from "./ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "./ui/select";

const FIELDS = [
  "nom",
  "categorie",
  "description",
  "prix_journalier",
  "quantite",
  "etat",
  "etats_exemplaires",
  "disponible",
] as const;

export function MaterielForm({ materiel }: { materiel?: Materiel }) {
  const navigate = useNavigate();
  const categories = useCategories();
  const save = useSaveMateriel(materiel?.id);
  const [photos, setPhotos] = useState<File[]>([]);
  const [photosError, setPhotosError] = useState<string | null>(null);

  const form = useForm<MaterielFormInput, unknown, MaterielFormValues>({
    resolver: zodResolver(materielSchema),
    defaultValues: {
      nom: materiel?.nom ?? "",
      categorie: materiel?.categorie ?? undefined,
      description: materiel?.description ?? "",
      prix_journalier: materiel ? String(Number.parseFloat(materiel.prix_journalier)) : "",
      quantite: String(materiel?.quantite ?? 1),
      etat: materiel?.etat ?? "bon",
      etats_exemplaires:
        materiel?.etats_exemplaires ??
        Array.from({ length: materiel?.quantite ?? 1 }, () => materiel?.etat ?? "bon"),
      disponible: materiel?.disponible ?? true,
    },
  });
  const quantiteSaisie = useWatch({ control: form.control, name: "quantite" });
  const quantite = Number(quantiteSaisie);
  const { getValues, setValue } = form;

  useEffect(() => {
    if (!Number.isInteger(quantite) || quantite < 1 || quantite > 10000) return;

    const etats = getValues("etats_exemplaires");
    if (etats.length !== quantite) {
      setValue(
        "etats_exemplaires",
        Array.from({ length: quantite }, (_, index) => etats[index] ?? getValues("etat")),
        { shouldValidate: true },
      );
    }
  }, [getValues, quantite, setValue]);

  const { errors } = form.formState;

  const onSubmit = form.handleSubmit((v) => {
    if (photosError) return;
    if (!materiel && photos.length < 1) {
      setPhotosError("Ajoutez au moins une photo pour publier ce matériel.");
      return;
    }

    const fd = new FormData();
    fd.set("nom", v.nom);
    fd.set("categorie", v.categorie);
    fd.set("description", v.description);
    fd.set("prix_journalier", v.prix_journalier);
    fd.set("quantite", String(v.quantite));
    fd.set("etat", v.etats_exemplaires[0] ?? v.etat);
    fd.set("etats_exemplaires", JSON.stringify(v.etats_exemplaires));
    fd.set("disponible", v.disponible ? "1" : "0");
    for (const photo of photos) fd.append("photos[]", photo);
    save.mutate(fd, {
      onSuccess: () => {
        toast.success(materiel ? "Matériel mis à jour." : "Matériel publié !");
        void navigate({ to: "/proprietaire/materiels" });
      },
      onError: (e) => {
        if (e instanceof ApiError && e.errors.photos?.[0]) setPhotosError(e.errors.photos[0]);
        applyServerErrors(e, form.setError, [
          ...FIELDS,
          "photos",
          "photos.0",
          "photos.1",
          "photos.2",
        ]);
      },
    });
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-8 lg:grid-cols-[1fr_340px]">
      <div className="space-y-5 rounded-2xl border bg-card p-6 shadow-card">
        <Field id="nom" label="Nom du matériel" error={errors.nom?.message}>
          <Input
            placeholder="Ex. : Canon EOS 90D + objectif 18-135"
            {...fieldAria("nom", errors.nom?.message)}
            {...form.register("nom")}
          />
        </Field>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field id="categorie" label="Catégorie" error={errors.categorie?.message}>
            <Controller
              control={form.control}
              name="categorie"
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger {...fieldAria("categorie", errors.categorie?.message)}>
                    <SelectValue placeholder="Choisir…" />
                  </SelectTrigger>
                  <SelectContent>
                    {(categories.data ?? []).map((category) => (
                      <SelectItem key={category.id} value={category.name}>
                        {category.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
            {categories.isError && (
              <p role="alert" className="mt-1 text-xs text-destructive">
                Impossible de charger les catégories. Rechargez la page pour réessayer.
              </p>
            )}
            {!categories.isPending && !categories.isError && categories.data?.length === 0 && (
              <p className="mt-1 text-xs text-muted-foreground">
                Aucune catégorie n’est configurée. Contactez un administrateur.
              </p>
            )}
          </Field>
          <Field
            id="prix_journalier"
            label="Prix par jour (FCFA)"
            error={errors.prix_journalier?.message}
            hint={materiel ? "Un nouveau prix ne modifie pas les demandes déjà créées." : undefined}
          >
            <Input
              inputMode="decimal"
              placeholder="15000"
              {...fieldAria("prix_journalier", errors.prix_journalier?.message)}
              {...form.register("prix_journalier")}
            />
          </Field>
        </div>
        <Field
          id="quantite"
          label="Nombre total d'exemplaires"
          error={errors.quantite?.message}
          hint={
            materiel
              ? "Après un achat, augmentez le total (par exemple de 3 à 4). Les locations en cours restent déduites automatiquement."
              : "Indiquez le nombre total d'exemplaires que vous possédez."
          }
        >
          <Input
            type="number"
            min={1}
            max={10000}
            step={1}
            inputMode="numeric"
            {...fieldAria("quantite", errors.quantite?.message)}
            {...form.register("quantite")}
          />
        </Field>
        <Field
          id="etats_exemplaires"
          label="État de chaque exemplaire"
          error={errors.etats_exemplaires?.root?.message ?? errors.etats_exemplaires?.message}
          hint="Renseignez l’état de chacun : il sera visible par les locataires."
        >
          <div className="space-y-2">
            {Number.isInteger(quantite) &&
              quantite > 0 &&
              quantite <= 10000 &&
              Array.from({ length: quantite }, (_, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2"
                >
                  <span className="text-sm font-medium">Exemplaire {index + 1}</span>
                  <Controller
                    control={form.control}
                    name={`etats_exemplaires.${index}`}
                    render={({ field }) => (
                      <Select value={field.value} onValueChange={field.onChange}>
                        <SelectTrigger
                          aria-label={`État de l’exemplaire ${index + 1}`}
                          className="w-40"
                        >
                          <SelectValue placeholder="Choisir…" />
                        </SelectTrigger>
                        <SelectContent>
                          {ETATS.map((etat) => (
                            <SelectItem key={etat.value} value={etat.value}>
                              {etat.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                  />
                </div>
              ))}
          </div>
        </Field>
        <Field id="description" label="Description" optional error={errors.description?.message}>
          <Textarea
            rows={6}
            placeholder="Ce qui est inclus, conseils d'utilisation, conditions de retrait…"
            {...fieldAria("description", errors.description?.message)}
            {...form.register("description")}
          />
        </Field>
        <Controller
          control={form.control}
          name="disponible"
          render={({ field }) => (
            <label className="flex items-center justify-between gap-4 rounded-xl border p-4">
              <span>
                <span className="block text-sm font-semibold">Visible dans le catalogue</span>
                <span className="text-xs text-muted-foreground">
                  Désactivez pour masquer temporairement ce matériel.
                </span>
              </span>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </label>
          )}
        />
      </div>

      <div className="space-y-4">
        <div className="rounded-2xl border bg-card p-6 shadow-card">
          <p className="mb-3 text-sm font-semibold">
            Photos du matériel
            {!materiel && <span className="font-normal text-muted-foreground"> (1 à 3)</span>}
          </p>
          <MaterielPhotosUpload
            files={photos}
            existing={materiel?.photos ?? (materiel?.photo ? [materiel.photo] : [])}
            onChange={setPhotos}
            error={photosError ?? undefined}
            onError={setPhotosError}
          />
        </div>
        {materiel && (
          <p className="flex gap-2 rounded-xl bg-highlight/15 p-3 text-xs text-highlight-foreground">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
            Modifier le prix n'affecte pas les demandes déjà envoyées : elles gardent le prix du
            moment.
          </p>
        )}
        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            className="flex-1"
            onClick={() => void navigate({ to: "/proprietaire/materiels" })}
          >
            Annuler
          </Button>
          <LoadingButton type="submit" className="flex-1" loading={save.isPending}>
            {materiel ? "Enregistrer" : "Publier"}
          </LoadingButton>
        </div>
      </div>
    </form>
  );
}
