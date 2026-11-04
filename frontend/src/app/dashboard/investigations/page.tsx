"use client";

import Link from "next/link";
import { useState } from "react";
import { ListPager } from "@/components/account/ListPager";
import { useApiList } from "@/components/account/useApiList";
import { StatusBadge } from "@/components/dashboard/StatusBadge";
import { ButtonLink } from "@/components/ui/Button";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";
import type { OwnerInvestigation } from "@/lib/types";

const FILTERS = ["", "draft", "published", "withdrawn", "archived", "restricted", "removed"];

export default function MyInvestigationsPage() {
  const { t, f } = useI18n();
  const [status, setStatus] = useState("");
  const { items, meta, error, page, setPage } = useApiList<OwnerInvestigation>(`/me/investigations${status ? `?status=${status}` : ""}`);
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-3xl font-bold">{t.myInvestigations.title}</h1>
        <ButtonLink href="/dashboard/investigations/new">{t.dashboard.newInvestigation}</ButtonLink>
      </div>
      <label className="text-sm">{t.myInvestigations.status}{" "}
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="rounded border border-line bg-surface px-2 py-1">
          {FILTERS.map((s) => <option key={s} value={s}>{s ? labelFor(t.statuses, s) : t.common.all}</option>)}
        </select>
      </label>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title={t.myInvestigations.nothing}>{t.myInvestigations.nothingHint}</EmptyState> : (
        <div className="overflow-x-auto rounded-lg border border-line bg-surface">
          <table className="w-full text-sm">
            <thead className="text-left text-muted"><tr><th className="p-3">{t.myInvestigations.colTitle}</th><th className="p-3">{t.myInvestigations.colStatus}</th><th className="p-3">{t.myInvestigations.colVersion}</th><th className="p-3">{t.myInvestigations.colReads}</th><th className="p-3">{t.myInvestigations.colUpdated}</th></tr></thead>
            <tbody>
              {items.map((i) => (
                <tr key={i.id} className="border-t border-line">
                  <td className="p-3"><Link href={`/dashboard/investigations/${i.id}`} className="font-medium hover:underline">{i.title}</Link>
                    {i.has_unpublished_changes && <span className="ml-2 text-xs text-warning">{t.myInvestigations.unpublishedChanges}</span>}</td>
                  <td className="p-3"><StatusBadge status={i.status} /></td>
                  <td className="p-3">{i.current_version ? f.digits(i.current_version) : "—"}</td>
                  <td className="p-3">{f.number(i.counts.views)}</td>
                  <td className="p-3 whitespace-nowrap">{f.dateTime(i.updated_at)}</td>
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
