"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { TipThread } from "@/components/engagement/TipThread";
import { Spinner } from "@/components/ui/Card";
import { api, errorMessage } from "@/lib/api";
import type { Tip } from "@/lib/types";

export default function MyTipPage() {
  const { id } = useParams<{ id: string }>();
  const [tip, setTip] = useState<Tip | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    api<{ data: Tip }>(`/me/tips/${id}`).then((r) => setTip(r.data)).catch((e) => setError(errorMessage(e)));
  }, [id]);
  return (
    <div className="space-y-4">
      <Link href="/account/tips" className="text-sm underline">← All tips</Link>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!tip ? !error && <Spinner /> : (
        <TipThread tip={tip} perspective="tipster" onReply={async (body) => setTip((await api<{ data: Tip }>(`/me/tips/${id}/messages`, { method: "POST", body: { body } })).data)} />
      )}
    </div>
  );
}
