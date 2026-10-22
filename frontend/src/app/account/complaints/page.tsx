"use client";

import Link from "next/link";
import { useApiList } from "@/components/account/useApiList";
import { Badge } from "@/components/ui/Badge";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";

interface MyComplaint {
  id: string;
  complaint_type: string;
  status: string;
  resolution: string | null;
  investigation: { id: string; title: string } | null;
  created_at: string;
  reviewed_at: string | null;
}

export default function MyComplaintsPage() {
  const { t, f } = useI18n();
  const { items, error } = useApiList<MyComplaint>("/me/complaints");
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">{t.account.complaintsTitle}</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title={t.account.noComplaints} /> : (
        <ul className="space-y-3">
          {items.map((c) => (
            <li key={c.id} className="rounded-lg border border-line bg-surface p-4">
              <div className="flex flex-wrap items-center gap-2">
                <strong>{labelFor(t.complaintTypeShort, c.complaint_type)}</strong><Badge>{labelFor(t.statuses, c.status)}</Badge>
                <span className="text-sm text-muted">{t.account.filed(f.date(c.created_at))}</span>
              </div>
              {c.investigation && <p className="text-sm">{t.account.aboutInvestigation} <Link href={`/investigation/${c.investigation.id}`} className="underline">{c.investigation.title}</Link></p>}
              {c.resolution && <p className="mt-2 text-sm"><span className="font-medium">{t.account.outcome}</span> {c.resolution}</p>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
