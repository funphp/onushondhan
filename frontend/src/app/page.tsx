import Link from "next/link";
import { InvestigationCard, InvestigationGrid } from "@/components/investigation/InvestigationCard";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { JsonLd } from "@/components/site/JsonLd";
import { ButtonLink } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/Card";
import { formatDate } from "@/lib/format";
import { getCategories, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";
import { PLATFORM_DISCLAIMER, SITE_NAME, SITE_URL, SUPPORT_NOTE } from "@/lib/site";
import type { CmsPage, LeaderboardResponse } from "@/lib/types";

export const revalidate = 60;

function Rail({ title, href, children }: { title: string; href: string; children: React.ReactNode }) {
  return (
    <section className="space-y-4" aria-labelledby={`rail-${href}`}>
      <div className="flex items-baseline justify-between">
        <h2 id={`rail-${href}`} className="font-serif text-2xl font-bold">{title}</h2>
        <Link href={href} className="text-sm text-accent hover:underline">See all →</Link>
      </div>
      {children}
    </section>
  );
}

export default async function Home() {
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
      <JsonLd data={{ "@context": "https://schema.org", "@type": "WebSite", name: SITE_NAME, url: SITE_URL, potentialAction: { "@type": "SearchAction", target: `${SITE_URL}/search?q={query}`, "query-input": "required name=query" } }} />
      <JsonLd data={{ "@context": "https://schema.org", "@type": "Organization", name: SITE_NAME, url: SITE_URL, description: PLATFORM_DISCLAIMER }} />

      <section className="rounded-xl border border-line bg-surface px-6 py-10 sm:px-10">
        <h1 className="max-w-3xl font-serif text-4xl font-bold leading-tight sm:text-5xl">Independent investigations, published in the open.</h1>
        <p className="mt-4 max-w-2xl text-lg text-muted">Read evidence-backed reports from independent investigators, discuss them, follow their work and send them tips securely.</p>
        <p className="mt-3 max-w-2xl text-sm text-muted">{PLATFORM_DISCLAIMER}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <ButtonLink href="/search">Explore investigations</ButtonLink>
          <ButtonLink href="/dashboard/onboarding" variant="secondary">Publish your investigation</ButtonLink>
        </div>
      </section>

      {announcements && announcements.data.length > 0 && (
        <section aria-label="Announcements" className="rounded-lg border border-line bg-accent-soft p-4 text-sm">
          {announcements.data.slice(0, 2).map((a) => (
            <p key={a.slug}><strong>{a.title}</strong> — {a.excerpt} <span className="text-muted">({formatDate(a.published_at)})</span></p>
          ))}
        </section>
      )}

      <Rail title="Latest investigations" href="/search?sort=latest">
        {latest.data.length ? <InvestigationGrid items={latest.data} /> : <EmptyState title="No investigations have been published yet." />}
      </Rail>

      <div className="grid gap-10 lg:grid-cols-3">
        <Rail title="Trending" href="/search?sort=trending"><div className="space-y-4">{trending.data.map((i) => <InvestigationCard key={i.id} item={i} compact />)}</div></Rail>
        <Rail title="Most read" href="/search?sort=most_read"><div className="space-y-4">{mostRead.data.map((i) => <InvestigationCard key={i.id} item={i} compact />)}</div></Rail>
        <Rail title="Most discussed" href="/search?sort=most_discussed"><div className="space-y-4">{discussed.data.map((i) => <InvestigationCard key={i.id} item={i} compact />)}</div></Rail>
      </div>

      <div className="grid gap-10 lg:grid-cols-2">
        <section aria-labelledby="cats">
          <h2 id="cats" className="mb-4 font-serif text-2xl font-bold">Browse by category</h2>
          <ul className="grid grid-cols-2 gap-2">
            {categories.map((c) => (
              <li key={c.slug}>
                <Link href={`/category/${c.slug}`} className="flex justify-between rounded-md border border-line bg-surface px-3 py-2 text-sm hover:bg-accent-soft">
                  <span>{c.name}</span><span className="text-muted">{c.investigations_count ?? 0}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
        <section aria-labelledby="top">
          <div className="mb-4 flex items-baseline justify-between">
            <h2 id="top" className="font-serif text-2xl font-bold">Top supported investigators</h2>
            <Link href="/leaderboards" className="text-sm text-accent hover:underline">Community →</Link>
          </div>
          <p className="mb-3 text-xs text-muted">{SUPPORT_NOTE}</p>
          <div className="space-y-2">
            {board?.data.length ? board.data.map((r) => <InvestigatorCard key={r.investigator.id} inv={r.investigator} />) : <EmptyState title="No supporters yet." />}
          </div>
        </section>
      </div>
    </div>
  );
}
