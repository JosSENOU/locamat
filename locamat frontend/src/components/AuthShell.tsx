import type { ReactNode } from "react";
import { CalendarCheck, MapPin, ShieldCheck, Wallet } from "lucide-react";
import { Logo } from "./Logo";

type Variant = "default" | "locataire" | "proprietaire";

const PANELS: Record<
  Variant,
  { title: ReactNode; bullets: { icon: typeof Wallet; text: string }[] }
> = {
  default: {
    title: (
      <>
        Louez malin. <span className="text-highlight">Rentabilisez</span> votre matériel.
      </>
    ),
    bullets: [
      { icon: CalendarCheck, text: "Calendrier clair, aucun conflit de dates." },
      { icon: Wallet, text: "Prix calculé automatiquement, en FCFA." },
      { icon: ShieldCheck, text: "Coordonnées partagées seulement après confirmation." },
    ],
  },
  locataire: {
    title: (
      <>
        Le bon matériel, <span className="text-highlight">près de chez vous.</span>
      </>
    ),
    bullets: [
      { icon: MapPin, text: "Vous voyez où récupérer le matériel avant de réserver." },
      { icon: Wallet, text: "Total en FCFA affiché avant d'envoyer votre demande." },
      { icon: ShieldCheck, text: "Le propriétaire vous répond, vous ne payez rien d'avance." },
    ],
  },
  proprietaire: {
    title: (
      <>
        Votre matériel <span className="text-highlight">travaille pour vous.</span>
      </>
    ),
    bullets: [
      {
        icon: Wallet,
        text: "Vous fixez votre prix par jour et acceptez les demandes qui vous conviennent.",
      },
      {
        icon: MapPin,
        text: "Votre ville et votre quartier aident les locataires à choisir près d'eux.",
      },
      {
        icon: ShieldCheck,
        text: "Votre adresse précise n'est partagée qu'après votre confirmation.",
      },
    ],
  },
};

export function AuthShell({
  title,
  subtitle,
  children,
  variant = "default",
}: {
  title: string;
  subtitle: string;
  children: ReactNode;
  variant?: Variant;
}) {
  const panel = PANELS[variant];
  return (
    <div className="container-page grid min-h-[calc(100vh-4rem)] gap-10 py-10 lg:grid-cols-2 lg:items-center">
      <div className="hidden h-full flex-col justify-between rounded-3xl bg-primary p-10 text-primary-foreground lg:flex">
        <Logo inverted />
        <div>
          <h2 className="text-3xl font-extrabold leading-tight">{panel.title}</h2>
          <ul className="mt-8 space-y-4 text-sm text-primary-foreground/85">
            {panel.bullets.map((b) => (
              <li key={b.text} className="flex gap-3">
                <b.icon className="size-5 shrink-0 text-highlight" aria-hidden /> {b.text}
              </li>
            ))}
          </ul>
        </div>
        <p className="text-xs text-primary-foreground/60">
          Informatique · Photo · Vidéo · Sono · Éclairage
        </p>
      </div>
      <div className="mx-auto w-full max-w-md">
        <h1 className="text-3xl font-extrabold">{title}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{subtitle}</p>
        <div className="mt-8">{children}</div>
      </div>
    </div>
  );
}
