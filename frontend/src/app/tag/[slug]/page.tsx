import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ResultsList } from "@/components/investigation/ResultsList";
import { getI18n } from "@/i18n/server";
import { decodeParam } from "@/lib/format";
import { pickFilters, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";

async function getTag(slug: string) {
  return (await serverGetOrNull<{ data: { name: string; slug: string } }>(`/tags/${encodeURIComponent(decodeParam(slug))}`, { revalidate: 300 }))?.data ?? null;
}

export async function generateMetadata(props: PageProps<"/tag/[slug]">): Promise<Metadata> {
  const { t } = await getI18n();
  const tag = await getTag((await props.params).slug);
  return tag ? { title: `#${tag.name}`, alternates: { canonical: `/tag/${tag.slug}` } } : { title: t.meta.tagNotFound };
}

export default async function TagPage(props: PageProps<"/tag/[slug]">) {
  const { t } = await getI18n();
  const tag = await getTag((await props.params).slug);
  if (!tag) notFound();
  const filters = { ...pickFilters(await props.searchParams), tag: tag.slug };
  const result = await searchInvestigations(filters);
  return (
    <div className="space-y-6">
      <nav aria-label={t.common.breadcrumb} className="text-sm text-muted"><Link href="/">{t.common.home}</Link> / <Link href="/search">{t.nav.discover}</Link> / <span aria-current="page">#{tag.name}</span></nav>
      <h1 className="font-display text-3xl font-bold">{t.search.tagged(tag.name)}</h1>
      <ResultsList result={result} base={`/tag/${tag.slug}`} filters={{ ...filters, tag: undefined }} />
    </div>
  );
}
