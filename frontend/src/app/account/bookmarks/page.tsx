"use client";

import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { InvestigationGrid } from "@/components/investigation/InvestigationCard";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { useI18n } from "@/i18n/client";
import type { InvestigationCard } from "@/lib/types";

export default function BookmarksPage() {
  const { t } = useI18n();
  const { items, meta, error, page, setPage } = useApiList<InvestigationCard>("/me/bookmarks");
  return (
    <div className="space-y-4">
      <h1 className="font-display text-3xl font-bold">{t.account.bookmarks}</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title={t.account.noBookmarks}>{t.account.noBookmarksHint}</EmptyState> : <InvestigationGrid items={items} />}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
