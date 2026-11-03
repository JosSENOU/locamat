import { differenceInCalendarDays, format } from "date-fns";
import { fr } from "date-fns/locale";
import { API_ORIGIN } from "./api";

/** Takes the YYYY-MM-DD part of an API date and builds a LOCAL date (no timezone shift). */
export function toLocalDate(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split("-").map(Number);
  return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1);
}

/** Formats a local Date as YYYY-MM-DD for the API. */
export function toApiDate(date: Date): string {
  return format(date, "yyyy-MM-dd");
}

export function formatDate(iso: string, pattern = "d MMM yyyy"): string {
  return format(toLocalDate(iso), pattern, { locale: fr });
}

export function formatDateObj(date: Date, pattern = "d MMM yyyy"): string {
  return format(date, pattern, { locale: fr });
}

export function formatRange(debut: string, fin: string): string {
  if (debut.slice(0, 10) === fin.slice(0, 10)) return `le ${formatDate(debut)}`;
  return `du ${formatDate(debut)} au ${formatDate(fin)}`;
}

const nf0 = new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 0 });
const nf2 = new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** "15000.00" -> "15 000 FCFA" ; "15000.50" -> "15 000,50 FCFA" */
export function formatFCFA(value: string | number): string {
  const n = typeof value === "number" ? value : Number.parseFloat(value);
  if (!Number.isFinite(n)) return "— FCFA";
  const isInt = Math.abs(n - Math.round(n)) < 0.005;
  // Intl uses narrow no-break spaces; normalise to a regular no-break space.
  return `${(isInt ? nf0 : nf2).format(n).replace(/\u202f/g, "\u00a0")}\u00a0FCFA`;
}

/** Inclusive number of days between two dates (start and end included). */
export function countDays(start: Date, end: Date): number {
  return differenceInCalendarDays(end, start) + 1;
}

export function photoUrl(photo: string | null | undefined): string | null {
  if (!photo) return null;
  if (/^https?:\/\//.test(photo)) return photo;
  return `${API_ORIGIN}/storage/${photo.replace(/^\/+/, "")}`;
}

/** Normalises a phone number for wa.me (Benin country code 229 by default). */
export function normalizeWhatsappPhone(phone: string | null | undefined): string | null {
  if (!phone) return null;
  let digits = phone.replace(/\D/g, "");
  if (digits.startsWith("00")) digits = digits.slice(2);
  if (!digits) return null;
  if (!digits.startsWith("229") && digits.length <= 10) digits = `229${digits}`;
  return digits;
}

export function whatsappLink(phone: string | null | undefined, text: string): string | null {
  const n = normalizeWhatsappPhone(phone);
  if (!n) return null;
  return `https://wa.me/${n}?text=${encodeURIComponent(text)}`;
}

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join("");
}
