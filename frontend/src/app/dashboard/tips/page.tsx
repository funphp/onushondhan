"use client";

import Link from "next/link";
import { useState } from "react";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { Badge } from "@/components/ui/Badge";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { formatDateTime, humanize } from "@/lib/format";
import type { Tip } from "@/lib/types";

const FILTERS = [["", "Inbox"], ["unread", "Unread"], ["important", "Important"], ["archived", "Archived"], ["closed", "Closed"]];

export default function TipInboxPage() {
  const [status, setStatus] = useState("");
  const { items, meta, error, page, setPage } = useApiList<Tip>(`/me/inbox/tips${status ? `?status=${status}` : ""}`);
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">Tip inbox</h1>
      <p className="text-sm text-muted">Protect your tipsters: do not try to identify anonymous senders, and never forward tip content without consent.</p>
      <nav aria-label="Tip folders" className="flex flex-wrap gap-2">
        {FILTERS.map(([v, l]) => (
          <button key={v} type="button" aria-pressed={status === v} onClick={() => { setStatus(v); setPage(1); }} className={`rounded-full px-3 py-1 text-sm ${status === v ? "bg-accent text-white dark:text-paper" : "border border-line"}`}>
            {l}{v === "unread" && meta?.unread ? ` (${meta.unread})` : ""}
          </button>
        ))}
      </nav>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title="No tips here." /> : (
        <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
          {items.map((t) => (
            <li key={t.id} className={`p-4 ${t.status === "new" ? "bg-accent-soft/50" : ""}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <Link href={`/dashboard/tips/${t.id}`} className={`hover:underline ${t.status === "new" ? "font-semibold" : ""}`}>{t.subject}</Link>
                <Badge tone={t.status === "important" ? "warning" : t.status === "new" ? "accent" : "neutral"}>{humanize(t.status)}</Badge>
              </div>
              <p className="text-sm text-muted">
                {t.sender?.anonymous ? `Anonymous ${t.sender.type === "guest" ? "guest" : "member"}` : t.sender?.username}
                {t.investigation && <> · re: {t.investigation.title}</>} · {formatDateTime(t.last_message_at)}
              </p>
            </li>
          ))}
        </ul>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
