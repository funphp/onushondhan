import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ResultsList } from "@/components/investigation/ResultsList";
import { pickFilters, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";

async function getTag(slug: string) {
  return (await serverGetOrNull<{ data: { name: string; slug: string } }>(`/tags/${encodeURIComponent(slug)}`, { revalidate: 300 }))?.data ?? null;
}

export async function generateMetadata(props: PageProps<"/tag/[slug]">): Promise<Metadata> {
  const tag = await getTag((await props.params).slug);
  return tag ? { title: `#${tag.name}`, alternates: { canonical: `/tag/${tag.slug}` } } : { title: "Tag not found" };
}

export default async function TagPage(props: PageProps<"/tag/[slug]">) {
  const tag = await getTag((await props.params).slug);
  if (!tag) notFound();
  const filters = { ...pickFilters(await props.searchParams), tag: tag.slug };
  const result = await searchInvestigations(filters);
  return (
    <div className="space-y-6">
      <nav aria-label="Breadcrumb" className="text-sm text-muted"><Link href="/">Home</Link> / <Link href="/search">Discover</Link> / <span aria-current="page">#{tag.name}</span></nav>
      <h1 className="font-serif text-3xl font-bold">Tagged “{tag.name}”</h1>
      <ResultsList result={result} base={`/tag/${tag.slug}`} filters={{ ...filters, tag: undefined }} />
    </div>
  );
}
