import { useState } from "react";
import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { ArrowLeft, Check, Inbox, X } from "lucide-react";
import { toast } from "sonner";
import {
  useConfirmerProlongation,
  useDemandes,
  useRefuserProlongation,
} from "@/hooks/use-locations";
import { STATUTS, STATUT_PLURAL } from "@/lib/constants";
import { errorMessage } from "@/lib/api";
import { formatFCFA, formatDate, formatDateTime, formatRange } from "@/lib/format";
import { seo } from "@/lib/seo";
import { cn } from "@/lib/utils";
import type { Location, Statut } from "@/types/api";
import { EmptyState, ErrorState, PageHeader } from "@/components/States";
import { StatusTabs } from "@/components/StatusTabs";
import { StatusBadge, RetardBadge } from "@/components/StatusBadge";
import { PaginationBar } from "@/components/PaginationBar";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import { DemandeActionButtons, DemandeDialog, useDemandeActions } from "@/components/DemandeDialog";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";

type Filter = Statut | "toutes";
const ORDER: Statut[] = ["en_attente", "confirmee", "refusee", "terminee", "annulee"];

export const Route = createFileRoute("/proprietaire/demandes")({
  validateSearch: (
    s: Record<string, unknown>,
  ): { statut?: Statut; page?: number; materiel_id?: number } => ({
    statut: STATUTS.includes(s.statut as Statut) ? (s.statut as Statut) : undefined,
    page: Number(s.page) > 1 ? Number(s.page) : undefined,
    materiel_id: Number(s.materiel_id) > 0 ? Number(s.materiel_id) : undefined,
  }),
  head: () =>
    seo(
      "Demandes reçues",
      "Confirmez, refusez ou clôturez les demandes de location de votre matériel.",
    ),
  component: Demandes,
});

