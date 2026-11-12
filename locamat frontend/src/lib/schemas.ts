import { z } from "zod";
import { strongPassword } from "./password";
import { phoneError } from "./phone";

const registrationPhone = z
  .string()
  .trim()
  .superRefine((value, ctx) => {
    const message = phoneError(value);
    if (message) ctx.addIssue({ code: "custom", message });
  });

export const loginSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, "L'adresse e-mail est requise.")
    .email("Adresse e-mail invalide."),
  password: z.string().min(1, "Le mot de passe est requis."),
});
export type LoginValues = z.infer<typeof loginSchema>;

export const registerSchema = z
  .object({
    name: z.string().trim().min(2, "Le nom doit contenir au moins 2 caractères.").max(255),
    email: z
      .string()
      .trim()
      .min(1, "L'adresse e-mail est requise.")
      .email("Adresse e-mail invalide."),
    telephone: registrationPhone,
    role: z.enum(["locataire", "proprietaire"], {
      required_error: "Choisissez votre profil.",
      invalid_type_error: "Choisissez votre profil.",
    }),
    // Localisation : demandée uniquement aux propriétaires (voir superRefine).
    ville: z.string().trim().max(100, "100 caractères maximum."),
    quartier: z.string().trim().max(150, "150 caractères maximum."),
    adresse: z.string().trim().max(255, "255 caractères maximum."),
    password: strongPassword(8),
    password_confirmation: z.string().min(1, "Confirmez votre mot de passe."),
  })
  .superRefine((v, ctx) => {
    if (v.password !== v.password_confirmation) {
      ctx.addIssue({
        code: "custom",
        path: ["password_confirmation"],
        message: "Les mots de passe ne correspondent pas.",
      });
    }
    if (v.role === "proprietaire") {
      if (!v.telephone) {
        ctx.addIssue({
          code: "custom",
          path: ["telephone"],
          message: "Le téléphone est obligatoire : les locataires vous contactent par WhatsApp.",
        });
      }
      if (!v.ville) {
        ctx.addIssue({
          code: "custom",
          path: ["ville"],
          message: "Indiquez votre ville : elle apparaît sur vos annonces.",
        });
      }
    }
  });
export type RegisterValues = z.infer<typeof registerSchema>;

export const profileSchema = z.object({
  name: z.string().trim().min(2, "Le nom doit contenir au moins 2 caractères.").max(255),
  telephone: registrationPhone,
  ville: z.string().trim().max(100, "100 caractères maximum.").optional(),
  quartier: z.string().trim().max(150, "150 caractères maximum.").optional(),
  adresse: z.string().trim().max(255, "255 caractères maximum.").optional(),
});
export type ProfileValues = z.infer<typeof profileSchema>;

export const becomeOwnerSchema = z.object({
  telephone: registrationPhone.refine((value) => value.length > 0, "Le téléphone est requis."),
  ville: z.string().trim().min(1, "Indiquez votre ville.").max(100, "100 caractères maximum."),
  quartier: z.string().trim().max(150, "150 caractères maximum."),
  adresse: z.string().trim().max(255, "255 caractères maximum."),
});
export type BecomeOwnerValues = z.infer<typeof becomeOwnerSchema>;

/** Un propriétaire doit avoir un téléphone et une ville (comme à l'inscription). */
export function profileSchemaFor(isProprietaire: boolean) {
  return profileSchema.superRefine((v, ctx) => {
    if (!isProprietaire) return;
    if (!v.telephone) {
      ctx.addIssue({
        code: "custom",
        path: ["telephone"],
        message: "Le téléphone est obligatoire pour un propriétaire.",
      });
    }
    if (!v.ville) {
      ctx.addIssue({
        code: "custom",
        path: ["ville"],
        message: "Indiquez votre ville : elle apparaît sur vos annonces.",
      });
    }
  });
}

export const resetPasswordSchema = z
  .object({
    password: strongPassword(8),
    password_confirmation: z.string().min(1, "Confirmez votre mot de passe."),
  })
  .superRefine((values, ctx) => {
    if (values.password !== values.password_confirmation) {
      ctx.addIssue({
        code: "custom",
        path: ["password_confirmation"],
        message: "Les mots de passe ne correspondent pas.",
      });
    }
  });
export type ResetPasswordValues = z.infer<typeof resetPasswordSchema>;

