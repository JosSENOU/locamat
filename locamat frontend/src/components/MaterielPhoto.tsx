import { useState } from "react";
import { Camera } from "lucide-react";
import { cn } from "@/lib/utils";
import { photoUrl } from "@/lib/format";
import { categoryImage } from "@/lib/category-images";

export function MaterielPhoto({
  photo,
  photos,
  alt,
  className,
  iconClassName,
  categorie,
}: {
  categorie?: string | null | undefined;
  photo: string | null | undefined;
  photos?: string[] | undefined;
  alt: string;
  className?: string;
  iconClassName?: string;
}) {
  const imagePaths = photos?.length ? photos : photo ? [photo] : [];
  const imageSources = imagePaths
    .map((path) => photoUrl(path))
    .filter((src): src is string => src !== null);
  const [failed, setFailed] = useState<Set<string>>(() => new Set());
  const fallback = categoryImage(categorie);

  return (
    <div className={cn("relative overflow-hidden bg-muted", className)}>
      {imageSources.length > 0 ? (
        <div
          className={cn(
            "absolute inset-0 grid gap-0.5 bg-background",
            imageSources.length === 1 ? "grid-cols-1" : "grid-cols-2",
            imageSources.length > 2 && "grid-rows-2",
          )}
        >
          {imageSources.map((src, index) => (
            <div
              key={src}
              className={cn(
                "relative min-h-0 min-w-0 overflow-hidden bg-muted",
                imageSources.length > 2 && index === 0 && "row-span-2",
              )}
            >
              {failed.has(src) && fallback ? (
                <img
                  src={fallback}
                  alt={`${alt}, photo ${index + 1}`}
                  loading="lazy"
                  className="absolute inset-0 size-full object-cover"
                />
              ) : failed.has(src) ? (
                <div
                  role="img"
                  aria-label={`${alt}, photo ${index + 1} indisponible`}
                  className="absolute inset-0 grid place-items-center bg-gradient-to-br from-muted to-secondary"
                >
                  <Camera
                    className={cn("size-8 text-muted-foreground/50", iconClassName)}
                    aria-hidden
                  />
                </div>
              ) : (
                <img
                  src={src}
                  alt={`${alt}, photo ${index + 1}`}
                  loading="lazy"
                  onError={() => setFailed((current) => new Set(current).add(src))}
                  className="absolute inset-0 size-full object-cover"
                />
              )}
            </div>
          ))}
        </div>
      ) : fallback ? (
        <img
          src={fallback}
          alt={alt}
          loading="lazy"
          className="absolute inset-0 size-full object-cover"
        />
      ) : (
        <div
          role="img"
          aria-label={`${alt} (pas de photo)`}
          className="absolute inset-0 grid place-items-center bg-gradient-to-br from-muted to-secondary"
        >
          <Camera className={cn("size-8 text-muted-foreground/50", iconClassName)} aria-hidden />
        </div>
      )}
    </div>
  );
}
