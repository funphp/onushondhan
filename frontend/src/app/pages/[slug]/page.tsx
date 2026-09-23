import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { cache } from "react";
import { Alert } from "@/components/ui/Alert";
import { Markdown } from "@/components/ui/Markdown";
import { formatDate } from "@/lib/format";
import { serverGetOrNull } from "@/lib/server-api";
import type { CmsPage } from "@/lib/types";

export const revalidate = 300;

const getPage = cache(async (slug: string) => (await serverGetOrNull<{ data: CmsPage }>(`/pages/${encodeURIComponent(slug)}`, { revalidate: 300 }))?.data ?? null);

export async function generateMetadata(props: PageProps<"/pages/[slug]">): Promise<Metadata> {
  const page = await getPage((await props.params).slug);
  if (!page) return { title: "Page not found" };
  return { title: page.seo_title ?? page.title, description: page.seo_description ?? page.excerpt ?? undefined, alternates: { canonical: page.canonical_url ?? `/pages/${page.slug}` } };
}

export default async function CmsPageView(props: PageProps<"/pages/[slug]">) {
  const page = await getPage((await props.params).slug);
  if (!page) notFound();
  return (
    <article className="mx-auto max-w-3xl space-y-6">
      <header>
        <h1 className="font-serif text-4xl font-bold">{page.title}</h1>
        <p className="mt-2 text-sm text-muted">Last updated {formatDate(page.updated_at)}</p>
      </header>
      {page.requires_legal_review && (
        <Alert tone="warning" title="Template pending legal review">
          This text is a product-policy template and has not yet been reviewed by legal counsel. It is not legal advice.
        </Alert>
      )}
      <Markdown>{page.body}</Markdown>
    </article>
  );
}
