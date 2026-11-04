import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import { InvestigatorActions } from "@/components/engagement/InvestigatorActions";
import { ResultsList } from "@/components/investigation/ResultsList";
import { JsonLd } from "@/components/site/JsonLd";
import { Avatar } from "@/components/ui/Avatar";
import { getI18n } from "@/i18n/server";
import { decodeParam, safeHref } from "@/lib/format";
import { pickFilters, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";
import { absoluteUrl } from "@/lib/site";
import type { InvestigatorProfile } from "@/lib/types";

export const revalidate = 60;

const getProfile = cache(async (slug: string) => (await serverGetOrNull<{ data: InvestigatorProfile }>(`/investigators/${encodeURIComponent(decodeParam(slug))}`, { revalidate: 60 }))?.data ?? null);

export async function generateMetadata(props: PageProps<"/investigators/[slug]">): Promise<Metadata> {
  const { t } = await getI18n();
  const p = await getProfile((await props.params).slug);
  if (!p) return { title: t.investigator.notFound, robots: { index: false } };
  return {
    title: p.public_name,
    description: p.bio?.slice(0, 250) ?? t.investigator.profileMeta(p.public_name),
    alternates: { canonical: `/investigators/${p.slug}` },
    openGraph: { type: "profile", title: p.public_name, url: `/investigators/${p.slug}` },
  };
}

export default async function InvestigatorPage(props: PageProps<"/investigators/[slug]">) {
  const { slug } = await props.params;
  const { t, f } = await getI18n();
  const profile = await getProfile(slug);
  if (!profile) notFound();
  const filters = { ...pickFilters(await props.searchParams), investigator: profile.slug };
  const result = await searchInvestigations(filters);
  const website = safeHref(profile.website_url);

  return (
    <div className="space-y-8">
      <JsonLd data={{ "@context": "https://schema.org", "@type": "ProfilePage", mainEntity: { "@type": "Person", name: profile.public_name, url: absoluteUrl(`/investigators/${profile.slug}`), description: profile.bio ?? undefined } }} />
      <nav aria-label={t.common.breadcrumb} className="text-sm text-muted"><Link href="/">{t.common.home}</Link> / <Link href="/investigators">{t.nav.investigators}</Link> / <span aria-current="page">{profile.public_name}</span></nav>
      <header className="flex flex-col gap-6 rounded-xl border border-line bg-surface p-6 sm:flex-row sm:items-start">
        <Avatar name={profile.public_name} src={profile.avatar_url} size={96} />
        <div className="flex-1 space-y-3">
          <h1 className="font-display text-3xl font-bold">{profile.public_name}</h1>
          {profile.bio && <p className="whitespace-pre-line">{profile.bio}</p>}
          {website && <p className="text-sm"><a href={website} rel="noopener noreferrer nofollow ugc" target="_blank" className="text-accent underline">{new URL(website).host}</a></p>}
          <dl className="flex flex-wrap gap-6 text-sm">
            <div><dt className="text-muted">{t.investigator.published}</dt><dd className="font-semibold">{f.number(profile.published_investigations_count)}</dd></div>
            <div><dt className="text-muted">{t.investigator.reads}</dt><dd className="font-semibold">{f.number(profile.total_views)}</dd></div>
            <div><dt className="text-muted">{t.investigator.followers}</dt><dd className="font-semibold">{f.number(profile.followers_count)}</dd></div>
            <div><dt className="text-muted">{t.investigator.supporters}</dt><dd className="font-semibold">{f.number(profile.supporters_count)}</dd></div>
            <div><dt className="text-muted">{t.investigator.since}</dt><dd className="font-semibold">{f.monthYear(profile.joined_at)}</dd></div>
          </dl>
          <p className="text-xs text-muted">{t.brand.supportNote}</p>
          <InvestigatorActions profile={profile} />
        </div>
      </header>
      <section aria-labelledby="inv-h">
        <h2 id="inv-h" className="mb-4 font-display text-2xl font-bold">{t.investigator.investigations}</h2>
        <ResultsList result={result} base={`/investigators/${profile.slug}`} filters={{ ...filters, investigator: undefined }} />
      </section>
    </div>
  );
}
