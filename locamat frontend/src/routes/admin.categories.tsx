import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Loader2, Pencil, Plus, Tags, Trash2 } from "lucide-react";
import { toast } from "sonner";
import {
  useAdminCategories,
  useDeleteAdminCategory,
  useSaveAdminCategory,
} from "@/hooks/use-admin";
import { errorMessage } from "@/lib/api";
import { seo } from "@/lib/seo";
import type { AdminCategory } from "@/types/api";
import { ConfirmDialog } from "@/components/ConfirmDialog";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";

export const Route = createFileRoute("/admin/categories")({
  head: () => seo("Catégories", "Gérez les catégories de matériels de LocaMat."),
  component: AdminCategoriesPage,
});

function AdminCategoriesPage() {
  const categories = useAdminCategories();
  const saveCategory = useSaveAdminCategory();
  const deleteCategory = useDeleteAdminCategory();
  const [name, setName] = useState("");
  const [editing, setEditing] = useState<AdminCategory | null>(null);
  const [deleting, setDeleting] = useState<AdminCategory | null>(null);

  const save = () => {
    const trimmedName = name.trim();
    if (!trimmedName) {
      toast.error("Le nom de la catégorie est obligatoire.");
      return;
    }
    saveCategory.mutate(
      { id: editing?.id, name: trimmedName },
      {
        onSuccess: () => {
          toast.success(editing ? "Catégorie modifiée." : "Catégorie ajoutée.");
          setName("");
          setEditing(null);
        },
        onError: (error) => toast.error(errorMessage(error)),
      },
    );
  };

  const remove = () => {
    if (!deleting) return;
    deleteCategory.mutate(deleting.id, {
      onSuccess: () => {
        toast.success("Catégorie supprimée.");
        setDeleting(null);
      },
      onError: (error) => toast.error(errorMessage(error)),
    });
  };

  return (
    <>
      <PageHeader
        title="Catégories"
        description="Ajoutez et modifiez les catégories proposées lors de la publication des matériels."
      />

      <form
        className="mb-6 flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-card sm:flex-row"
        onSubmit={(event) => {
          event.preventDefault();
          save();
        }}
      >
        <Input
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="Nom de la catégorie"
          aria-label="Nom de la catégorie"
          maxLength={255}
          disabled={saveCategory.isPending}
        />
        <Button type="submit" disabled={saveCategory.isPending} className="shrink-0">
          {saveCategory.isPending ? (
            <Loader2 className="animate-spin" aria-hidden />
          ) : editing ? (
            <Pencil aria-hidden />
          ) : (
            <Plus aria-hidden />
          )}
          {editing ? "Enregistrer" : "Ajouter"}
        </Button>
        {editing && (
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setEditing(null);
              setName("");
            }}
          >
            Annuler
          </Button>
        )}
      </form>

      {categories.isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }, (_, index) => (
            <Skeleton key={index} className="h-16 rounded-xl" />
          ))}
        </div>
      ) : categories.isError ? (
        <ErrorState error={categories.error} onRetry={() => void categories.refetch()} />
      ) : categories.data.length === 0 ? (
        <EmptyState
          icon={Tags}
          title="Aucune catégorie"
          description="Ajoutez une catégorie pour faciliter la publication et la recherche des matériels."
        />
      ) : (
        <ul className="divide-y rounded-2xl border bg-card shadow-card">
          {categories.data.map((category) => (
            <li
              key={category.id}
              className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p className="font-semibold">{category.name}</p>
                <p className="text-sm text-muted-foreground">
                  {category.materiels_count} matériel{category.materiels_count === 1 ? "" : "s"}
                </p>
              </div>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setEditing(category);
                    setName(category.name);
                  }}
                  aria-label={`Modifier ${category.name}`}
                >
                  <Pencil aria-hidden />
                  Modifier
                </Button>
                <Button
                  type="button"
                  variant="destructive"
                  size="sm"
                  onClick={() => setDeleting(category)}
                  aria-label={`Supprimer ${category.name}`}
                >
                  <Trash2 aria-hidden />
                  Supprimer
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <ConfirmDialog
        open={deleting != null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title="Supprimer cette catégorie ?"
        description={
          deleting
            ? `La catégorie « ${deleting.name} » ne pourra être supprimée que si aucun matériel ne l’utilise.`
            : undefined
        }
        confirmLabel="Supprimer"
        destructive
        loading={deleteCategory.isPending}
        onConfirm={remove}
      />
    </>
  );
}
