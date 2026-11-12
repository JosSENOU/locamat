import { z } from "zod";

export type PasswordRule = {
  id: string;
  /** Libellé affiché dans la liste de contrôle. */
  label: string;
  /** Fragment utilisé dans le message d'erreur. */
  missing: string;
  test: (value: string) => boolean;
};

export const PASSWORD_MAX_LENGTH = 64;

export function passwordRules(minLength = 8): PasswordRule[] {
  return [
    {
      id: "length",
      label: `Au moins ${minLength} caractères`,
      missing: `au moins ${minLength} caractères`,
      test: (v) => v.length >= minLength,
    },
    {
      id: "upper",
      label: "Une majuscule (A-Z)",
      missing: "une majuscule",
      test: (v) => /\p{Lu}/u.test(v),
    },
    {
      id: "lower",
      label: "Une minuscule (a-z)",
      missing: "une minuscule",
      test: (v) => /\p{Ll}/u.test(v),
    },
    {
      id: "digit",
      label: "Un chiffre (0-9)",
      missing: "un chiffre",
      test: (v) => /\d/.test(v),
    },
    {
      id: "special",
      label: "Un caractère spécial (@ # $ % ! ? …)",
      missing: "un caractère spécial",
      test: (v) => /[^\p{L}\d\s]/u.test(v),
    },
    {
      id: "space",
      label: "Aucun espace",
      missing: "aucun espace",
      test: (v) => !/\s/.test(v),
    },
  ];
}

export function failedPasswordRules(value: string, minLength = 8): PasswordRule[] {
  return passwordRules(minLength).filter((rule) => !rule.test(value));
}

/** Schéma zod d'un nouveau mot de passe : mêmes règles que l'API (Laravel `StrongPassword`). */
export function strongPassword(minLength = 8, requiredMessage = "Le mot de passe est requis.") {
  return z
    .string()
    .min(1, requiredMessage)
    .max(PASSWORD_MAX_LENGTH, `Le mot de passe ne doit pas dépasser ${PASSWORD_MAX_LENGTH} caractères.`)
    .superRefine((value, ctx) => {
      const failed = failedPasswordRules(value, minLength);
      if (failed.length > 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: `Mot de passe trop faible : il faut ${failed.map((rule) => rule.missing).join(", ")}.`,
        });
      }
    });
}
