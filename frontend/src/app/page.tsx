import Link from "next/link";
import type { ReactNode } from "react";
import { CoverStory, InvestigationGrid, NumberedList } from "@/components/investigation/InvestigationCard";
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

/** Section heading in the dossier style: heavy rule above, red tab, display type. */
function Section({ id, title, href, seeAll, children, className }: { id: string; title: string; href?: string; seeAll?: string; children: ReactNode; className?: string }) {
  return (
    <section aria-labelledby={id} className={className}>
      <div className="mb-5 flex items-end justify-between border-t-4 border-ink pt-3">
        <h2 id={id} className="flex items-center gap-2.5 font-display text-2xl font-bold">
          <span aria-hidden className="inline-block h-3.5 w-3.5 bg-accent" />{title}
        </h2>
        {href && <Link href={href} className="text-sm font-medium text-accent hover:underline">{seeAll}</Link>}
      </div>
      {children}
    </section>
  );
}

export default async function Home() {
  const { t, f } = await getI18n();
  const [latest, trending, mostRead, discussed, categories, board, announcements] = await Promise.all([
    searchInvestigations({ sort: "latest" }, 7),
    searchInvestigations({ sort: "trending" }, 5),
    searchInvestigations({ sort: "most_read" }, 5),
    searchInvestigations({ sort: "most_discussed" }, 5),
    getCategories(),
    serverGetOrNull<LeaderboardResponse>("/leaderboards/support", { params: { limit: 4 }, revalidate: 300 }),
    serverGetOrNull<{ data: CmsPage[] }>("/announcements", { revalidate: 300 }),
  ]);
  // The cover story is the newest report with a header image (or simply the newest).
  const lead = latest.data.find((i) => i.cover_url) ?? latest.data[0];
  const rest = latest.data.filter((i) => i.id !== lead?.id).slice(0, 6);

  return (
    <div className="space-y-16">
      <JsonLd data={{ "@context": "https://schema.org", "@type": "WebSite", name: t.brand.name, url: SITE_URL, potentialAction: { "@type": "SearchAction", target: `${SITE_URL}/search?q={query}`, "query-input": "required name=query" } }} />
      <JsonLd data={{ "@context": "https://schema.org", "@type": "Organization", name: t.brand.name, url: SITE_URL, description: t.brand.disclaimer }} />

      <div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-2 border-b border-ink pb-3">
          <h1 className="font-display text-2xl font-bold leading-tight sm:text-3xl">{t.home.heroTitle}</h1>
          <p className="file-stamp text-muted"><time dateTime={new Date().toISOString().slice(0, 10)}>{f.date(new Date().toISOString())}</time></p>
        </div>

        {announcements && announcements.data.length > 0 && (
          <section aria-label={t.home.announcements} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-l-4 border-accent bg-surface px-4 py-2.5 text-sm">
            <span className="file-stamp font-semibold text-accent">{t.home.announcements}</span>
            {announcements.data.slice(0, 1).map((a) => (
              <span key={a.slug}><strong>{a.title}</strong> — {a.excerpt}</span>
            ))}
          </section>
        )}

        {lead ? (
          <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <CoverStory item={lead} />
            <aside aria-labelledby="most-read">
              <h2 id="most-read" className="mb-4 flex items-center gap-2.5 border-t-4 border-ink pt-3 font-display text-xl font-bold">
                <span aria-hidden className="inline-block h-3 w-3 bg-accent" />{t.home.mostRead}
              </h2>
              <NumberedList items={mostRead.data} />
            </aside>
          </div>
        ) : (
          <EmptyState title={t.home.noneYet} />
        )}
      </div>

      {rest.length > 0 && (
        <Section id="latest" title={t.home.latest} href="/search?sort=latest" seeAll={t.common.seeAll}>
          <InvestigationGrid items={rest} />
        </Section>
      )}

      <section className="masthead -mx-4 bg-masthead px-6 py-10 text-masthead-ink sm:mx-0 sm:px-10" aria-labelledby="cta">
        <p className="file-stamp text-[#ff6b78]">{t.home.publish}</p>
        <h2 id="cta" className="mt-2 max-w-3xl font-display text-3xl font-bold leading-tight sm:text-4xl">{t.home.ctaTitle}</h2>
        <p className="mt-3 max-w-2xl text-masthead-muted">{t.home.heroLead}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <ButtonLink href="/dashboard/onboarding" className="text-white">{t.home.publish}</ButtonLink>
          <ButtonLink href="/search" variant="ghost" className="border border-white/30 text-masthead-ink hover:bg-white/10">{t.home.explore}</ButtonLink>
        </div>
      </section>

      <div className="grid gap-12 lg:grid-cols-3">
        <Section id="trending" title={t.home.trending} href="/search?sort=trending" seeAll={t.common.seeAll}>
          <NumberedList items={trending.data} />
        </Section>
        <Section id="discussed" title={t.home.mostDiscussed} href="/search?sort=most_discussed" seeAll={t.common.seeAll}>
          <NumberedList items={discussed.data} />
        </Section>
        <Section id="topics" title={t.home.topics}>
          <ul className="divide-y divide-line border-b border-line">
            {categories.map((c) => (
              <li key={c.slug}>
                <Link href={`/category/${c.slug}`} className="group flex items-baseline justify-between py-2.5 text-sm">
                  <span className="font-medium group-hover:text-accent">{c.name}</span>
                  <span className="file-stamp text-muted">{f.number(c.investigations_count ?? 0)}</span>
                </Link>
              </li>
            ))}
          </ul>
        </Section>
      </div>

      <Section id="top" title={t.home.topSupported} href="/leaderboards" seeAll={t.home.communityLink}>
        <p className="mb-4 text-xs text-muted">{t.brand.supportNote}</p>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {board?.data.length ? board.data.map((r) => <InvestigatorCard key={r.investigator.id} inv={r.investigator} />) : <EmptyState title={t.home.noSupporters} />}
        </div>
      </Section>
    </div>
  );
}
