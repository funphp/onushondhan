import type { Metadata } from "next";
import { ResultsList } from "@/components/investigation/ResultsList";
import { SearchFilters } from "@/components/investigation/SearchFilters";
import { getI18n } from "@/i18n/server";
import { getCategories, pickFilters, searchInvestigations } from "@/lib/search";

export async function generateMetadata(props: PageProps<"/search">): Promise<Metadata> {
  const { t } = await getI18n();
  const f = pickFilters(await props.searchParams);
  return {
    title: f.q ? t.search.metaTitle(f.q) : t.meta.searchTitle,
    alternates: { canonical: "/search" },
    // Filtered/paged search result pages are not indexed; the sitemap covers content.
    robots: f.q || f.page ? { index: false, follow: true } : undefined,
  };
}

export default async function SearchPage(props: PageProps<"/search">) {
  const { t } = await getI18n();
  const filters = pickFilters(await props.searchParams);
  const [result, categories] = await Promise.all([searchInvestigations(filters), getCategories()]);
  return (
    <div className="space-y-6">
      <h1 className="font-display text-3xl font-bold">{filters.q ? t.search.resultsFor(filters.q) : t.search.title}</h1>
      <SearchFilters params={filters} categories={categories} />
      <ResultsList result={result} base="/search" filters={filters} />
    </div>
  );
}