function Demandes() {
  const { statut, page = 1, materiel_id: materielId } = Route.useSearch();
  const navigate = useNavigate({ from: "/proprietaire/demandes" });
  const { data, isPending, isError, error, refetch, isPlaceholderData } = useDemandes(
    statut,
    page,
    materielId,
  );
  const actions = useDemandeActions();
  const confirmerProlongation = useConfirmerProlongation();
  const refuserProlongation = useRefuserProlongation();
  const [detail, setDetail] = useState<Location | null>(null);
  const materielFiltre = materielId ? data?.data[0]?.materiel?.nom : undefined;

  return (
    <>
      <PageHeader
        title={materielId ? "Historique du matériel" : "Demandes reçues"}
        description={
          materielId
            ? `Toutes les locations${materielFiltre ? ` de « ${materielFiltre} »` : ""}, avec leur statut.`
            : "Gérez les demandes de location de votre matériel."
        }
        actions={
          materielId ? (
            <Button asChild variant="outline">
              <Link to="/proprietaire/materiels">
                <ArrowLeft aria-hidden /> Mes matériels
              </Link>
            </Button>
          ) : undefined
        }
      />
      <StatusTabs<Filter>
        label="Filtrer par statut"
        value={statut ?? "toutes"}
        onChange={(v) =>
          void navigate({
            search: (prev) => ({
              ...prev,
              statut: v === "toutes" ? undefined : v,
              page: undefined,
            }),
          })
        }
        items={[
          { value: "toutes", label: "Toutes" },
          ...ORDER.map((s) => ({ value: s as Filter, label: STATUT_PLURAL[s] })),
        ]}
      />
      {isPending ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }, (_, i) => (
            <Skeleton key={i} className="h-24 rounded-xl" />
          ))}
        </div>
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : data.data.length === 0 ? (
        <EmptyState
          icon={Inbox}
          title="Aucune demande"
          description={
            statut
              ? "Aucune demande avec ce statut."
              : "Vous n'avez encore reçu aucune demande de location."
          }
        />
      ) : (
        <>
          <ul className={cn("space-y-3", isPlaceholderData && "opacity-60")}>
            {data.data.map((d) => (
              <li
                key={d.id}
                className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-card md:flex-row md:items-center"
              >
                <button
                  type="button"
                  onClick={() => setDetail(d)}
                  className="flex flex-1 items-center gap-3 rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <MaterielPhoto
                    categorie={d.materiel?.categorie}
                    photo={d.materiel?.photo}
                    alt={d.materiel?.nom ?? ""}
                    className="size-16 shrink-0 rounded-lg"
                    iconClassName="size-5"
                  />
                  <div className="min-w-0 space-y-0.5">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="truncate font-bold">
                        {d.materiel?.nom ?? `Matériel #${d.materiel_id}`}
                      </p>
                      <StatusBadge statut={d.statut} />
                      {d.en_retard && <RetardBadge />}
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {d.locataire?.name} · {formatRange(d.date_debut, d.date_fin)} · {d.nb_jours} j
                    </p>
                    <p className="text-xs text-muted-foreground">
                      Demande envoyée le {formatDateTime(d.created_at)}
                    </p>
                    <p className="text-sm font-semibold text-primary">
                      Quantité demandée : {d.quantite} exemplaire{d.quantite > 1 ? "s" : ""}
                    </p>
                    {(d.date_fin_initiale ?? d.date_fin).slice(0, 10) !==
                      d.date_fin.slice(0, 10) && (
                      <p className="text-xs text-muted-foreground">
                        Retour initialement prévu le {formatDate(d.date_fin_initiale ?? d.date_fin)}
                      </p>
                    )}
                    {d.statut === "confirmee" && d.penalite_jours > 0 && (
                      <p className="text-xs font-semibold text-destructive">
                        Retard : {d.penalite_jours} jour{d.penalite_jours > 1 ? "s" : ""} · pénalité{" "}
                        {formatFCFA(d.penalite_montant)}
                      </p>
                    )}
                    {d.statut === "confirmee" && (
                      <p className="text-xs text-muted-foreground">
                        Vérifiez le matériel rendu avant de confirmer sa réception.
                      </p>
                    )}
                    <p className="text-sm font-semibold">{formatFCFA(d.prix_total)}</p>
                  </div>
                </button>
                <div className="flex flex-col gap-2">
                  <DemandeActionButtons d={d} actions={actions} size="sm" />
                  {d.prolongation_statut === "en_attente" && d.prolongation_date_fin && (
                    <div className="flex flex-wrap items-center justify-end gap-2 border-t pt-2">
                      <p className="mr-auto text-xs text-muted-foreground">
                        Prolongation au {formatDate(d.prolongation_date_fin)} ·{" "}
                        {formatFCFA(d.prolongation_montant ?? "0")} (tarif journalier + 10 %)
                      </p>
                      <Button
                        size="sm"
                        variant="success"
                        disabled={confirmerProlongation.isPending || refuserProlongation.isPending}
                        onClick={() =>
                          confirmerProlongation.mutate(d.id, {
                            onSuccess: () => toast.success("Prolongation acceptée."),
                            onError: (error) => toast.error(errorMessage(error)),
                          })
                        }
                      >
                        <Check aria-hidden /> Accepter
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        className="text-destructive"
                        disabled={confirmerProlongation.isPending || refuserProlongation.isPending}
                        onClick={() =>
                          refuserProlongation.mutate(d.id, {
                            onSuccess: () => toast.success("Prolongation refusée."),
                            onError: (error) => toast.error(errorMessage(error)),
                          })
                        }
                      >
                        <X aria-hidden /> Refuser
                      </Button>
                    </div>
                  )}
                </div>
              </li>
            ))}
          </ul>
          <PaginationBar
            meta={data.meta}
            onPage={(p) =>
              void navigate({ search: (prev) => ({ ...prev, page: p > 1 ? p : undefined }) })
            }
          />
        </>
      )}
      <DemandeDialog demande={detail} onClose={() => setDetail(null)} actions={actions} />
      {actions.dialogs}
    </>
  );
}
