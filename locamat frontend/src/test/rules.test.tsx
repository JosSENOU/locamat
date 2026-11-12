import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen } from "@testing-library/react";
import { MaterielPhoto } from "@/components/MaterielPhoto";
import {
  beninToday,
  countDays,
  formatFCFA,
  normalizeWhatsappPhone,
  toLocalDate,
} from "@/lib/format";
import { isBeforeToday, overlapsBooked } from "@/lib/booking";

describe("Règles de gestion LocaMat", () => {
  it("utilise le jour du Bénin pour les échéances, même si UTC est encore la veille", () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2025-01-01T23:30:00.000Z"));

    try {
      expect(beninToday()).toBe("2025-01-02");
    } finally {
      vi.useRealTimers();
    }
  });

  it("affiche toutes les photos du matériel dans la galerie", () => {
    render(
      <MaterielPhoto
        categorie="Photo"
        photo="materiels/face.jpg"
        photos={["materiels/face.jpg", "materiels/profil.jpg", "materiels/dos.jpg"]}
        alt="Appareil photo"
      />,
    );

    expect(screen.getAllByRole("img")).toHaveLength(3);
    expect(screen.getByAltText("Appareil photo, photo 1")).toHaveAttribute(
      "src",
      "http://localhost:8000/storage/materiels/face.jpg",
    );
    expect(screen.getByAltText("Appareil photo, photo 3")).toHaveAttribute(
      "src",
      "http://localhost:8000/storage/materiels/dos.jpg",
    );
  });

  it("affiche une seule image quand le matériel n'en a qu'une", () => {
    render(
      <MaterielPhoto
        categorie="Photo"
        photo="materiels/unique.jpg"
        photos={["materiels/unique.jpg"]}
        alt="Matériel"
      />,
    );

    expect(screen.getAllByRole("img")).toHaveLength(1);
    expect(screen.getByAltText("Matériel, photo 1")).toHaveAttribute(
      "src",
      "http://localhost:8000/storage/materiels/unique.jpg",
    );
  });

  it("remplace une photo indisponible par l'image de sa catégorie", () => {
    render(
      <MaterielPhoto
        categorie="Photo"
        photo="materiels/introuvable.jpg"
        photos={["materiels/introuvable.jpg", "materiels/profil.jpg"]}
        alt="Appareil photo"
      />,
    );

    fireEvent.error(screen.getByAltText("Appareil photo, photo 1"));
    expect(screen.getByAltText("Appareil photo, photo 1").getAttribute("src")).not.toContain(
      "/storage/materiels/introuvable.jpg",
    );
  });

  it("compte les jours début et fin inclus", () => {
    expect(countDays(new Date(2026, 10, 5), new Date(2026, 10, 7))).toBe(3);
    expect(countDays(new Date(2026, 10, 5), new Date(2026, 10, 5))).toBe(1);
  });

  it("lit les dates API sans décalage de fuseau", () => {
    const d = toLocalDate("2026-11-05T00:00:00.000000Z");
    expect([d.getFullYear(), d.getMonth(), d.getDate()]).toEqual([2026, 10, 5]);
  });

  it("détecte un chevauchement avec une période confirmée (bornes incluses)", () => {
    const booked = [{ from: new Date(2026, 10, 10), to: new Date(2026, 10, 12) }];
    expect(overlapsBooked(new Date(2026, 10, 12), new Date(2026, 10, 14), booked)).toBe(true);
    expect(overlapsBooked(new Date(2026, 10, 13), new Date(2026, 10, 14), booked)).toBe(false);
  });

  it("bloque les dates futures quand une location est en retard sans date de retour", () => {
    const booked = [{ from: new Date(2026, 10, 5) }];
    expect(overlapsBooked(new Date(2026, 10, 6), new Date(2026, 10, 7), booked)).toBe(true);
    expect(overlapsBooked(new Date(2026, 10, 4), new Date(2026, 10, 4), booked)).toBe(false);
  });

  it("refuse une date de début antérieure à aujourd'hui", () => {
    const today = new Date(2026, 10, 3);
    expect(isBeforeToday(new Date(2026, 10, 2), today)).toBe(true);
    expect(isBeforeToday(new Date(2026, 10, 3), today)).toBe(false);
  });

  it("formate les prix en FCFA sans décimales inutiles", () => {
    expect(formatFCFA("15000.00").replace(/\s/g, " ")).toBe("15 000 FCFA");
  });

  it("prépare le numéro WhatsApp avec l'indicatif 229", () => {
    expect(normalizeWhatsappPhone("01 23 45 67")).toBe("22901234567");
    expect(normalizeWhatsappPhone("0033612345678")).toBe("33612345678");
  });
});
