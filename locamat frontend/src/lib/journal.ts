import { formatDate } from "@/lib/format";
import type { AdminAction, AdminActionType } from "@/types/api";

export const ACTION_LABELS: Record<AdminActionType, string> = {
  inscription: "Inscription",
  connexion: "Connexion",
  deconnexion: "Déconnexion",
  compte_bloque: "Compte bloqué (tentatives incorrectes)",
  profil_personnel_modifie: "Profil modifié par l’utilisateur",
  mot_de_passe_modifie: "Mot de passe modifié",
  mot_de_passe_reinitialise: "Mot de passe réinitialisé",
  materiel_cree: "Matériel publié",
  materiel_modifie: "Matériel modifié",
  location_demandee: "Location demandée",
  location_confirmee: "Location confirmée",
  location_refusee: "Location refusée",
  location_annulee: "Location annulée",
  location_terminee: "Location terminée",
  prolongation_demandee: "Prolongation demandée",
  prolongation_confirmee: "Prolongation acceptée",
  prolongation_refusee: "Prolongation refusée",
  reclamation_creee: "Plainte d’un locataire reçue",
  reclamation_mise_a_jour: "Suivi de plainte mis à jour",
  statut_modifie: "Statut du compte modifié",
  compte_debloque: "Compte débloqué",
  profil_modifie: "Profil modifié par un administrateur",
  compte_supprime: "Compte supprimé (corbeille)",
  compte_restaure: "Compte restauré",
  compte_purge: "Compte supprimé définitivement",
  admin_cree: "Administrateur créé",
  materiel_disponibilite: "Disponibilité du matériel modifiée",
  materiel_supprime: "Matériel supprimé (corbeille)",
  materiel_restaure: "Matériel restauré",
  materiel_purge: "Matériel supprimé définitivement",
  categorie_creee: "Catégorie créée",
  categorie_modifiee: "Catégorie modifiée",
  categorie_supprimee: "Catégorie supprimée (corbeille)",
  categorie_restauree: "Catégorie restaurée",
  categorie_purgee: "Catégorie supprimée définitivement",
  role_modifie: "Rôle modifié (ancienne fonction)",
};

export const ACTION_GROUPS: { label: string; actions: AdminActionType[] }[] = [
  {
    label: "Compte et sécurité",
    actions: [
      "inscription",
      "connexion",
      "deconnexion",
      "compte_bloque",
      "profil_personnel_modifie",
      "mot_de_passe_modifie",
      "mot_de_passe_reinitialise",
    ],
  },
  {
    label: "Matériels",
    actions: ["materiel_cree", "materiel_modifie", "materiel_disponibilite", "materiel_supprime"],
  },
  {
    label: "Locations",
    actions: [
      "location_demandee",
      "location_confirmee",
      "location_refusee",
      "location_annulee",
      "location_terminee",
      "prolongation_demandee",
      "prolongation_confirmee",
      "prolongation_refusee",
    ],
  },
  {
    label: "Plaintes",
    actions: ["reclamation_creee", "reclamation_mise_a_jour"],
  },
  {
    label: "Administration et corbeille",
    actions: [
      "statut_modifie",
      "compte_debloque",
      "profil_modifie",
      "admin_cree",
      "compte_supprime",
      "compte_restaure",
      "compte_purge",
      "materiel_restaure",
      "materiel_purge",
      "categorie_creee",
      "categorie_modifiee",
      "categorie_supprimee",
      "categorie_restauree",
      "categorie_purgee",
    ],
  },
];

export const ACTIONS = Object.keys(ACTION_LABELS) as AdminActionType[];

export const ROLE_LABELS: Record<string, string> = {
  locataire: "Locataire",
  proprietaire: "Propriétaire",
  admin: "Administrateur",
  super_admin: "Super-administrateur",
};

export function isAction(value: string): value is AdminActionType {
  return ACTIONS.some((action) => action === value);
}

function text(value: unknown): string | null {
  return typeof value === "string" && value.length > 0 ? value : null;
}

/** Détail lisible d'une entrée de l'historique, quand elle en porte un. */
export function describeDetails(entry: AdminAction): string | null {
  const details = entry.details;
  if (!details) return null;

  const parts: string[] = [];

  if (entry.cible_type === "location") {
    const locataire = text(details.locataire);
    const proprietaire = text(details.proprietaire);
    const debut = text(details.date_debut);
    const fin = text(details.date_fin);
    if (locataire) parts.push(`Locataire : ${locataire}`);
    if (proprietaire) parts.push(`Propriétaire : ${proprietaire}`);
    if (debut && fin) parts.push(`du ${formatDate(debut)} au ${formatDate(fin)}`);
    const note = text(details.note);
    if (note) parts.push(`Note : ${note}`);
    return parts.join(" · ") || null;
  }

  switch (entry.action) {
    case "inscription":
      return Array.isArray(details.roles)
        ? `Compte ${details.roles.map((role) => ROLE_LABELS[String(role)] ?? String(role)).join(" et ")}`
        : null;
    case "compte_bloque":
      return `${String(details.tentatives ?? 3)} tentatives de connexion incorrectes`;
    case "statut_modifie":
      return details.is_active ? "Compte réactivé" : "Compte désactivé";
    case "materiel_disponibilite":
      return details.disponible ? "Rendu disponible" : "Masqué du catalogue";
    case "categorie_modifiee":
      return text(details.ancien_nom) ? `Ancien nom : ${String(details.ancien_nom)}` : null;
    case "compte_purge":
      return details.resultat === "anonymise"
        ? "Anonymisé : historique des achats conservé"
        : "Supprimé sans historique";
    case "profil_modifie":
    case "profil_personnel_modifie":
      return Array.isArray(details.champs)
        ? `Champs : ${details.champs.map(String).join(", ")}`
        : null;
    default:
      return null;
  }
}