export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, "Saisissez votre mot de passe actuel."),
    password: strongPassword(8, "Saisissez le nouveau mot de passe."),
    password_confirmation: z.string().min(1, "Confirmez le nouveau mot de passe."),
  })
  .superRefine((values, ctx) => {
    if (values.password !== values.password_confirmation) {
      ctx.addIssue({
        code: "custom",
        path: ["password_confirmation"],
        message: "Les mots de passe ne correspondent pas.",
      });
    }
    if (values.password === values.current_password) {
      ctx.addIssue({
        code: "custom",
        path: ["password"],
        message: "Choisissez un mot de passe différent de l'ancien.",
      });
    }
  });
export type ChangePasswordValues = z.infer<typeof changePasswordSchema>;

export const materielSchema = z
  .object({
    nom: z.string().trim().min(1, "Le nom est requis.").max(255, "255 caractères maximum."),
    categorie: z.string().trim().min(1, "Choisissez une catégorie.").max(255),
    description: z.string().max(5000, "5000 caractères maximum."),
    prix_journalier: z
      .string()
      .trim()
      .min(1, "Le prix par jour est requis.")
      .transform((v) => v.replace(",", ".").replace(/\s/g, ""))
      .refine(
        (v) => /^\d+(\.\d{1,2})?$/.test(v),
        "Prix invalide (nombre positif, 2 décimales max).",
      ),
    quantite: z
      .string()
      .trim()
      .min(1, "Indiquez le nombre d'exemplaires disponibles.")
      .transform(Number)
      .pipe(z.number().int().min(1, "Le stock doit être d'au moins un exemplaire.").max(10000)),
    etat: z.enum(["neuf", "bon", "usé"], { required_error: "Choisissez l'état." }),
    etats_exemplaires: z.array(z.enum(["neuf", "bon", "usé"])).min(1),
    disponible: z.boolean(),
  })
  .superRefine((values, ctx) => {
    if (values.etats_exemplaires.length !== values.quantite) {
      ctx.addIssue({
        code: "custom",
        path: ["etats_exemplaires"],
        message: "Renseignez l’état de chacun des exemplaires indiqués en quantité.",
      });
    }
  });
export type MaterielFormInput = z.input<typeof materielSchema>;
export type MaterielFormValues = z.output<typeof materielSchema>;

/** Profil d'un compte, modifié par un administrateur (localisation : propriétaires uniquement). */
export const adminProfilSchema = z.object({
  name: z.string().trim().min(2, "Le nom doit contenir au moins 2 caractères.").max(255),
  telephone: registrationPhone,
  ville: z.string().trim().max(100, "100 caractères maximum."),
  quartier: z.string().trim().max(150, "150 caractères maximum."),
  adresse: z.string().trim().max(255, "255 caractères maximum."),
});
export type AdminProfilValues = z.infer<typeof adminProfilSchema>;
export type AdminProfilBody = Pick<AdminProfilValues, "name" | "telephone"> &
  Partial<Pick<AdminProfilValues, "ville" | "quartier" | "adresse">>;

export function adminProfilSchemaFor(isOwner: boolean) {
  return adminProfilSchema.superRefine((v, ctx) => {
    if (!isOwner) return;
    if (!v.telephone) {
      ctx.addIssue({
        code: "custom",
        path: ["telephone"],
        message: "Le téléphone est obligatoire pour un propriétaire.",
      });
    }
    if (!v.ville) {
      ctx.addIssue({
        code: "custom",
        path: ["ville"],
        message: "Indiquez la ville : elle apparaît sur les annonces.",
      });
    }
  });
}

/** Création d'un compte administrateur par le super-administrateur (mot de passe de 12 caractères minimum). */
export const administrateurSchema = z
  .object({
    name: z.string().trim().min(2, "Le nom doit contenir au moins 2 caractères.").max(255),
    email: z
      .string()
      .trim()
      .min(1, "L'adresse e-mail est requise.")
      .email("Adresse e-mail invalide."),
    telephone: registrationPhone,
    password: strongPassword(12),
    password_confirmation: z.string().min(1, "Confirmez le mot de passe."),
  })
  .superRefine((values, ctx) => {
    if (values.password !== values.password_confirmation) {
      ctx.addIssue({
        code: "custom",
        path: ["password_confirmation"],
        message: "Les mots de passe ne correspondent pas.",
      });
    }
  });
export type AdministrateurValues = z.infer<typeof administrateurSchema>;

export const refusSchema = z.object({
  motif_refus: z.string().max(1000, "1000 caractères maximum."),
});
