"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BarChart } from "@/components/dashboard/BarChart";
import { ButtonLink } from "@/components/ui/Button";
import { Card, Spinner } from "@/components/ui/Card";
import { api, errorMessage } from "@/lib/api";
import { formatNumber } from "@/lib/format";

interface Summary {
  range_days: number;
  totals: Record<string, number>;
  series: Record<string, { date: string; count: number }[]>;
  top_investigations: { id: string; title: string; views: number; shares: number; comments: number }[];
  note: string;
}

const SERIES: [string, string][] = [
  ["investigation_view", "Reads"], ["investigation_share", "Shares"], ["comment_created", "Comments"], ["tip_submitted", "Tips"],
  ["support_created", "New supporters"], ["investigator_followed", "New followers"],
];

export default function DashboardHome() {
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
        <h1 className="font-serif text-3xl font-bold">Overview</h1>
        <div className="flex gap-2">
          <ButtonLink href="/dashboard/investigations/new">New investigation</ButtonLink>
          <ButtonLink href="/dashboard/tips" variant="secondary">Tips{unread ? ` (${unread} new)` : ""}</ButtonLink>
        </div>
      </div>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!data ? <Spinner /> : (
        <>
          <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {[["views", "Reads"], ["comments", "Comments"], ["shares", "Shares"], ["tips", "Tips"], ["bookmarks", "Bookmarks"], ["followers", "Followers"], ["supporters", "Supporters"], ["published_investigations", "Published"]].map(([k, l]) => (
              <Card key={k} className="p-4"><dt className="text-xs text-muted">{l}</dt><dd className="text-2xl font-semibold">{formatNumber(data.totals[k])}</dd></Card>
            ))}
          </dl>
          <p className="text-xs text-muted">{data.note}</p>
          <Card as="section">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-lg font-semibold">Activity</h2>
              <label className="text-sm">Range{" "}
                <select value={days} onChange={(e) => setDays(Number(e.target.value))} className="rounded border border-line bg-surface px-2 py-1">
                  <option value={7}>7 days</option><option value={30}>30 days</option><option value={90}>90 days</option>
                </select>
              </label>
            </div>
            <div className="grid gap-6 md:grid-cols-2">
              {SERIES.map(([k, l]) => <BarChart key={k} label={l} data={data.series[k] ?? []} />)}
            </div>
          </Card>
          {data.top_investigations.length > 0 && (
            <Card as="section">
              <h2 className="mb-3 text-lg font-semibold">Most read</h2>
              <table className="w-full text-sm">
                <thead><tr className="text-left text-muted"><th className="py-1">Investigation</th><th>Reads</th><th>Shares</th><th>Comments</th></tr></thead>
                <tbody>
                  {data.top_investigations.map((i) => (
                    <tr key={i.id} className="border-t border-line"><td className="py-2"><Link href={`/dashboard/investigations/${i.id}`} className="hover:underline">{i.title}</Link></td><td>{i.views}</td><td>{i.shares}</td><td>{i.comments}</td></tr>
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
