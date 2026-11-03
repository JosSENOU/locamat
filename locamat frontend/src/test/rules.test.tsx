import { describe, expect, it } from "vitest";
import { countDays, formatFCFA, normalizeWhatsappPhone, toLocalDate } from "@/lib/format";
import { isBeforeToday, overlapsBooked } from "@/lib/booking";

describe("Règles de gestion LocaMat", () => {
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
