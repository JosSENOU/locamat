import { cn } from "@/lib/utils";
import { formatFCFA } from "@/lib/format";

export function PriceTag({
  value,
  suffix = "/ jour",
  className,
  size = "md",
}: {
  value: string | number;
  suffix?: string | null;
  className?: string;
  size?: "sm" | "md" | "lg";
}) {
  return (
    <span className={cn("inline-flex items-baseline gap-1", className)}>
      <span
        className={cn(
          "font-extrabold tracking-tight text-foreground",
          size === "sm" && "text-sm",
          size === "md" && "text-lg",
          size === "lg" && "text-3xl",
        )}
      >
        {formatFCFA(value)}
      </span>
      {suffix && <span className="text-xs font-medium text-muted-foreground">{suffix}</span>}
    </span>
  );
}
