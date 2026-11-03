import type { ReactNode } from "react";

export function Info({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-lg bg-muted/50 p-3">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="mt-0.5 font-semibold">{children}</dd>
    </div>
  );
}

export function Quote({ label, children, tone }: { label: string; children: ReactNode; tone?: "destructive" }) {
  return (
    <div className={tone === "destructive" ? "rounded-xl border border-destructive/30 bg-destructive/5 p-4" : "rounded-xl border p-4"}>
      <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
      <p className="mt-1 whitespace-pre-line text-sm">{children}</p>
    </div>
  );
}
