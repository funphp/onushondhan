import type { Metadata } from "next";
import Link from "next/link";
import { notFound, permanentRedirect } from "next/navigation";
import { cache } from "react";
import { Comments } from "@/components/engagement/Comments";
import { EngagementBar } from "@/components/engagement/EngagementBar";
import { ShareButtons } from "@/components/engagement/ShareButtons";
import { ViewBeacon } from "@/components/engagement/ViewBeacon";
import { InvestigationArticle } from "@/components/investigation/InvestigationArticle";
import { InvestigationCard } from "@/components/investigation/InvestigationCard";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { JsonLd } from "@/components/site/JsonLd";
import { buttonClass } from "@/components/ui/Button";
import { serverGet, serverGetOrNull } from "@/lib/server-api";
import { getI18n } from "@/i18n/server";
import { decodeParam } from "@/lib/format";
import { SITE_URL, absoluteUrl } from "@/lib/site";
import type { InvestigationCard as Card, PublicInvestigation } from "@/lib/types";

export const revalidate = 60;

const getInvestigation = cache(async (id: string) => (await serverGetOrNull<{ data: PublicInvestigation }>(`/investigations/${encodeURIComponent(id)}`, { revalidate: 60 }))?.data ?? null);

export async function generateMetadata(props: PageProps<"/investigation/[id]/[[...slug]]">): Promise<Metadata> {
  const { id, slug } = await props.params;
  const { t } = await getI18n();
  const inv = await getInvestigation(id);
  if (!inv) return { title: t.investigation.notFound, robots: { index: false } };
  if (slug?.[1] === "history") return { title: t.investigation.historyTitle(inv.title), robots: { index: false }, alternates: { canonical: `/investigation/${inv.id}/${inv.slug}` } };
  const path = `/investigation/${inv.id}/${inv.slug}`;
  const description = (inv.summary ?? inv.notice?.message ?? "").slice(0, 300);
  return {
    title: inv.title,
    description,
    alternates: { canonical: path },
    robots: { index: inv.robots.index, follow: inv.robots.follow },
    openGraph: {
      type: "article",
      url: path,
      title: inv.title,
      description,
      siteName: t.brand.name,
      publishedTime: inv.published_at ?? undefined,
      modifiedTime: inv.updated_at ?? undefined,
      section: inv.category?.name,
      tags: inv.tags.map((tag) => tag.name),
      ...(inv.cover_url ? { images: [{ url: inv.cover_url, alt: inv.title }] } : {}),
    },
    twitter: { card: "summary_large_image", title: inv.title, description, ...(inv.cover_url ? { images: [inv.cover_url] } : {}) },
  };
}

