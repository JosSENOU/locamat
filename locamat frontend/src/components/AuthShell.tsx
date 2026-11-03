import type { ReactNode } from "react";
import { CalendarCheck, ShieldCheck, Wallet } from "lucide-react";
import { Logo } from "./Logo";

export function AuthShell({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <div className="container-page grid min-h-[calc(100vh-4rem)] gap-10 py-10 lg:grid-cols-2 lg:items-center">
      <div className="hidden h-full flex-col justify-between rounded-3xl bg-primary p-10 text-primary-foreground lg:flex">
        <Logo inverted />
        <div>
          <h2 className="text-3xl font-extrabold leading-tight">
            Louez malin. <span className="text-highlight">Rentabilisez</span> votre matériel.
          </h2>
          <ul className="mt-8 space-y-4 text-sm text-primary-foreground/85">
            <li className="flex gap-3"><CalendarCheck className="size-5 shrink-0 text-highlight" aria-hidden /> Calendrier clair, aucun conflit de dates.</li>
            <li className="flex gap-3"><Wallet className="size-5 shrink-0 text-highlight" aria-hidden /> Prix calculé automatiquement, en FCFA.</li>
            <li className="flex gap-3"><ShieldCheck className="size-5 shrink-0 text-highlight" aria-hidden /> Coordonnées partagées seulement après confirmation.</li>
          </ul>
        </div>
        <p className="text-xs text-primary-foreground/60">Informatique · Photo · Vidéo · Sono · Éclairage</p>
      </div>
      <div className="mx-auto w-full max-w-md">
        <h1 className="text-3xl font-extrabold">{title}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{subtitle}</p>
        <div className="mt-8">{children}</div>
      </div>
    </div>
  );
}
