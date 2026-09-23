import { EmptyState } from "@/components/ui/Card";
import { Pagination } from "@/components/ui/Pagination";
import { hrefWith } from "@/lib/search";
import type { InvestigationCard, Paginated } from "@/lib/types";
import { InvestigationGrid } from "./InvestigationCard";

export function ResultsList({ result, base, filters }: { result: Paginated<InvestigationCard>; base: string; filters: Record<string, string | undefined> }) {
  return (
    <section aria-live="polite">
      <p className="mb-4 text-sm text-muted">{result.meta.total} {result.meta.total === 1 ? "investigation" : "investigations"}</p>
      {result.data.length === 0 ? (
        <EmptyState title="No investigations match these filters">Try different keywords or remove a filter.</EmptyState>
      ) : (
        <InvestigationGrid items={result.data} />
      )}
      <Pagination page={result.meta.current_page} lastPage={result.meta.last_page} hrefFor={(p) => hrefWith(base, filters, { page: p })} />
    </section>
  );
}
