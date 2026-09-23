"use client";

import Link from "next/link";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { Badge } from "@/components/ui/Badge";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { formatDateTime, humanize } from "@/lib/format";
import type { Tip } from "@/lib/types";

export default function MyTipsPage() {
  const { items, meta, error, page, setPage } = useApiList<Tip>("/me/tips");
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">Tips you sent</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title="You have not sent any tips while signed in." /> : (
        <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
          {items.map((t) => (
            <li key={t.id} className="flex flex-wrap items-center justify-between gap-2 p-4">
              <div>
                <Link href={`/account/tips/${t.id}`} className="font-semibold hover:underline">{t.subject}</Link>
                <p className="text-sm text-muted">To {t.investigator.public_name} · {formatDateTime(t.last_message_at)} · {t.anonymous_to_investigator ? "anonymous to investigator" : "identity shared"}</p>
              </div>
              <Badge>{humanize(t.status)}</Badge>
            </li>
          ))}
        </ul>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
