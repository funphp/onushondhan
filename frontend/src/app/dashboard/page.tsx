"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BarChart } from "@/components/dashboard/BarChart";
import { ButtonLink } from "@/components/ui/Button";
import { Card, Spinner } from "@/components/ui/Card";
import { api, errorMessage } from "@/lib/api";
import { useI18n } from "@/i18n/client";

interface Summary {
  range_days: number;
  totals: Record<string, number>;
  series: Record<string, { date: string; count: number }[]>;
  top_investigations: { id: string; title: string; views: number; shares: number; comments: number }[];
  note: string;
}

const SERIES = ["investigation_view", "investigation_share", "comment_created", "tip_submitted", "support_created", "investigator_followed"] as const;
const TOTALS = ["views", "comments", "shares", "tips", "bookmarks", "followers", "supporters", "published_investigations"] as const;

export default function DashboardHome() {
  const { t, f } = useI18n();
  const [days, setDays] = useState(30);
  const [data, setData] = useState<Summary | null>(null);
  const [unread, setUnread] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<{ data: Summary }>(`/me/investigator/analytics?days=${days}`).then((r) => setData(r.data)).catch((e) => setError(errorMessage(e)));
  }, [days]);
  useEffect(() => {
    api<{ meta: { unread: number } }>("/me/inbox/tips?per_page=1").then((r) => setUnread(r.meta.unread)).catch(() => undefined);
  }, []);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-serif text-3xl font-bold">{t.dashboard.overview}</h1>
        <div className="flex gap-2">
          <ButtonLink href="/dashboard/investigations/new">{t.dashboard.newInvestigation}</ButtonLink>
          <ButtonLink href="/dashboard/tips" variant="secondary">{t.dashboard.tipsButton(unread ? f.number(unread) : "")}</ButtonLink>
        </div>
      </div>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!data ? <Spinner /> : (
        <>
          <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {TOTALS.map((k) => (
              <Card key={k} className="p-4"><dt className="text-xs text-muted">{t.dashboard.totals[k]}</dt><dd className="text-2xl font-semibold">{f.number(data.totals[k])}</dd></Card>
            ))}
          </dl>
          <p className="text-xs text-muted">{data.note}</p>
          <Card as="section">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold">{t.dashboard.activity}</h2>
              <label className="text-sm">{t.dashboard.range}{" "}
                <select value={days} onChange={(e) => setDays(Number(e.target.value))} className="rounded border border-line bg-surface px-2 py-1">
                  {[7, 30, 90].map((n) => <option key={n} value={n}>{t.dashboard.days(f.digits(n))}</option>)}
                </select>
              </label>
            </div>
            <div className="grid gap-6 md:grid-cols-2">
              {SERIES.map((k) => <BarChart key={k} label={t.dashboard.series[k]} data={data.series[k] ?? []} />)}
            </div>
          </Card>
          {data.top_investigations.length > 0 && (
            <Card as="section">
              <h2 className="mb-3 text-lg font-semibold">{t.dashboard.mostRead}</h2>
              <table className="w-full text-sm">
                <thead><tr className="text-left text-muted"><th className="py-1">{t.dashboard.colInvestigation}</th><th>{t.dashboard.colReads}</th><th>{t.dashboard.colShares}</th><th>{t.dashboard.colComments}</th></tr></thead>
                <tbody>
                  {data.top_investigations.map((i) => (
                    <tr key={i.id} className="border-t border-line"><td className="py-2"><Link href={`/dashboard/investigations/${i.id}`} className="hover:underline">{i.title}</Link></td><td>{f.number(i.views)}</td><td>{f.number(i.shares)}</td><td>{f.number(i.comments)}</td></tr>
                  ))}
                </tbody>
              </table>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
