import Link from "next/link";
import { InvestigationCard, InvestigationGrid } from "@/components/investigation/InvestigationCard";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { JsonLd } from "@/components/site/JsonLd";
import { ButtonLink } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/Card";
import { getCategories, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";
import { getI18n } from "@/i18n/server";
import { SITE_URL } from "@/lib/site";
import type { CmsPage, LeaderboardResponse } from "@/lib/types";

export const revalidate = 60;

function Rail({ title, href, seeAll, children }: { title: string; href: string; seeAll: string; children: React.ReactNode }) {
  return (
    <section className="space-y-4" aria-labelledby={`rail-${href}`}>
      <div className="flex items-baseline justify-between">
        <h2 id={`rail-${href}`} className="font-serif text-2xl font-bold">{title}</h2>
        <Link href={href} className="text-sm text-accent hover:underline">{seeAll}</Link>
      </div>
      {children}
    </section>
  );
}

export default async function Home() {
  const { t, f } = await getI18n();
  const [latest, trending, mostRead, discussed, categories, board, announcements] = await Promise.all([
    searchInvestigations({ sort: "latest" }, 6),
    searchInvestigations({ sort: "trending" }, 3),
    searchInvestigations({ sort: "most_read" }, 3),
    searchInvestigations({ sort: "most_discussed" }, 3),
    getCategories(),
    serverGetOrNull<LeaderboardResponse>("/leaderboards/support", { params: { limit: 5 }, revalidate: 300 }),
    serverGetOrNull<{ data: CmsPage[] }>("/announcements", { revalidate: 300 }),
  ]);

  return (
    <div className="space-y-14">
      <JsonLd data={{ "@context": "https://schema.org", "@type": "WebSite", name: t.brand.name, url: SITE_URL, potentialAction: { "@type": "SearchAction", target: `${SITE_URL}/search?q={query}`, "query-input": "required name=query" } }} />
      <JsonLd data={{ "@context": "https://schema.org", "@type": "Organization", name: t.brand.name, url: SITE_URL, description: t.brand.disclaimer }} />

      <section className="rounded-xl border border-line bg-surface px-6 py-10 sm:px-10">
        <h1 className="max-w-3xl font-serif text-4xl font-bold leading-tight sm:text-5xl">{t.home.heroTitle}</h1>
        <p className="mt-4 max-w-2xl text-lg text-muted">{t.home.heroLead}</p>
        <p className="mt-3 max-w-2xl text-sm text-muted">{t.brand.disclaimer}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <ButtonLink href="/search">{t.home.explore}</ButtonLink>
          <ButtonLink href="/dashboard/onboarding" variant="secondary">{t.home.publish}</ButtonLink>
        </div>
      </section>

      {announcements && announcements.data.length > 0 && (
        <section aria-label={t.home.announcements} className="rounded-lg border border-line bg-accent-soft p-4 text-sm">
          {announcements.data.slice(0, 2).map((a) => (
            <p key={a.slug}><strong>{a.title}</strong> — {a.excerpt} <span className="text-muted">({f.date(a.published_at)})</span></p>
          ))}
        </section>
      )}

      <Rail title={t.home.latest} href="/search?sort=latest" seeAll={t.common.seeAll}>
        {latest.data.length ? <InvestigationGrid items={latest.data} /> : <EmptyState title={t.home.noneYet} />}
      </Rail>

      <div className="grid gap-10 lg:grid-cols-3">
        <Rail title={t.home.trending} href="/search?sort=trending" seeAll={t.common.seeAll}><div className="space-y-4">{trending.data.map((i) => <InvestigationCard key={i.id} item={i} compact />)}</div></Rail>
        <Rail title={t.home.mostRead} href="/search?sort=most_read" seeAll={t.common.seeAll}><div className="space-y-4">{mostRead.data.map((i) => <InvestigationCard key={i.id} item={i} compact />)}</div></Rail>
        <Rail title={t.home.mostDiscussed} href="/search?sort=most_discussed" seeAll={t.common.seeAll}><div className="space-y-4">{discussed.data.map((i) => <InvestigationCard key={i.id} item={i} compact />)}</div></Rail>
      </div>

      <div className="grid gap-10 lg:grid-cols-2">
        <section aria-labelledby="cats">
          <h2 id="cats" className="mb-4 font-serif text-2xl font-bold">{t.home.browseCategories}</h2>
          <ul className="grid grid-cols-2 gap-2">
            {categories.map((c) => (
              <li key={c.slug}>
                <Link href={`/category/${c.slug}`} className="flex justify-between rounded-md border border-line bg-surface px-3 py-2 text-sm hover:bg-accent-soft">
                  <span>{c.name}</span><span className="text-muted">{f.number(c.investigations_count ?? 0)}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
        <section aria-labelledby="top">
          <div className="mb-4 flex items-baseline justify-between">
            <h2 id="top" className="font-serif text-2xl font-bold">{t.home.topSupported}</h2>
            <Link href="/leaderboards" className="text-sm text-accent hover:underline">{t.home.communityLink}</Link>
          </div>
          <p className="mb-3 text-xs text-muted">{t.brand.supportNote}</p>
          <div className="space-y-2">
            {board?.data.length ? board.data.map((r) => <InvestigatorCard key={r.investigator.id} inv={r.investigator} />) : <EmptyState title={t.home.noSupporters} />}
          </div>
        </section>
      </div>
    </div>
  );
}
