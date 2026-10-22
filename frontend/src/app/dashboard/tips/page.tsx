"use client";

import Link from "next/link";
import { useState } from "react";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { Badge } from "@/components/ui/Badge";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";
import type { Tip } from "@/lib/types";

const FILTERS = [["", "inbox"], ["unread", "unread"], ["important", "important"], ["archived", "archived"], ["closed", "closed"]] as const;

export default function TipInboxPage() {
  const { t, f } = useI18n();
  const [status, setStatus] = useState<string>("");
  const { items, meta, error, page, setPage } = useApiList<Tip>(`/me/inbox/tips${status ? `?status=${status}` : ""}`);
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">{t.inbox.title}</h1>
      <p className="text-sm text-muted">{t.inbox.lead}</p>
      <nav aria-label={t.inbox.folders} className="flex flex-wrap gap-2">
        {FILTERS.map(([v, l]) => (
          <button key={v} type="button" aria-pressed={status === v} onClick={() => { setStatus(v); setPage(1); }} className={`rounded-full px-3 py-1 text-sm ${status === v ? "bg-accent text-white dark:text-paper" : "border border-line"}`}>
            {t.inbox[l]}{v === "unread" && meta?.unread ? ` (${f.number(meta.unread)})` : ""}
          </button>
        ))}
      </nav>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title={t.inbox.none} /> : (
        <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
          {items.map((tip) => (
            <li key={tip.id} className={`p-4 ${tip.status === "new" ? "bg-accent-soft/50" : ""}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <Link href={`/dashboard/tips/${tip.id}`} className={`hover:underline ${tip.status === "new" ? "font-semibold" : ""}`}>{tip.subject}</Link>
                <Badge tone={tip.status === "important" ? "warning" : tip.status === "new" ? "accent" : "neutral"}>{labelFor(t.statuses, tip.status)}</Badge>
              </div>
              <p className="text-sm text-muted">
                {tip.sender?.anonymous ? t.inbox.anonymous(tip.sender.type === "guest") : tip.sender?.username}
                {tip.investigation && <> · {t.inbox.re(tip.investigation.title)}</>} · {f.dateTime(tip.last_message_at)}
              </p>
            </li>
          ))}
        </ul>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
