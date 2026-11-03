import { startOfDay } from "date-fns";
import type { Periode } from "@/types/api";
import { toLocalDate } from "./format";

export interface DayRange {
  from: Date;
  to: Date;
}

export function periodesToRanges(periodes: Periode[]): DayRange[] {
  return periodes.map((p) => ({ from: toLocalDate(p.date_debut), to: toLocalDate(p.date_fin) }));
}

/** True when [from, to] (inclusive) overlaps any booked period (inclusive bounds). */
export function overlapsBooked(from: Date, to: Date, booked: DayRange[]): boolean {
  const a = startOfDay(from).getTime();
  const b = startOfDay(to).getTime();
  return booked.some((r) => startOfDay(r.from).getTime() <= b && startOfDay(r.to).getTime() >= a);
}

export function isBeforeToday(date: Date, today: Date = new Date()): boolean {
  return startOfDay(date).getTime() < startOfDay(today).getTime();
}
