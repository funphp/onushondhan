"use client";

import Link from "next/link";
import { useAuth } from "@/components/auth/AuthProvider";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { Button } from "@/components/ui/Button";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { api } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import type { Notification } from "@/lib/types";

export default function NotificationsPage() {
  const { refresh } = useAuth();
  const { items, meta, error, page, setPage, reload } = useApiList<Notification>("/me/notifications");
  const markRead = async (id?: string) => {
    await api(id ? `/me/notifications/${id}/read` : "/me/notifications/read-all", { method: "POST" });
    await Promise.all([reload(), refresh()]);
  };
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="font-serif text-3xl font-bold">Notifications</h1>
        <Button size="sm" variant="secondary" onClick={() => markRead()}>Mark all read</Button>
      </div>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title="No notifications yet." /> : (
        <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
          {items.map((n) => (
            <li key={n.id} className={`flex items-start gap-3 p-4 ${n.read_at ? "" : "bg-accent-soft/50"}`}>
              <div className="flex-1">
                <p className={n.read_at ? "" : "font-semibold"}>{n.data.url ? <Link href={n.data.url.replace(/^https?:\/\/[^/]+/, "")} className="hover:underline">{n.data.title}</Link> : n.data.title}</p>
                {typeof n.data.investigation_title === "string" && <p className="text-sm text-muted">{n.data.investigation_title}</p>}
                {typeof n.data.reason === "string" && <p className="text-sm text-muted">Reason: {n.data.reason}</p>}
                {typeof n.data.note === "string" && <p className="text-sm text-muted">{n.data.note}</p>}
                <p className="text-xs text-muted">{formatDateTime(n.created_at)}</p>
              </div>
              {!n.read_at && <Button size="sm" variant="ghost" onClick={() => markRead(n.id)}>Mark read</Button>}
            </li>
          ))}
        </ul>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
