import { Loader2 } from "lucide-react";
import { Logo } from "./Logo";

export function FullPageLoader({ label = "Chargement…" }: { label?: string }) {
  return (
    <div className="flex min-h-[70vh] flex-col items-center justify-center gap-5" role="status" aria-live="polite">
      <Logo />
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="size-4 animate-spin" aria-hidden />
        {label}
      </div>
    </div>
  );
}
