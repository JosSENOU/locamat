import type { AccountRole, Etat, Statut, User } from "@/types/api";

export const BENIN_PHONE_PREFIXES = [
  "20",
  "21",
  "22",
  "23",
  "24",
  "28",
  "29",
  "40",
  "41",
  "42",
  "43",
  "44",
  "45",
  "46",
  "47",
  "48",
  "49",
  "50",
  "51",
  "52",
  "53",
  "54",
  "55",
  "56",
  "57",
  "58",
  "59",
  "60",
  "61",
  "62",
  "63",
  "64",
  "65",
  "66",
  "67",
  "68",
  "69",
  "90",
  "91",
  "92",
  "93",
  "94",
  "95",
  "96",
  "97",
  "98",
  "99",
] as const;

export const ETATS: { value: Etat; label: string }[] = [
  { value: "neuf", label: "Neuf" },
  { value: "bon", label: "Bon état" },
  { value: "usé", label: "Usé" },
];

export const ETAT_LABELS: Record<Etat, string> = { neuf: "Neuf", bon: "Bon état", usé: "Usé" };

export const STATUTS: Statut[] = ["en_attente", "confirmee", "refusee", "annulee", "terminee"];

export function userHasRole(
  user: Pick<User, "role" | "roles"> | null | undefined,
  role: AccountRole,
): boolean {
  return user !== null && user !== undefined && (user.roles?.includes(role) ?? user.role === role);
}

export function userCanRent(user: Pick<User, "role" | "roles"> | null | undefined): boolean {
  return userHasRole(user, "locataire") || userHasRole(user, "proprietaire");
}

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

export function homeFor(role: AccountRole): "/" | "/proprietaire" | "/admin" | "/super-admin" {
  if (role === "proprietaire") return "/proprietaire";
  if (role === "admin") return "/admin";
  if (role === "super_admin") return "/super-admin";
  return "/";
}

/** Only allow same-site relative redirects. */
export function safeRedirect(value: string | undefined): string | null {
  if (!value || !value.startsWith("/") || value.startsWith("//")) return null;
  return value;
}
