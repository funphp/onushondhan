"use client";

import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { Card, EmptyState, Spinner } from "@/components/ui/Card";
import { formatDate } from "@/lib/format";
import { SUPPORT_NOTE } from "@/lib/site";

type Row = { username: string; since: string };

function AudienceList({ title, path }: { title: string; path: string }) {
  const { items, meta, page, setPage, error } = useApiList<Row>(path);
  return (
    <Card as="section">
      <h2 className="mb-3 text-lg font-semibold">{title} {meta && <span className="text-muted">({meta.total})</span>}</h2>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title="None yet." /> : (
        <ul className="divide-y divide-line text-sm">{items.map((r, i) => <li key={i} className="flex justify-between py-2"><span>{r.username}</span><span className="text-muted">since {formatDate(r.since)}</span></li>)}</ul>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </Card>
  );
}

export default function AudiencePage() {
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">Followers &amp; supporters</h1>
      <p className="text-sm text-muted">{SUPPORT_NOTE} Only public usernames are shown.</p>
      <div className="grid gap-4 lg:grid-cols-2">
        <AudienceList title="Followers" path="/me/investigator/followers" />
        <AudienceList title="Supporters" path="/me/investigator/supporters" />
      </div>
    </div>
  );
}
