import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";
import { initials, photoUrl } from "@/lib/format";

export function ProfileAvatar({
  name,
  photo,
  srcOverride,
  className,
}: {
  name: string;
  photo: string | null;
  srcOverride?: string | null;
  className?: string;
}) {
  const src = srcOverride === undefined ? photoUrl(photo) : srcOverride;
  const [failedSrc, setFailedSrc] = useState<string | null>(null);

  useEffect(() => {
    setFailedSrc(null);
  }, [src]);

  return (
    <span
      role="img"
      className={cn(
        "relative inline-grid shrink-0 place-items-center overflow-hidden rounded-full bg-primary font-bold text-primary-foreground",
        className,
      )}
      aria-label={`Photo de profil de ${name}`}
    >
      {src && failedSrc !== src ? (
        <img
          src={src}
          alt=""
          className="absolute inset-0 size-full object-cover object-center"
          onError={() => setFailedSrc(src)}
        />
      ) : (
        initials(name)
      )}
    </span>
  );
}
