import { createFileRoute, Link } from "@tanstack/react-router";
import { PackageX } from "lucide-react";
import { useOwnMateriels } from "@/hooks/use-materiels";
import { seo } from "@/lib/seo";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { MaterielForm } from "@/components/MaterielForm";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";

export const Route = createFileRoute("/proprietaire/materiels/$id/modifier")({
  head: () => seo("Modifier un matériel", "Mettez à jour le prix, l'état, la photo ou la description."),
  component: Modifier,
});

function Modifier() {
  const { id } = Route.useParams();
  const { data, isPending, isError, error, refetch } = useOwnMateriels();
  const materiel = data?.find((m) => m.id === Number(id));

  return (
    <>
      <PageHeader title="Modifier le matériel" description={materiel?.nom} />
      {isPending ? (
        <Skeleton className="h-[520px] rounded-2xl" />
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : !materiel ? (
        <EmptyState icon={PackageX} title="Matériel introuvable" description="Ce matériel n'existe pas ou ne vous appartient pas." action={<Button asChild><Link to="/proprietaire/materiels">Mes matériels</Link></Button>} />
      ) : (
        <MaterielForm key={materiel.id} materiel={materiel} />
      )}
    </>
  );
}
