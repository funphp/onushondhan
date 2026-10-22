import { EmptyState } from "@/components/ui/Card";
import { Pagination } from "@/components/ui/Pagination";
import { getI18n } from "@/i18n/server";
import { hrefWith } from "@/lib/search";
import type { InvestigationCard, Paginated } from "@/lib/types";
import { InvestigationGrid } from "./InvestigationCard";

export async function ResultsList({ result, base, filters }: { result: Paginated<InvestigationCard>; base: string; filters: Record<string, string | undefined> }) {
  const { t, f } = await getI18n();
  const pages = Array.from({ length: result.meta.last_page }, (_, i) => hrefWith(base, filters, { page: i + 1 }));
  return (
    <section aria-live="polite">
      <p className="mb-4 text-sm text-muted">{t.search.count(f.number(result.meta.total), result.meta.total === 1)}</p>
      {result.data.length === 0 ? (
        <EmptyState title={t.search.noResults}>{t.search.noResultsHint}</EmptyState>
      ) : (
        <InvestigationGrid items={result.data} />
      )}
      <Pagination page={result.meta.current_page} lastPage={result.meta.last_page} hrefs={pages} />
    </section>
  );
}
