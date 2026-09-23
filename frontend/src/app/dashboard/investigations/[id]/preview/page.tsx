"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { InvestigationArticle } from "@/components/investigation/InvestigationArticle";
import { Alert } from "@/components/ui/Alert";
import { Spinner } from "@/components/ui/Card";
import { api, errorMessage } from "@/lib/api";
import type { PublicInvestigation } from "@/lib/types";

export default function PreviewPage() {
  const { id } = useParams<{ id: string }>();
  const [inv, setInv] = useState<PublicInvestigation | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    api<{ data: PublicInvestigation }>(`/me/investigations/${id}/preview`).then((r) => setInv(r.data)).catch((e) => setError(errorMessage(e)));
  }, [id]);
  return (
    <div className="space-y-6">
      <Alert tone="warning" title="Preview — not published">
        This is how readers will see your investigation after you publish. Only evidence marked public (and passing scanning) is shown.{" "}
        <Link href={`/dashboard/investigations/${id}`} className="underline">Back to the editor</Link>
      </Alert>
      {error && <Alert tone="danger">{error}</Alert>}
      {!inv ? !error && <Spinner /> : <div className="mx-auto max-w-3xl"><InvestigationArticle inv={inv} /></div>}
    </div>
  );
}
