import { cn } from "@/lib/utils";

export interface TabItem<V extends string> {
  value: V;
  label: string;
  count?: number;
}

export function StatusTabs<V extends string>({
  items,
  value,
  onChange,
  label,
}: {
  items: TabItem<V>[];
  value: V;
  onChange: (v: V) => void;
  label: string;
}) {
  return (
    <div className="-mx-1 mb-6 overflow-x-auto px-1 pb-1">
      <div role="tablist" aria-label={label} className="inline-flex min-w-max gap-1 rounded-xl border bg-card p-1 shadow-card">
        {items.map((item) => {
          const active = item.value === value;
          return (
            <button
              key={item.value}
              role="tab"
              type="button"
              aria-selected={active}
              onClick={() => onChange(item.value)}
              className={cn(
                "inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                active ? "bg-primary text-primary-foreground shadow-sm" : "text-muted-foreground hover:bg-muted hover:text-foreground",
              )}
            >
              {item.label}
              {item.count !== undefined && (
                <span
                  className={cn(
                    "rounded-full px-1.5 text-xs tabular-nums",
                    active ? "bg-primary-foreground/20" : "bg-muted",
                  )}
                >
                  {item.count}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
