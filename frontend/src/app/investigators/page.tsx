import type { Metadata } from "next";
import Link from "next/link";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { EmptyState } from "@/components/ui/Card";
import { Pagination } from "@/components/ui/Pagination";
import { serverGet } from "@/lib/server-api";
import type { InvestigatorSummary, Paginated } from "@/lib/types";

export const metadata: Metadata = { title: "Investigators", description: "Independent investigators publishing on Onushondhan.", alternates: { canonical: "/investigators" } };

const SORTS = [
  { v: "published", l: "Most published" },
  { v: "followers", l: "Most followed" },
  { v: "supporters", l: "Most supported" },
  { v: "views", l: "Most read" },
  { v: "recent", l: "Newest" },
];

export default async function InvestigatorsPage(props: PageProps<"/investigators">) {
  const sp = await props.searchParams;
  const sort = typeof sp.sort === "string" && SORTS.some((s) => s.v === sp.sort) ? sp.sort : "published";
  const q = typeof sp.q === "string" ? sp.q : "";
  const page = Number(sp.page ?? 1) || 1;
  const res = await serverGet<Paginated<InvestigatorSummary>>("/investigators", { params: { sort, q, page, per_page: 24 }, revalidate: 120 })
    .catch(() => ({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } }) as Paginated<InvestigatorSummary>);

  return (
    <div className="space-y-6">
      <h1 className="font-serif text-3xl font-bold">Investigators</h1>
      <form className="flex flex-wrap items-end gap-3" action="/investigators">
        <div>
          <label htmlFor="iq" className="mb-1 block text-xs font-medium">Name</label>
          <input id="iq" name="q" defaultValue={q} className="rounded-md border border-line bg-surface px-3 py-2 text-sm" />
        </div>
        <div>
          <label htmlFor="is" className="mb-1 block text-xs font-medium">Sort</label>
          <select id="is" name="sort" defaultValue={sort} className="rounded-md border border-line bg-surface px-3 py-2 text-sm">
            {SORTS.map((s) => <option key={s.v} value={s.v}>{s.l}</option>)}
          </select>
        </div>
        <button className="h-10 rounded-md bg-accent px-4 text-sm text-white dark:text-paper">Apply</button>
      </form>
      <p className="text-xs text-muted">Follower and supporter counts are engagement signals, not measures of accuracy. <Link href="/leaderboards" className="underline">Community rankings</Link></p>
      {res.data.length === 0 ? <EmptyState title="No investigators found." /> : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{res.data.map((i) => <InvestigatorCard key={i.id} inv={i} />)}</div>
      )}
      <Pagination page={res.meta.current_page} lastPage={res.meta.last_page} hrefFor={(p) => `/investigators?sort=${sort}&q=${encodeURIComponent(q)}&page=${p}`} />
    </div>
  );
}
