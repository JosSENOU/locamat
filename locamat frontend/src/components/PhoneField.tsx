import * as React from "react";
import { Input } from "@/components/ui/input";
import {
  buildPhone,
  DEFAULT_COUNTRY,
  OTHER_COUNTRY,
  PHONE_COUNTRIES,
  splitPhone,
} from "@/lib/phone";
import { cn } from "@/lib/utils";

type PhoneFieldProps = {
  /** Valeur complète, par exemple « +229 01 42 12 34 56 » ou « +33612345678 ». */
  value: string;
  onChange: (value: string) => void;
  onBlur?: () => void;
  name?: string;
  inputRef?: React.Ref<HTMLInputElement>;
  /** Attributs d'accessibilité du champ (id, aria-invalid, aria-describedby). */
  inputProps?: React.ComponentProps<"input">;
  disabled?: boolean;
};

/**
 * Téléphone avec choix du pays : l'utilisateur saisit seulement son numéro national
 * (Bénin : 01 XX XX XX XX). Il peut aussi coller le numéro complet avec « + » : le pays est alors détecté.
 */
export function PhoneField({
  value,
  onChange,
  onBlur,
  name,
  inputRef,
  inputProps,
  disabled,
}: PhoneFieldProps) {
  const [country, setCountry] = React.useState(() => splitPhone(value).country);

  React.useEffect(() => {
    if (!value) return;
    const detected = splitPhone(value, country).country;
    if (detected !== country) setCountry(detected);
  }, [value, country]);

  const isOther = country === OTHER_COUNTRY;
  const isBenin = country === DEFAULT_COUNTRY;
  const national = value ? splitPhone(value, country).national : "";

  const handleInput = (raw: string) => {
    const text = raw.trim();

    if (text.startsWith("+") || text.startsWith("00")) {
      const full = text.startsWith("00") ? `+${text.slice(2)}` : text;
      const parsed = splitPhone(full);
      setCountry(parsed.country);
      onChange(buildPhone(parsed.country, parsed.country === OTHER_COUNTRY ? full : parsed.national));
      return;
    }

    onChange(buildPhone(country, raw));
  };

  return (
    <div className="flex flex-col gap-2 sm:flex-row">
      <select
        aria-label="Pays du numéro de téléphone"
        value={country}
        disabled={disabled}
        onChange={(event) => {
          setCountry(event.target.value);
          onChange("");
        }}
        className={cn(
          "h-10 shrink-0 rounded-md border border-input bg-background px-2 text-sm",
          "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
          "disabled:cursor-not-allowed disabled:opacity-50 sm:w-48",
        )}
      >
        {PHONE_COUNTRIES.map((item) => (
          <option key={item.code} value={item.code}>
            {item.name} (+{item.dial})
          </option>
        ))}
        <option value={OTHER_COUNTRY}>Autre pays (numéro complet)</option>
      </select>
      <Input
        type="tel"
        autoComplete="tel-national"
        inputMode={isOther ? "tel" : "numeric"}
        placeholder={isBenin ? "01 42 12 34 56" : isOther ? "+XXX XXXXXXXX" : "Numéro sans l’indicatif"}
        disabled={disabled}
        {...inputProps}
        name={name}
        ref={inputRef}
        value={national}
        onBlur={onBlur}
        onChange={(event) => handleInput(event.target.value)}
      />
    </div>
  );
}
