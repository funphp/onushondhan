"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { InvestigationArticle } from "@/components/investigation/InvestigationArticle";
import { Alert } from "@/components/ui/Alert";
import { Spinner } from "@/components/ui/Card";
import { useI18n } from "@/i18n/client";
import { api, errorMessage } from "@/lib/api";
import type { PublicInvestigation } from "@/lib/types";

export default function PreviewPage() {
  const { t } = useI18n();
  const { id } = useParams<{ id: string }>();
  const [inv, setInv] = useState<PublicInvestigation | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    api<{ data: PublicInvestigation }>(`/me/investigations/${id}/preview`).then((r) => setInv(r.data)).catch((e) => setError(errorMessage(e)));
  }, [id]);
  return (
    <div className="space-y-6">
      <Alert tone="warning" title={t.investigation.preview}>
        {t.editor.previewBanner}{" "}
        <Link href={`/dashboard/investigations/${id}`} className="underline">{t.editor.backToEditor}</Link>
      </Alert>
      {error && <Alert tone="danger">{error}</Alert>}
      {!inv ? !error && <Spinner /> : <div className="mx-auto max-w-3xl"><InvestigationArticle inv={inv} /></div>}
    </div>
  );
}
