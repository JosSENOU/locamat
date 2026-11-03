import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Info } from "lucide-react";
import { toast } from "sonner";
import { useSaveMateriel } from "@/hooks/use-materiels";
import { ApiError } from "@/lib/api";
import { applyServerErrors } from "@/lib/form-errors";
import { CATEGORIES, ETATS } from "@/lib/constants";
import { materielSchema, type MaterielFormInput, type MaterielFormValues } from "@/lib/schemas";
import { cn } from "@/lib/utils";
import type { Materiel } from "@/types/api";
import { Field, fieldAria } from "./FormField";
import { PhotoUpload } from "./PhotoUpload";
import { LoadingButton } from "./SubmitButton";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Textarea } from "./ui/textarea";
import { Switch } from "./ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "./ui/select";

const FIELDS = ["nom", "categorie", "description", "prix_journalier", "etat", "disponible"] as const;

export function MaterielForm({ materiel }: { materiel?: Materiel }) {
  const navigate = useNavigate();
  const save = useSaveMateriel(materiel?.id);
  const [photo, setPhoto] = useState<File | null>(null);
  const [photoError, setPhotoError] = useState<string | null>(null);

  const form = useForm<MaterielFormInput, unknown, MaterielFormValues>({
    resolver: zodResolver(materielSchema),
    defaultValues: {
      nom: materiel?.nom ?? "",
      categorie: (CATEGORIES as readonly string[]).includes(materiel?.categorie ?? "")
        ? (materiel?.categorie as MaterielFormInput["categorie"])
        : undefined,
      description: materiel?.description ?? "",
      prix_journalier: materiel ? String(Number.parseFloat(materiel.prix_journalier)) : "",
      etat: materiel?.etat ?? "bon",
      disponible: materiel?.disponible ?? true,
    },
  });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit((v) => {
    if (photoError) return;
    const fd = new FormData();
    fd.set("nom", v.nom);
    fd.set("categorie", v.categorie);
    fd.set("description", v.description);
    fd.set("prix_journalier", v.prix_journalier);
    fd.set("etat", v.etat);
    fd.set("disponible", v.disponible ? "1" : "0");
    if (photo) fd.set("photo", photo);
    save.mutate(fd, {
      onSuccess: () => {
        toast.success(materiel ? "Matériel mis à jour." : "Matériel publié !");
        void navigate({ to: "/proprietaire/materiels" });
      },
      onError: (e) => {
        if (e instanceof ApiError && e.errors.photo?.[0]) setPhotoError(e.errors.photo[0]);
        applyServerErrors(e, form.setError, [...FIELDS, "photo"]);
      },
    });
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-8 lg:grid-cols-[1fr_340px]">
      <div className="space-y-5 rounded-2xl border bg-card p-6 shadow-card">
        <Field id="nom" label="Nom du matériel" error={errors.nom?.message}>
          <Input placeholder="Ex. : Canon EOS 90D + objectif 18-135" {...fieldAria("nom", errors.nom?.message)} {...form.register("nom")} />
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
                    {CATEGORIES.map((c) => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                  </SelectContent>
                </Select>
              )}
            />
          </Field>
          <Field
            id="prix_journalier"
            label="Prix par jour (FCFA)"
            error={errors.prix_journalier?.message}
            hint={materiel ? "Un nouveau prix ne modifie pas les demandes déjà créées." : undefined}
          >
            <Input inputMode="decimal" placeholder="15000" {...fieldAria("prix_journalier", errors.prix_journalier?.message)} {...form.register("prix_journalier")} />
          </Field>
        </div>
        <Field id="etat" label="État" error={errors.etat?.message}>
          <Controller
            control={form.control}
            name="etat"
            render={({ field }) => (
              <div role="radiogroup" aria-labelledby="etat" className="inline-flex rounded-xl border bg-muted p-1">
                {ETATS.map((e) => (
                  <button
                    key={e.value}
                    type="button"
                    role="radio"
                    aria-checked={field.value === e.value}
                    onClick={() => field.onChange(e.value)}
                    className={cn(
                      "rounded-lg px-4 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                      field.value === e.value ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {e.label}
                  </button>
                ))}
              </div>
            )}
          />
        </Field>
        <Field id="description" label="Description" optional error={errors.description?.message}>
          <Textarea rows={6} placeholder="Ce qui est inclus, conseils d'utilisation, conditions de retrait…" {...fieldAria("description", errors.description?.message)} {...form.register("description")} />
        </Field>
        <Controller
          control={form.control}
          name="disponible"
          render={({ field }) => (
            <label className="flex items-center justify-between gap-4 rounded-xl border p-4">
              <span>
                <span className="block text-sm font-semibold">Visible dans le catalogue</span>
                <span className="text-xs text-muted-foreground">Désactivez pour masquer temporairement ce matériel.</span>
              </span>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </label>
          )}
        />
      </div>

      <div className="space-y-4">
        <div className="rounded-2xl border bg-card p-6 shadow-card">
          <p className="mb-3 text-sm font-semibold">Photo <span className="font-normal text-muted-foreground">(facultatif)</span></p>
          <PhotoUpload file={photo} existing={materiel?.photo} onChange={setPhoto} error={photoError ?? undefined} onError={setPhotoError} />
        </div>
        {materiel && (
          <p className="flex gap-2 rounded-xl bg-highlight/15 p-3 text-xs text-highlight-foreground">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
            Modifier le prix n'affecte pas les demandes déjà envoyées : elles gardent le prix du moment.
          </p>
        )}
        <div className="flex gap-2">
          <Button type="button" variant="outline" className="flex-1" onClick={() => void navigate({ to: "/proprietaire/materiels" })}>
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
