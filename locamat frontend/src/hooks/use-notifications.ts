import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import type { AppNotification, NotificationsPage } from "@/types/api";

const POLL_MS = 60_000;

/** Unread badge: a cheap request, refreshed every minute while the tab is visible. */
export function useUnreadCount(enabled: boolean) {
  return useQuery({
    queryKey: queryKeys.notificationsUnread,
    queryFn: ({ signal }) =>
      api<{ unread_count: number }>("/notifications/unread-count", { signal }),
    enabled,
    refetchInterval: POLL_MS,
    select: (data) => data.unread_count,
  });
}

/** Latest notifications, loaded only when the panel is opened. */
export function useNotifications(enabled: boolean) {
  return useQuery({
    queryKey: queryKeys.notificationsList,
    queryFn: ({ signal }) => api<NotificationsPage>("/notifications", { signal }),
    enabled,
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) =>
      api<{ data: AppNotification }>(`/notifications/${id}/read`, { method: "PATCH" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.notifications }),
  });
}

export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => api<{ unread_count: number }>("/notifications/read-all", { method: "PATCH" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.notifications }),
  });
}
