import informatique from "@/assets/images/categorie-informatique.jpg";
import photo from "@/assets/images/categorie-photo.jpg";
import video from "@/assets/images/categorie-video.jpg";
import videoprojection from "@/assets/images/categorie-videoprojection.jpg";
import sonorisation from "@/assets/images/categorie-sonorisation.jpg";
import eclairage from "@/assets/images/categorie-eclairage.jpg";
import accessoires from "@/assets/images/categorie-accessoires.jpg";
import autre from "@/assets/images/categorie-autre.jpg";

/** Illustration shown when a materiel has no photo (or it fails to load). */
const MAP: Record<string, string> = {
  Informatique: informatique,
  Photo: photo,
  Vidéo: video,
  Vidéoprojection: videoprojection,
  Sonorisation: sonorisation,
  Éclairage: eclairage,
  Accessoires: accessoires,
  Autre: autre,
};

export function categoryImage(categorie: string | null | undefined): string | null {
  return categorie ? (MAP[categorie] ?? null) : null;
}
