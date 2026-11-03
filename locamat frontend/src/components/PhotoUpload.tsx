import { useEffect, useRef, useState, type DragEvent } from "react";
import { Camera, ImagePlus, Trash2, Upload } from "lucide-react";
import { cn } from "@/lib/utils";
import { PHOTO_MAX_BYTES, PHOTO_TYPES } from "@/lib/constants";
import { photoUrl } from "@/lib/format";
import { Button } from "./ui/button";

export function validatePhoto(file: File): string | null {
  if (!PHOTO_TYPES.includes(file.type)) return "Format non accepté (jpg, png ou webp).";
  if (file.size > PHOTO_MAX_BYTES) return "La photo ne doit pas dépasser 2 Mo.";
  return null;
}

export function PhotoUpload({
  file,
  existing,
  id = "photo",
  variant = "default",
  onChange,
  error,
  onError,
}: {
  file: File | null;
  existing?: string | null;
  id?: string;
  variant?: "default" | "avatar";
  onChange: (f: File | null) => void;
  error?: string;
  onError: (msg: string | null) => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [drag, setDrag] = useState(false);
  const [preview, setPreview] = useState<string | null>(null);

  useEffect(() => {
    if (!file) {
      setPreview(null);
      return;
    }
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  const pick = (f: File | undefined) => {
    if (!f) return;
    const err = validatePhoto(f);
    onError(err);
    if (!err) onChange(f);
  };

  const onDrop = (e: DragEvent) => {
    e.preventDefault();
    setDrag(false);
    pick(e.dataTransfer.files[0]);
  };

  const shown = preview ?? photoUrl(existing);

  if (variant === "avatar") {
    return (
      <div className="flex flex-col items-center gap-3 text-center">
        <input
          ref={inputRef}
          id={id}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="sr-only"
          onChange={(e) => {
            pick(e.target.files?.[0]);
            e.target.value = "";
          }}
          aria-invalid={error ? true : undefined}
        />
        <div
          className={cn(
            "relative size-32 overflow-hidden rounded-full bg-primary/10 ring-4 ring-background shadow-lg sm:size-36",
            error && "ring-destructive/40",
          )}
        >
          {shown ? (
            <img
              src={shown}
              alt="Aperçu de la photo de profil"
              className="absolute inset-0 size-full object-cover object-center"
            />
          ) : (
            <div className="absolute inset-0 grid place-items-center text-primary">
              <Camera className="size-10" aria-hidden />
            </div>
          )}
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            aria-label={shown ? "Changer la photo de profil" : "Ajouter une photo de profil"}
            className="absolute inset-x-0 bottom-0 flex h-10 items-center justify-center gap-1.5 bg-foreground/70 text-xs font-semibold text-background transition-colors hover:bg-foreground/85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
          >
            <Upload className="size-3.5" aria-hidden />
            {shown ? "Modifier" : "Ajouter"}
          </button>
        </div>
        <p className="text-xs text-muted-foreground">JPG, PNG ou WEBP · 2 Mo maximum</p>
        {file && (
          <Button type="button" size="sm" variant="outline" onClick={() => onChange(null)}>
            <Trash2 aria-hidden />
            Annuler le changement
          </Button>
        )}
        {error && (
          <p role="alert" className="text-xs font-medium text-destructive">
            {error}
          </p>
        )}
      </div>
    );
  }

  return (
    <div>
      <input
        ref={inputRef}
        id={id}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="sr-only"
        onChange={(e) => {
          pick(e.target.files?.[0]);
          e.target.value = "";
        }}
        aria-invalid={error ? true : undefined}
      />
      {shown ? (
        <div className="relative overflow-hidden rounded-xl border bg-muted">
          <img src={shown} alt="Aperçu de la photo" className="aspect-[4/3] w-full object-cover" />
          <div className="absolute inset-x-0 bottom-0 flex flex-wrap gap-2 bg-gradient-to-t from-foreground/60 to-transparent p-3">
            <Button
              type="button"
              size="sm"
              variant="secondary"
              onClick={() => inputRef.current?.click()}
            >
              <Upload aria-hidden /> Changer
            </Button>
            {file && (
              <Button type="button" size="sm" variant="secondary" onClick={() => onChange(null)}>
                <Trash2 aria-hidden /> {existing ? "Garder l'ancienne" : "Retirer"}
              </Button>
            )}
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          onDragOver={(e) => {
            e.preventDefault();
            setDrag(true);
          }}
          onDragLeave={() => setDrag(false)}
          onDrop={onDrop}
          className={cn(
            "flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed bg-muted/40 p-6 text-center transition-colors hover:border-primary hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
            drag && "border-primary bg-primary-soft",
            error && "border-destructive",
          )}
        >
          <ImagePlus className="size-8 text-primary" aria-hidden />
          <span className="text-sm font-semibold">Glissez une photo ou cliquez pour choisir</span>
          <span className="text-xs text-muted-foreground">JPG, PNG ou WEBP · 2 Mo maximum</span>
        </button>
      )}
      {error && (
        <p role="alert" className="mt-1.5 text-xs font-medium text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
