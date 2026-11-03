import type { Etat, Role, Statut } from "@/types/api";

export const CATEGORIES = [
  "Informatique",
  "Photo",
  "Vidéo",
  "Vidéoprojection",
  "Sonorisation",
  "Éclairage",
  "Accessoires",
  "Autre",
] as const;
export type Categorie = (typeof CATEGORIES)[number];

export const ETATS: { value: Etat; label: string }[] = [
  { value: "neuf", label: "Neuf" },
  { value: "bon", label: "Bon état" },
  { value: "usé", label: "Usé" },
];

export const ETAT_LABELS: Record<Etat, string> = { neuf: "Neuf", bon: "Bon état", usé: "Usé" };

export const STATUTS: Statut[] = ["en_attente", "confirmee", "refusee", "annulee", "terminee"];

export const STATUT_LABELS: Record<Statut, string> = {
  en_attente: "En attente",
  confirmee: "Confirmée",
  refusee: "Refusée",
  annulee: "Annulée",
  terminee: "Terminée",
};

export const STATUT_PLURAL: Record<Statut, string> = {
  en_attente: "En attente",
  confirmee: "Confirmées",
  refusee: "Refusées",
  annulee: "Annulées",
  terminee: "Terminées",
};

export const PHOTO_MAX_BYTES = 2048 * 1024;
export const PHOTO_TYPES = ["image/jpeg", "image/png", "image/webp"];

export function homeFor(role: Role): "/" | "/proprietaire" | "/admin" {
  if (role === "proprietaire") return "/proprietaire";
  if (role === "admin") return "/admin";
  return "/";
}

/** Only allow same-site relative redirects. */
export function safeRedirect(value: string | undefined): string | null {
  if (!value || !value.startsWith("/") || value.startsWith("//")) return null;
  return value;
}
