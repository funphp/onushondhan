"use client";

import Link from "next/link";
import { useState } from "react";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { StatusBadge } from "@/components/dashboard/StatusBadge";
import { ButtonLink } from "@/components/ui/Button";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { formatDateTime } from "@/lib/format";
import type { OwnerInvestigation } from "@/lib/types";

const FILTERS = ["", "draft", "published", "withdrawn", "archived", "restricted", "removed"];

export default function MyInvestigationsPage() {
  const [status, setStatus] = useState("");
  const { items, meta, error, page, setPage } = useApiList<OwnerInvestigation>(`/me/investigations${status ? `?status=${status}` : ""}`);
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-serif text-3xl font-bold">Your investigations</h1>
        <ButtonLink href="/dashboard/investigations/new">New investigation</ButtonLink>
      </div>
      <label className="text-sm">Status{" "}
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="rounded border border-line bg-surface px-2 py-1">
          {FILTERS.map((f) => <option key={f} value={f}>{f ? f[0].toUpperCase() + f.slice(1) : "All"}</option>)}
        </select>
      </label>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title="Nothing here yet.">Start a draft — it saves automatically and stays private until you publish.</EmptyState> : (
        <div className="overflow-x-auto rounded-lg border border-line bg-surface">
          <table className="w-full text-sm">
            <thead className="text-left text-muted"><tr><th className="p-3">Title</th><th className="p-3">Status</th><th className="p-3">Version</th><th className="p-3">Reads</th><th className="p-3">Updated</th></tr></thead>
            <tbody>
              {items.map((i) => (
                <tr key={i.id} className="border-t border-line">
                  <td className="p-3"><Link href={`/dashboard/investigations/${i.id}`} className="font-medium hover:underline">{i.title}</Link>
                    {i.has_unpublished_changes && <span className="ml-2 text-xs text-warning">unpublished changes</span>}</td>
                  <td className="p-3"><StatusBadge status={i.status} /></td>
                  <td className="p-3">{i.current_version || "—"}</td>
                  <td className="p-3">{i.counts.views}</td>
                  <td className="p-3 whitespace-nowrap">{formatDateTime(i.updated_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {meta && <ListPager page={page} lastPage={meta.last_page} setPage={setPage} />}
    </div>
  );
}
