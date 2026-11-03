import { z } from "zod";
import { CATEGORIES } from "./constants";

const phone = z
  .string()
  .trim()
  .max(30, "Le numéro est trop long.")
  .refine((v) => v === "" || /^[+\d][\d\s().-]{5,}$/.test(v), "Numéro de téléphone invalide.");

export const loginSchema = z.object({
  email: z.string().trim().min(1, "L'adresse e-mail est requise.").email("Adresse e-mail invalide."),
  password: z.string().min(1, "Le mot de passe est requis."),
});
export type LoginValues = z.infer<typeof loginSchema>;

export const registerSchema = z
  .object({
    name: z.string().trim().min(2, "Le nom doit contenir au moins 2 caractères.").max(255),
    email: z.string().trim().min(1, "L'adresse e-mail est requise.").email("Adresse e-mail invalide."),
    telephone: phone,
    role: z.enum(["locataire", "proprietaire"], {
      required_error: "Choisissez votre profil.",
      invalid_type_error: "Choisissez votre profil.",
    }),
    password: z.string().min(8, "Le mot de passe doit contenir au moins 8 caractères."),
    password_confirmation: z.string().min(1, "Confirmez votre mot de passe."),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Les mots de passe ne correspondent pas.",
  });
export type RegisterValues = z.infer<typeof registerSchema>;

export const profileSchema = z.object({
  name: z.string().trim().min(2, "Le nom doit contenir au moins 2 caractères.").max(255),
  telephone: phone,
});
export type ProfileValues = z.infer<typeof profileSchema>;

export const materielSchema = z.object({
  nom: z.string().trim().min(1, "Le nom est requis.").max(255, "255 caractères maximum."),
  categorie: z.enum(CATEGORIES, {
    required_error: "Choisissez une catégorie.",
    invalid_type_error: "Choisissez une catégorie.",
  }),
  description: z.string().max(5000, "5000 caractères maximum."),
  prix_journalier: z
    .string()
    .trim()
    .min(1, "Le prix par jour est requis.")
    .transform((v) => v.replace(",", ".").replace(/\s/g, ""))
    .refine((v) => /^\d+(\.\d{1,2})?$/.test(v), "Prix invalide (nombre positif, 2 décimales max)."),
  etat: z.enum(["neuf", "bon", "usé"], { required_error: "Choisissez l'état." }),
  disponible: z.boolean(),
});
export type MaterielFormInput = z.input<typeof materielSchema>;
export type MaterielFormValues = z.output<typeof materielSchema>;

export const refusSchema = z.object({
  motif_refus: z.string().max(1000, "1000 caractères maximum."),
});
