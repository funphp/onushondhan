import type { Metadata } from "next";
import Link from "next/link";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { EmptyState } from "@/components/ui/Card";
import { Pagination } from "@/components/ui/Pagination";
import { getI18n } from "@/i18n/server";
import { serverGet } from "@/lib/server-api";
import type { InvestigatorSummary, Paginated } from "@/lib/types";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.investigator.directoryTitle, description: t.investigator.directoryMeta, alternates: { canonical: "/investigators" } };
}

const SORTS = ["published", "followers", "supporters", "views", "recent"] as const;

export default async function InvestigatorsPage(props: PageProps<"/investigators">) {
  const { t } = await getI18n();
  const sp = await props.searchParams;
  const sort = typeof sp.sort === "string" && (SORTS as readonly string[]).includes(sp.sort) ? sp.sort : "published";
  const q = typeof sp.q === "string" ? sp.q : "";
  const page = Number(sp.page ?? 1) || 1;
  const res = await serverGet<Paginated<InvestigatorSummary>>("/investigators", { params: { sort, q, page, per_page: 24 }, revalidate: 120 })
    .catch(() => ({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } }) as Paginated<InvestigatorSummary>);

  return (
    <div className="space-y-6">
      <h1 className="font-serif text-3xl font-bold">{t.investigator.directoryTitle}</h1>
      <form className="flex flex-wrap items-end gap-3" action="/investigators">
        <div>
          <label htmlFor="iq" className="mb-1 block text-xs font-medium">{t.investigator.name}</label>
          <input id="iq" name="q" defaultValue={q} className="rounded-md border border-line bg-surface px-3 py-2 text-sm" />
        </div>
        <div>
          <label htmlFor="is" className="mb-1 block text-xs font-medium">{t.investigator.sort}</label>
          <select id="is" name="sort" defaultValue={sort} className="rounded-md border border-line bg-surface px-3 py-2 text-sm">
            {SORTS.map((s) => <option key={s} value={s}>{t.investigator.sorts[s]}</option>)}
          </select>
        </div>
        <button className="h-10 rounded-md bg-accent px-4 text-sm text-white dark:text-paper">{t.common.apply}</button>
      </form>
      <p className="text-xs text-muted">{t.investigator.engagementNote} <Link href="/leaderboards" className="underline">{t.investigator.rankings}</Link></p>
      {res.data.length === 0 ? <EmptyState title={t.investigator.noneFound} /> : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{res.data.map((i) => <InvestigatorCard key={i.id} inv={i} />)}</div>
      )}
      <Pagination page={res.meta.current_page} lastPage={res.meta.last_page} hrefs={Array.from({ length: res.meta.last_page }, (_, i) => `/investigators?sort=${sort}&q=${encodeURIComponent(q)}&page=${i + 1}`)} />
    </div>
  );
}
