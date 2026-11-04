import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { cache } from "react";
import { Alert } from "@/components/ui/Alert";
import { Markdown } from "@/components/ui/Markdown";
import { getI18n } from "@/i18n/server";
import { serverGetOrNull } from "@/lib/server-api";
import type { CmsPage } from "@/lib/types";

export const revalidate = 300;

const getPage = cache(async (slug: string) => (await serverGetOrNull<{ data: CmsPage }>(`/pages/${encodeURIComponent(slug)}`, { revalidate: 300 }))?.data ?? null);

export async function generateMetadata(props: PageProps<"/pages/[slug]">): Promise<Metadata> {
  const { t } = await getI18n();
  const page = await getPage((await props.params).slug);
  if (!page) return { title: t.pages.notFound };
  return { title: page.seo_title ?? page.title, description: page.seo_description ?? page.excerpt ?? undefined, alternates: { canonical: page.canonical_url ?? `/pages/${page.slug}` } };
}

export default async function CmsPageView(props: PageProps<"/pages/[slug]">) {
  const { t, f } = await getI18n();
  const page = await getPage((await props.params).slug);
  if (!page) notFound();
  return (
    <article className="mx-auto max-w-3xl space-y-6">
      <header>
        <h1 className="font-display text-4xl font-bold">{page.title}</h1>
        <p className="mt-2 text-sm text-muted">{t.pages.lastUpdated(f.date(page.updated_at))}</p>
      </header>
      {page.requires_legal_review && (
        <Alert tone="warning" title={t.pages.legalTitle}>
          {t.pages.legalBody}
        </Alert>
      )}
      <Markdown>{page.body}</Markdown>
    </article>
  );
}
