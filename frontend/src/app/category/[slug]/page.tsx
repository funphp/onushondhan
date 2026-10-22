import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ResultsList } from "@/components/investigation/ResultsList";
import { getI18n } from "@/i18n/server";
import { pickFilters, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";
import type { Category } from "@/lib/types";

async function getCategory(slug: string) {
  return (await serverGetOrNull<{ data: Category }>(`/categories/${encodeURIComponent(slug)}`, { revalidate: 300 }))?.data ?? null;
}

export async function generateMetadata(props: PageProps<"/category/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  const { t } = await getI18n();
  const c = await getCategory(slug);
  if (!c) return { title: t.meta.categoryNotFound };
  return { title: t.meta.categoryInvestigations(c.name), description: c.description ?? t.search.categoryMetaDescription(c.name), alternates: { canonical: `/category/${c.slug}` } };
}

export default async function CategoryPage(props: PageProps<"/category/[slug]">) {
  const { slug } = await props.params;
  const { t } = await getI18n();
  const category = await getCategory(slug);
  if (!category) notFound();
  const filters = { ...pickFilters(await props.searchParams), category: category.slug };
  const result = await searchInvestigations(filters);

  return (
    <div className="space-y-6">
      <nav aria-label={t.common.breadcrumb} className="text-sm text-muted">
        <Link href="/">{t.common.home}</Link> / <Link href="/search">{t.nav.discover}</Link>
        {category.parent && <> / <Link href={`/category/${category.parent}`}>{category.parent}</Link></>} / <span aria-current="page">{category.name}</span>
      </nav>
      <h1 className="font-serif text-3xl font-bold">{category.name}</h1>
      {category.description && <p className="text-muted">{category.description}</p>}
      {category.children && category.children.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {category.children.map((c) => (
            <li key={c.slug}><Link href={`/category/${c.slug}`} className="rounded-full border border-line px-3 py-1 text-sm hover:bg-accent-soft">{c.name}</Link></li>
          ))}
        </ul>
      )}
      <ResultsList result={result} base={`/category/${category.slug}`} filters={{ ...filters, category: undefined }} />
    </div>
  );
}
