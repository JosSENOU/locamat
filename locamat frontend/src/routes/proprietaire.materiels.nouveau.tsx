import { createFileRoute } from "@tanstack/react-router";
import { seo } from "@/lib/seo";
import { PageHeader } from "@/components/States";
import { MaterielForm } from "@/components/MaterielForm";

export const Route = createFileRoute("/proprietaire/materiels/nouveau")({
  head: () => seo("Ajouter un matériel", "Publiez un nouvel équipement à louer sur LocaMat."),
  component: () => (
    <>
      <PageHeader title="Ajouter un matériel" description="Une bonne photo et une description claire attirent plus de demandes." />
      <MaterielForm />
    </>
  ),
});