export default async function InvestigationPage(props: PageProps<"/investigation/[id]/[[...slug]]">) {
  const { id, slug } = await props.params;
  const { t, f } = await getI18n();
  const inv = await getInvestigation(id);
  if (!inv) notFound();
  const isHistory = slug?.length === 2 && slug[1] === "history";
  if ((slug?.length ?? 0) > 1 && !isHistory) notFound();
  // Non-ASCII (Bangla) slugs may arrive percent-encoded; compare the decoded form.
  if (inv.id !== id || decodeParam(slug?.[0] ?? "") !== inv.slug) permanentRedirect(`/investigation/${inv.id}/${inv.slug}${isHistory ? "/history" : ""}`);
  if (isHistory) return <VersionHistory inv={inv} />;

  const related = inv.body !== null ? await serverGet<{ data: Card[] }>(`/investigations/${inv.id}/related`, { revalidate: 300 }).catch(() => ({ data: [] as Card[] })) : { data: [] as Card[] };
  const canonical = absoluteUrl(`/investigation/${inv.id}/${inv.slug}`);
  const readable = inv.status === "published" || inv.status === "archived";

  return (
    <>
      {readable && (
        <JsonLd data={{
          "@context": "https://schema.org",
          "@type": "Article",
          headline: inv.title,
          description: inv.summary ?? undefined,
          datePublished: inv.published_at ?? undefined,
          dateModified: inv.updated_at ?? undefined,
          mainEntityOfPage: canonical,
          url: canonical,
          articleSection: inv.category?.name,
          keywords: inv.tags.map((tag) => tag.name).join(", ") || undefined,
          author: { "@type": "Person", name: inv.investigator.public_name, url: absoluteUrl(`/investigators/${inv.investigator.slug}`) },
          publisher: { "@type": "Organization", name: t.brand.name, url: SITE_URL },
          isAccessibleForFree: true,
          contentLocation: inv.location_name ? { "@type": "Place", name: inv.location_name } : undefined,
        }} />
      )}
      <JsonLd data={{
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        itemListElement: [
          { "@type": "ListItem", position: 1, name: t.common.home, item: SITE_URL },
          ...(inv.category ? [{ "@type": "ListItem", position: 2, name: inv.category.name, item: absoluteUrl(`/category/${inv.category.slug}`) }] : []),
          { "@type": "ListItem", position: inv.category ? 3 : 2, name: inv.title, item: canonical },
        ],
      }} />
      {inv.status === "published" && <ViewBeacon id={inv.id} />}

      <nav aria-label={t.common.breadcrumb} className="mb-6 text-sm text-muted">
        <Link href="/">{t.common.home}</Link>
        {inv.category && <> / <Link href={`/category/${inv.category.slug}`}>{inv.category.name}</Link></>}
        {" / "}<span aria-current="page" className="line-clamp-1 inline">{inv.title}</span>
      </nav>

      <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-12">
          <InvestigationArticle inv={inv} />
          {inv.version > 1 && (
            <p className="text-sm text-muted">
              {t.investigation.updatedTimes(f.digits(inv.version - 1), inv.version - 1 === 1)}{inv.change_reason && <> {t.investigation.latestChange(inv.change_reason)}</>}{" "}
              <Link href={`/investigation/${inv.id}/${inv.slug}/history`} className="underline">{t.investigation.versionHistory}</Link>
            </p>
          )}
          {readable && <Comments investigationId={inv.id} />}
        </div>

        <aside className="space-y-6" aria-label={t.investigation.actions}>
          <InvestigatorCard inv={inv.investigator} />
          <EngagementBar investigation={inv} />
          {readable && <ShareButtons id={inv.id} url={canonical} title={inv.title} />}
          {inv.status === "published" && (
            <div className="rounded-lg border border-line bg-surface p-4 text-sm">
              <h2 className="mb-1 font-semibold">{t.investigation.tipTitle}</h2>
              <p className="mb-3 text-muted">{t.investigation.tipLead}</p>
              <Link href={`/tips/new?investigation=${inv.id}`} className={buttonClass("primary", "sm")}>{t.investigation.sendTip}</Link>
            </div>
          )}
          <p className="text-sm">
            <Link href={`/report?investigation=${inv.id}`} className="text-muted underline">{t.investigation.reportProblem}</Link>
          </p>
          {related.data.length > 0 && (
            <section aria-labelledby="related-h" className="space-y-3">
              <h2 id="related-h" className="font-semibold">{t.investigation.related}</h2>
              {related.data.slice(0, 4).map((r) => <InvestigationCard key={r.id} item={r} compact />)}
            </section>
          )}
        </aside>
      </div>
    </>
  );
}

async function VersionHistory({ inv }: { inv: PublicInvestigation }) {
  const { t, f } = await getI18n();
  const versions = await serverGet<{ data: { version: number; change_reason: string | null; created_at: string }[] }>(`/investigations/${inv.id}/versions`, { revalidate: 60 }).catch(() => ({ data: [] }));
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <nav aria-label={t.common.breadcrumb} className="text-sm text-muted"><Link href={`/investigation/${inv.id}/${inv.slug}`}>{t.investigation.backToInvestigation}</Link></nav>
      <h1 className="font-serif text-3xl font-bold">{t.investigation.versionHistory}</h1>
      <p className="text-muted">{t.investigation.historyIntro(inv.title, inv.investigator.public_name)}</p>
      <ol className="space-y-3">
        {versions.data.map((v) => (
          <li key={v.version} className="rounded-lg border border-line bg-surface p-4">
            <p className="font-semibold">{t.investigation.versionN(f.digits(v.version))}</p>
            <p className="text-sm text-muted"><time dateTime={v.created_at}>{f.dateTime(v.created_at)}</time></p>
            {v.change_reason && <p className="mt-1 text-sm">{v.change_reason}</p>}
          </li>
        ))}
      </ol>
    </div>
  );
}

