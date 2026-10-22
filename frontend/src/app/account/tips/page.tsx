"use client";

import Link from "next/link";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { Badge } from "@/components/ui/Badge";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";
import type { Tip } from "@/lib/types";

export default function MyTipsPage() {
  const { t, f } = useI18n();
  const { items, meta, error, page, setPage } = useApiList<Tip>("/me/tips");
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">{t.account.tipsTitle}</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title={t.account.noTips} /> : (
        <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
          {items.map((tip) => (
            <li key={tip.id} className="flex flex-wrap items-center justify-between gap-2 p-4">
              <div>
                <Link href={`/account/tips/${tip.id}`} className="font-semibold hover:underline">{tip.subject}</Link>
                <p className="text-sm text-muted">{t.account.tipMeta(tip.investigator.public_name, f.dateTime(tip.last_message_at), !!tip.anonymous_to_investigator)}</p>
              </div>
              <Badge>{labelFor(t.statuses, tip.status)}</Badge>
            </li>
          ))}
        </ul>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
