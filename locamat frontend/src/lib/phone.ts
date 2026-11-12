import { BENIN_PHONE_PREFIXES } from "./constants";

export type PhoneCountry = { code: string; name: string; dial: string };

/** Code interne du choix « Autre pays » : l'utilisateur saisit alors le numéro complet avec « + ». */
export const OTHER_COUNTRY = "XX";
export const DEFAULT_COUNTRY = "BJ";

const BENIN_PHONE_COUNTRY: PhoneCountry = { code: DEFAULT_COUNTRY, name: "Bénin", dial: "229" };

export const PHONE_COUNTRIES: PhoneCountry[] = [
  BENIN_PHONE_COUNTRY,
  { code: "TG", name: "Togo", dial: "228" },
  { code: "NG", name: "Nigeria", dial: "234" },
  { code: "NE", name: "Niger", dial: "227" },
  { code: "BF", name: "Burkina Faso", dial: "226" },
  { code: "CI", name: "Côte d’Ivoire", dial: "225" },
  { code: "GH", name: "Ghana", dial: "233" },
  { code: "SN", name: "Sénégal", dial: "221" },
  { code: "ML", name: "Mali", dial: "223" },
  { code: "CM", name: "Cameroun", dial: "237" },
  { code: "GA", name: "Gabon", dial: "241" },
  { code: "CG", name: "Congo", dial: "242" },
  { code: "CD", name: "RD Congo", dial: "243" },
  { code: "FR", name: "France", dial: "33" },
  { code: "BE", name: "Belgique", dial: "32" },
  { code: "CH", name: "Suisse", dial: "41" },
  { code: "GB", name: "Royaume-Uni", dial: "44" },
  { code: "DE", name: "Allemagne", dial: "49" },
  { code: "CN", name: "Chine", dial: "86" },
  { code: "CA", name: "Canada / États-Unis", dial: "1" },
];

const BENIN_PATTERN = new RegExp(
  `^\\+229 01 (?:${BENIN_PHONE_PREFIXES.join("|")}) \\d{2} \\d{2} \\d{2}$`,
);

const MAX_E164_DIGITS = 15;

const COUNTRIES_BY_DIAL_LENGTH = [...PHONE_COUNTRIES].sort((a, b) => b.dial.length - a.dial.length);

export function phoneDigits(value: string): string {
  return value.replace(/\D/g, "");
}

function formatBeninNational(digits: string): string {
  return (digits.slice(0, 10).match(/.{1,2}/g) ?? []).join(" ");
}

/**
 * Construit la valeur envoyée à l'API :
 * - Bénin : `+229 01 XX XX XX XX` (le numéro national commence par 01) ;
 * - autre pays : `+indicatif` suivi du numéro sans le 0 initial ;
 * - « Autre pays » : le numéro complet saisi par l'utilisateur, précédé de « + ».
 */
export function buildPhone(countryCode: string, national: string): string {
  let digits = phoneDigits(national);
  if (!national.trim()) return "";

  if (countryCode === OTHER_COUNTRY) return `+${digits.slice(0, MAX_E164_DIGITS)}`;
  if (!digits) return "";

  const country = PHONE_COUNTRIES.find((c) => c.code === countryCode) ?? BENIN_PHONE_COUNTRY;

  if (country.code === "BJ") {
    if (digits.startsWith("229")) digits = digits.slice(3);
    return `+229 ${formatBeninNational(digits)}`;
  }

  const local = digits.replace(/^0+/, "").slice(0, MAX_E164_DIGITS - country.dial.length);
  return local ? `+${country.dial}${local}` : "";
}

/** Retrouve le pays et le numéro national à afficher à partir d'une valeur saisie ou enregistrée. */
export function splitPhone(
  value: string,
  fallbackCountry: string = DEFAULT_COUNTRY,
): { country: string; national: string } {
  const text = value.trim();
  if (!text.startsWith("+")) return { country: fallbackCountry, national: text };

  const digits = phoneDigits(text);
  const country = COUNTRIES_BY_DIAL_LENGTH.find((c) => digits.startsWith(c.dial));
  if (!country) return { country: OTHER_COUNTRY, national: text };

  const rest = digits.slice(country.dial.length);
  return {
    country: country.code,
    national: country.code === "BJ" ? formatBeninNational(rest) : rest,
  };
}

/** Message d'erreur si le numéro est invalide, `null` sinon (un numéro vide est accepté ici). */
export function phoneError(value: string): string | null {
  if (!value) return null;

  if (value.startsWith("+229")) {
    return BENIN_PATTERN.test(value)
      ? null
      : "Numéro béninois invalide : 01 suivi de 8 chiffres avec un préfixe valide (ex. 01 42 12 34 56).";
  }

  return /^\+[1-9]\d{7,14}$/.test(value)
    ? null
    : "Numéro invalide : vérifiez le pays et le nombre de chiffres.";
}
