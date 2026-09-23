"use client";

import Link from "next/link";
import { useApiList } from "@/components/account/useApiList";
import { Badge } from "@/components/ui/Badge";
import { EmptyState, Spinner } from "@/components/ui/Card";
import { formatDate, humanize } from "@/lib/format";

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
  const { items, error } = useApiList<MyComplaint>("/me/complaints");
  return (
    <div className="space-y-4">
      <h1 className="font-serif text-3xl font-bold">Complaints you filed</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!items ? <Spinner /> : items.length === 0 ? <EmptyState title="You have not filed any complaints." /> : (
        <ul className="space-y-3">
          {items.map((c) => (
            <li key={c.id} className="rounded-lg border border-line bg-surface p-4">
              <div className="flex flex-wrap items-center gap-2">
                <strong>{humanize(c.complaint_type)}</strong><Badge>{humanize(c.status)}</Badge>
                <span className="text-sm text-muted">filed {formatDate(c.created_at)}</span>
              </div>
              {c.investigation && <p className="text-sm">About <Link href={`/investigation/${c.investigation.id}`} className="underline">{c.investigation.title}</Link></p>}
              {c.resolution && <p className="mt-2 text-sm"><span className="font-medium">Outcome:</span> {c.resolution}</p>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
