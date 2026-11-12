import { describe, expect, it } from "vitest";
import { BENIN_PHONE_PREFIXES } from "@/lib/constants";
import { materielSchema, profileSchemaFor, registerSchema } from "@/lib/schemas";
import { formatBeninPhone } from "@/lib/format";

const base = {
  name: "Awa Dossou",
  email: "awa@example.com",
  telephone: "",
  ville: "",
  quartier: "",
  adresse: "",
  password: "Password@123",
  password_confirmation: "Password@123",
};

function errorPaths(result: { success: boolean; error?: { issues: { path: PropertyKey[] }[] } }) {
  return result.success ? [] : (result.error?.issues.map((i) => String(i.path[0])) ?? []);
}

describe("Formulaire d'inscription selon le rôle", () => {
  it("un locataire n'a besoin ni de téléphone ni de localisation", () => {
    expect(registerSchema.safeParse({ ...base, role: "locataire" }).success).toBe(true);
  });

  it("un propriétaire doit renseigner son téléphone et sa ville", () => {
    const paths = errorPaths(registerSchema.safeParse({ ...base, role: "proprietaire" }));
    expect(paths).toContain("telephone");
    expect(paths).toContain("ville");
  });

  it("un propriétaire avec téléphone et ville est accepté (quartier et adresse facultatifs)", () => {
    const result = registerSchema.safeParse({
      ...base,
      role: "proprietaire",
      telephone: "+229 01 42 12 34 56",
      ville: "Cotonou",
    });
    expect(result.success).toBe(true);
  });

  it("formate et vérifie le téléphone béninois à l'inscription", () => {
    expect(formatBeninPhone("0102030405")).toBe("+229 01 02 03 04 05");
    expect(formatBeninPhone("+229 01 02 03 04 05")).toBe("+229 01 02 03 04 05");
    const formattedLongNumber = formatBeninPhone("010203040599");
    expect(formattedLongNumber).toBe("+229 01 02 03 04 05 99");
    expect(
      registerSchema.safeParse({
        ...base,
        role: "locataire",
        telephone: "+229 01 42 12 34 56",
      }).success,
    ).toBe(true);
    expect(
      registerSchema.safeParse({
        ...base,
        role: "locataire",
        telephone: "+229 97 00 00 00",
      }).success,
    ).toBe(false);
    expect(
      registerSchema.safeParse({
        ...base,
        role: "locataire",
        telephone: "+229 01 25 12 34 56",
      }).success,
    ).toBe(false);
    expect(
      registerSchema.safeParse({
        ...base,
        role: "locataire",
        telephone: formattedLongNumber,
      }).success,
    ).toBe(false);
  });

  it("accepte chacun des préfixes béninois autorisés", () => {
    for (const prefix of BENIN_PHONE_PREFIXES) {
      expect(
        registerSchema.safeParse({
          ...base,
          role: "locataire",
          telephone: `+229 01 ${prefix} 12 34 56`,
        }).success,
      ).toBe(true);
    }
  });

  it("refuse une confirmation de mot de passe différente", () => {
    const paths = errorPaths(
      registerSchema.safeParse({ ...base, role: "locataire", password_confirmation: "autre" }),
    );
    expect(paths).toContain("password_confirmation");
  });

  it("impose un mot de passe fort : longueur, majuscule, minuscule, chiffre et caractère spécial", () => {
    const faibles = [
      "password",
      "password123",
      "PASSWORD@123",
      "Password@abc",
      "Password123",
      "Pa@1",
    ];
    for (const password of faibles) {
      const result = registerSchema.safeParse({
        ...base,
        role: "locataire",
        password,
        password_confirmation: password,
      });
      expect(result.success, password).toBe(false);
      expect(errorPaths(result)).toContain("password");
    }
  });

  it("accepte le numéro d'un autre pays avec son indicatif", () => {
    for (const telephone of ["+33612345678", "+2348012345678", "+2250102030405"]) {
      expect(
        registerSchema.safeParse({ ...base, role: "locataire", telephone }).success,
        telephone,
      ).toBe(true);
    }
    expect(
      registerSchema.safeParse({ ...base, role: "locataire", telephone: "+33 12" }).success,
    ).toBe(false);
  });
});

describe("Profil selon le rôle", () => {
  it("exige la ville d'un propriétaire, pas celle d'un locataire", () => {
    const values = { name: "Awa Dossou", telephone: "+229 01 23 45 67 89", ville: "" };
    expect(profileSchemaFor(true).safeParse(values).success).toBe(false);
    expect(profileSchemaFor(false).safeParse({ name: "Awa Dossou", telephone: "" }).success).toBe(
      true,
    );
    expect(
      profileSchemaFor(false).safeParse({ name: "Awa Dossou", telephone: "+229 01 25 12 34 56" })
        .success,
    ).toBe(false);
  });
});

describe("Stock du matériel", () => {
  const materiel = {
    nom: "Appareil photo",
    categorie: "Photo",
    description: "",
    prix_journalier: "15000",
    quantite: "3",
    etat: "bon",
    etats_exemplaires: ["neuf", "bon", "usé"],
    disponible: true,
  };

  it("convertit le nombre d'exemplaires en quantité entière", () => {
    expect(materielSchema.parse(materiel).quantite).toBe(3);
    expect(materielSchema.safeParse({ ...materiel, quantite: "0" }).success).toBe(false);
    expect(materielSchema.safeParse({ ...materiel, quantite: "1.5" }).success).toBe(false);
  });

  it("exige un état valide pour chacun des exemplaires disponibles", () => {
    expect(
      materielSchema.safeParse({ ...materiel, etats_exemplaires: ["neuf", "usé"] }).success,
    ).toBe(false);
    expect(
      materielSchema.safeParse({ ...materiel, etats_exemplaires: ["neuf", "usé", "bon"] }).success,
    ).toBe(true);
  });
});
