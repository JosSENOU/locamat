import { useState } from "react";
import { useRouter } from "@tanstack/react-router";
import { Bell, CheckCheck } from "lucide-react";
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
  useUnreadCount,
} from "@/hooks/use-notifications";
import { formatAgo } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { AppNotification } from "@/types/api";
import { Button } from "./ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "./ui/popover";

export function NotificationBell() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const { data: unread = 0 } = useUnreadCount(true);
  const { data, isPending, isError } = useNotifications(open);
  const markRead = useMarkNotificationRead();
  const markAllRead = useMarkAllNotificationsRead();

  const items = data?.data ?? [];

  const openNotification = (n: AppNotification) => {
    if (!n.lu) markRead.mutate(n.id);
    setOpen(false);
    if (n.lien) router.history.push(n.lien);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative"
          aria-label={
            unread > 0
              ? `Notifications, ${unread} non lue${unread > 1 ? "s" : ""}`
              : "Notifications"
          }
        >
          <Bell aria-hidden />
          {unread > 0 && (
            <span
              aria-hidden
              className="absolute -right-0.5 -top-0.5 flex min-w-[1.1rem] items-center justify-center rounded-full bg-destructive px-1 text-[0.65rem] font-bold leading-[1.1rem] text-white"
            >
              {unread > 9 ? "9+" : unread}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-[min(22rem,calc(100vw-1.5rem))] p-0">
        <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
          <p className="text-sm font-bold">Notifications</p>
          {unread > 0 && (
            <Button
              variant="ghost"
              size="sm"
              className="h-7 gap-1 px-2 text-xs"
              disabled={markAllRead.isPending}
              onClick={() => markAllRead.mutate()}
            >
              <CheckCheck aria-hidden className="size-3.5" /> Tout marquer comme lu
            </Button>
          )}
        </div>

        <div className="max-h-[24rem] overflow-y-auto">
          {isPending ? (
            <p className="px-4 py-6 text-center text-sm text-muted-foreground">Chargement…</p>
          ) : isError ? (
            <p className="px-4 py-6 text-center text-sm text-destructive">
              Impossible de charger les notifications.
            </p>
          ) : items.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
              Aucune notification pour le moment.
            </p>
          ) : (
            <ul>
              {items.map((n) => (
                <li key={n.id} className="border-b last:border-b-0">
                  <button
                    type="button"
                    onClick={() => openNotification(n)}
                    className={cn(
                      "flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-primary/5 focus-visible:bg-primary/5 focus-visible:outline-none",
                      !n.lu && "bg-primary-soft/60",
                    )}
                  >
                    <span
                      aria-hidden
                      className={cn(
                        "mt-1.5 size-2 shrink-0 rounded-full",
                        n.lu ? "bg-transparent" : "bg-primary",
                      )}
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block text-sm font-semibold">{n.titre}</span>
                      <span className="mt-0.5 block text-sm text-muted-foreground">
                        {n.message}
                      </span>
                      <span className="mt-1 block text-xs text-muted-foreground/80">
                        {formatAgo(n.created_at)}
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
