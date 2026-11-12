import { useEffect, useRef, useState, type DragEvent } from "react";
import { ImagePlus, Trash2, Upload } from "lucide-react";
import { PHOTO_MAX_BYTES, PHOTO_TYPES } from "@/lib/constants";
import { photoUrl } from "@/lib/format";
import { cn } from "@/lib/utils";
import { Button } from "./ui/button";

const MAX_PHOTOS = 3;

function validatePhotos(files: File[]): string | null {
  if (files.length > MAX_PHOTOS) return "Vous pouvez ajouter 3 photos maximum.";

  for (const file of files) {
    if (!PHOTO_TYPES.includes(file.type)) return "Format non accepté (jpg, png ou webp).";
    if (file.size > PHOTO_MAX_BYTES) return "Chaque photo ne doit pas dépasser 2 Mo.";
  }

  return null;
}

export function MaterielPhotosUpload({
  files,
  existing = [],
  onChange,
  error,
  onError,
}: {
  files: File[];
  existing?: string[];
  onChange: (files: File[]) => void;
  error?: string;
  onError: (message: string | null) => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [drag, setDrag] = useState(false);
  const [previews, setPreviews] = useState<string[]>([]);

  useEffect(() => {
    const urls = files.map((file) => URL.createObjectURL(file));
    setPreviews(urls);
    return () => urls.forEach((url) => URL.revokeObjectURL(url));
  }, [files]);

  const pick = (selectedFiles: File[]) => {
    if (selectedFiles.length === 0) return;
    const updatedFiles = [...files, ...selectedFiles];
    const validationError = validatePhotos(updatedFiles);
    onError(validationError);
    if (!validationError) onChange(updatedFiles);
  };

  const onDrop = (event: DragEvent<HTMLButtonElement>) => {
    event.preventDefault();
    setDrag(false);
    pick(Array.from(event.dataTransfer.files));
  };

  const displayedPhotos =
    files.length > 0
      ? previews.map((src, index) => ({
          src,
          alt: `Aperçu de la photo ${index + 1}`,
          selected: true,
        }))
      : existing
          .map((path, index) => ({
            src: photoUrl(path),
            alt: `Photo actuelle ${index + 1}`,
            selected: false,
          }))
          .filter(
            (photo): photo is { src: string; alt: string; selected: boolean } => photo.src !== null,
          );

  return (
    <div>
      <input
        ref={inputRef}
        id="photos"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        className="sr-only"
        onChange={(event) => {
          pick(Array.from(event.currentTarget.files ?? []));
          event.currentTarget.value = "";
        }}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? "photos-error" : undefined}
      />
      {displayedPhotos.length > 0 && (
        <div className="mb-3 grid grid-cols-2 gap-3">
          {displayedPhotos.map((photo, index) => (
            <div
              key={`${photo.src}-${index}`}
              className="relative overflow-hidden rounded-xl border bg-muted"
            >
              <img src={photo.src} alt={photo.alt} className="aspect-[4/3] w-full object-cover" />
              {photo.selected && (
                <Button
                  type="button"
                  size="icon"
                  variant="secondary"
                  className="absolute right-2 top-2"
                  aria-label={`Retirer la photo ${index + 1}`}
                  onClick={() => {
                    const remainingFiles = files.filter((_, fileIndex) => fileIndex !== index);
                    onChange(remainingFiles);
                    onError(null);
                  }}
                >
                  <Trash2 aria-hidden />
                </Button>
              )}
            </div>
          ))}
        </div>
      )}
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        onDragOver={(event) => {
          event.preventDefault();
          setDrag(true);
        }}
        onDragLeave={() => setDrag(false)}
        onDrop={onDrop}
        className={cn(
          "flex min-h-32 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed bg-muted/40 p-5 text-center transition-colors hover:border-primary hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
          drag && "border-primary bg-primary-soft",
          error && "border-destructive",
        )}
      >
        {displayedPhotos.length > 0 ? (
          <>
            <Upload className="size-6 text-primary" aria-hidden />
            <span className="text-sm font-semibold">
              {files.length > 0 ? "Ajouter des photos" : "Remplacer les photos"}
            </span>
          </>
        ) : (
          <>
            <ImagePlus className="size-8 text-primary" aria-hidden />
            <span className="text-sm font-semibold">Ajoutez au moins une photo</span>
            <span className="text-xs text-muted-foreground">
              Sélectionnez plusieurs fichiers ou déposez-les ici
            </span>
          </>
        )}
        <span className="text-xs text-muted-foreground">
          1 à 3 photos · JPG, PNG ou WEBP · 2 Mo maximum chacune
        </span>
      </button>
      {error && (
        <p id="photos-error" role="alert" className="mt-1.5 text-xs font-medium text-destructive">
          {error}
        </p>
      )}
      {files.length > 0 && (
        <Button
          type="button"
          size="sm"
          variant="outline"
          className="mt-2"
          onClick={() => {
            onChange([]);
            onError(null);
          }}
        >
          <Trash2 aria-hidden />
          Annuler le remplacement
        </Button>
      )}
    </div>
  );
}
