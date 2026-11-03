import { useState } from "react";
import { Camera } from "lucide-react";
import { cn } from "@/lib/utils";
import { photoUrl } from "@/lib/format";
import { categoryImage } from "@/lib/category-images";

export function MaterielPhoto({
  photo,
  alt,
  className,
  iconClassName,
  categorie,
}: {
  categorie?: string | null | undefined;
  photo: string | null | undefined;
  alt: string;
  className?: string;
  iconClassName?: string;
}) {
  const src = photoUrl(photo);
  const [failed, setFailed] = useState<string | null>(null);
  const fallback = categoryImage(categorie);
  const showImg = src && failed !== src;

  return (
    <div className={cn("relative overflow-hidden bg-muted", className)}>
      {showImg ? (
        <img
          src={src}
          alt={alt}
          loading="lazy"
          onError={() => setFailed(src)}
          className="absolute inset-0 size-full object-cover"
        />
      ) : fallback ? (
        <img src={fallback} alt={alt} loading="lazy" className="absolute inset-0 size-full object-cover" />
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
