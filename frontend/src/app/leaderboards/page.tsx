import type { Metadata } from "next";
import Link from "next/link";
import { InvestigatorCard } from "@/components/investigation/InvestigatorCard";
import { Alert } from "@/components/ui/Alert";
import { EmptyState } from "@/components/ui/Card";
import { getI18n } from "@/i18n/server";
import { serverGetOrNull } from "@/lib/server-api";
import type { LeaderboardResponse } from "@/lib/types";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.rankings, alternates: { canonical: "/leaderboards" } };
}
export const revalidate = 300;

const BOARDS = ["top_supported", "rising", "most_published", "most_read", "most_shared", "most_discussed"];

export default async function LeaderboardsPage(props: PageProps<"/leaderboards">) {
  const { t, f } = await getI18n();
  const sp = await props.searchParams;
  const board = typeof sp.board === "string" && BOARDS.includes(sp.board) ? sp.board : "top_supported";
  const res = await serverGetOrNull<LeaderboardResponse>(`/leaderboards/${board === "top_supported" ? "support" : board}`, { params: { limit: 25 }, revalidate: 300 });

  return (
    <div className="space-y-6">
      <h1 className="font-serif text-3xl font-bold">{t.leaderboards.title}</h1>
      <Alert>{res?.note ?? t.leaderboards.fallbackNote}</Alert>
      <nav aria-label={t.leaderboards.type} className="flex flex-wrap gap-2">
        {(res?.boards ?? []).map((b) => (
          <Link key={b.key} href={`/leaderboards?board=${b.key}`} aria-current={b.key === board ? "page" : undefined}
            className={`rounded-full px-3 py-1 text-sm ${b.key === board ? "bg-accent text-white dark:text-paper" : "border border-line hover:bg-accent-soft"}`}>{b.label}</Link>
        ))}
      </nav>
      <h2 className="text-xl font-semibold">{res?.label}</h2>
      {!res || res.data.length === 0 ? <EmptyState title={t.leaderboards.none} /> : (
        <ol className="space-y-2">
          {res.data.map((r) => (
            <li key={r.investigator.id} className="flex items-center gap-3">
              <span className="w-8 text-right font-semibold text-muted">{f.digits(r.rank)}</span>
              <div className="flex-1"><InvestigatorCard inv={r.investigator} extra={<span className="text-sm font-semibold">{f.number(r.score)}</span>} /></div>
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}
