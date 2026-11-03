import { cn } from "@/lib/utils";

export function Logo({ className, inverted = false }: { className?: string; inverted?: boolean }) {
  return (
    <span className={cn("inline-flex items-center gap-2 font-display", className)}>
      <span
        aria-hidden
        className={cn(
          "grid size-8 place-items-center rounded-lg text-sm font-extrabold",
          inverted ? "bg-primary-foreground text-primary" : "bg-primary text-primary-foreground",
        )}
      >
        L
        <span className="sr-only">LocaMat</span>
      </span>
      <span
        className={cn(
          "text-xl font-extrabold tracking-tight",
          inverted ? "text-primary-foreground" : "text-foreground",
        )}
      >
        Loca<span className={inverted ? "text-highlight" : "text-primary"}>Mat</span>
        <span className="text-highlight">.</span>
      </span>
    </span>
  );
}
