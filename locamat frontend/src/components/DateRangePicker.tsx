import type { DateRange } from "react-day-picker";
import { fr } from "date-fns/locale";
import { startOfDay } from "date-fns";
import { Calendar } from "./ui/calendar";
import type { DayRange } from "@/lib/booking";

export function DateRangePicker({
  value,
  onChange,
  booked,
  disabled = false,
}: {
  value: DateRange | undefined;
  onChange: (r: DateRange | undefined) => void;
  booked: DayRange[];
  disabled?: boolean;
}) {
  const today = startOfDay(new Date());
  return (
    <div className="rounded-xl border bg-card p-2">
      <Calendar
        mode="range"
        locale={fr}
        selected={value}
        onSelect={onChange}
        numberOfMonths={1}
        disabled={disabled ? true : [{ before: today }, ...booked]}
        modifiers={{ booked }}
        modifiersClassNames={{ booked: "booked-day" }}
        className="mx-auto"
      />
      <div className="flex flex-wrap items-center gap-4 border-t px-3 pt-2 text-xs text-muted-foreground">
        <span className="inline-flex items-center gap-1.5">
          <span aria-hidden className="size-3 rounded bg-destructive/20 ring-1 ring-destructive/40" /> Déjà réservé
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span aria-hidden className="size-3 rounded bg-primary" /> Votre sélection
        </span>
      </div>
    </div>
  );
}
