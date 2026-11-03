import { ChevronLeft, ChevronRight } from "lucide-react";
import type { PaginationMeta } from "@/types/api";
import { Button } from "./ui/button";

function pages(current: number, last: number): (number | "…")[] {
  const out: (number | "…")[] = [];
  for (let p = 1; p <= last; p++) {
    if (p === 1 || p === last || Math.abs(p - current) <= 1) out.push(p);
    else if (out[out.length - 1] !== "…") out.push("…");
  }
  return out;
}

export function PaginationBar({ meta, onPage }: { meta: PaginationMeta; onPage: (p: number) => void }) {
  if (meta.last_page <= 1) return null;
  const { current_page: cur, last_page: last } = meta;
  return (
    <nav aria-label="Pagination" className="mt-8 flex items-center justify-center gap-1.5">
      <Button variant="outline" size="icon" disabled={cur <= 1} onClick={() => onPage(cur - 1)} aria-label="Page précédente">
        <ChevronLeft />
      </Button>
      <div className="hidden items-center gap-1.5 sm:flex">
        {pages(cur, last).map((p, i) =>
          p === "…" ? (
            <span key={`e${i}`} className="px-2 text-muted-foreground">
              …
            </span>
          ) : (
            <Button
              key={p}
              variant={p === cur ? "default" : "ghost"}
              size="icon"
              aria-current={p === cur ? "page" : undefined}
              aria-label={`Page ${p}`}
              onClick={() => onPage(p)}
            >
              {p}
            </Button>
          ),
        )}
      </div>
      <span className="px-3 text-sm text-muted-foreground sm:hidden">
        Page {cur} / {last}
      </span>
      <Button variant="outline" size="icon" disabled={cur >= last} onClick={() => onPage(cur + 1)} aria-label="Page suivante">
        <ChevronRight />
      </Button>
    </nav>
  );
}
