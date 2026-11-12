import { differenceInCalendarDays, format, formatDistanceToNow } from "date-fns";
import { fr } from "date-fns/locale";
import { API_ORIGIN } from "./api";

/** "il y a 5 minutes" from an ISO timestamp (full datetime, not a date-only value). */
export function formatAgo(iso: string): string {
  return formatDistanceToNow(new Date(iso), { addSuffix: true, locale: fr });
}

/** Takes the YYYY-MM-DD part of an API date and builds a LOCAL date (no timezone shift). */
export function toLocalDate(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split("-").map(Number);
  return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1);
}

/** Formats a local Date as YYYY-MM-DD for the API. */
export function toApiDate(date: Date): string {
  return format(date, "yyyy-MM-dd");
}

export function beninToday(): string {
  const parts = new Intl.DateTimeFormat("en-GB", {
    timeZone: "Africa/Porto-Novo",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(new Date());
  const part = (type: "year" | "month" | "day") =>
    parts.find((value) => value.type === type)?.value ?? "";

  return `${part("year")}-${part("month")}-${part("day")}`;
}

/** True when the API date (YYYY-MM-DD) is strictly before today in Benin. */
export function isDatePassee(iso: string): boolean {
  return iso.slice(0, 10) < beninToday();
}

export function formatDate(iso: string, pattern = "d MMM yyyy"): string {
  return format(toLocalDate(iso), pattern, { locale: fr });
}

/** "10 nov. 2026 à 14h32" from a full ISO timestamp (shown in the viewer's local time). */
export function formatDateTime(iso: string): string {
  return format(new Date(iso), "d MMM yyyy 'à' HH'h'mm", { locale: fr });
}

export function formatDateObj(date: Date, pattern = "d MMM yyyy"): string {
  return format(date, pattern, { locale: fr });
}

export function formatRange(debut: string, fin: string | null): string {
  if (fin === null) return `à partir du ${formatDate(debut)}`;
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

/** "1 an", "2 ans" or "90 jours" : durée de conservation de la corbeille. */
export function formatRetention(days: number): string {
  if (days > 0 && days % 365 === 0) {
    const years = days / 365;
    return `${years} an${years > 1 ? "s" : ""}`;
  }
  return `${days} jour${days > 1 ? "s" : ""}`;
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
  const international = phone.trim().startsWith("+") || digits.startsWith("00");
  if (digits.startsWith("00")) digits = digits.slice(2);
  if (!digits) return null;
  if (!international && !digits.startsWith("229") && digits.length <= 10) digits = `229${digits}`;
  return digits;
}

export function formatBeninPhone(value: string): string {
  let digits = value.replace(/\D/g, "");
  if (digits.startsWith("00229")) digits = digits.slice(5);
  else if (digits.startsWith("229")) digits = digits.slice(3);

  const groups = digits.match(/.{1,2}/g) ?? [];
  return digits ? `+229 ${groups.join(" ")}` : "";
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
