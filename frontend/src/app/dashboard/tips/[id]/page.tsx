"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { TipThread } from "@/components/engagement/TipThread";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Card";
import { api, errorMessage } from "@/lib/api";
import type { Tip } from "@/lib/types";

export default function TipDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [tip, setTip] = useState<Tip | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<{ data: Tip }>(`/me/inbox/tips/${id}`).then((r) => setTip(r.data)).catch((e) => setError(errorMessage(e)));
  }, [id]);

  const setStatus = async (status: string) => {
    try {
      setTip((await api<{ data: Tip }>(`/me/inbox/tips/${id}`, { method: "PATCH", body: { status } })).data);
    } catch (e) {
      setError(errorMessage(e));
    }
  };

  return (
    <div className="space-y-4">
      <Link href="/dashboard/tips" className="text-sm underline">← Tip inbox</Link>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!tip ? !error && <Spinner /> : (
        <>
          <div className="flex flex-wrap gap-2">
            {tip.status !== "important" && <Button size="sm" variant="secondary" onClick={() => setStatus("important")}>Mark important</Button>}
            {tip.status !== "archived" && <Button size="sm" variant="secondary" onClick={() => setStatus("archived")}>Archive</Button>}
            {tip.status !== "closed" ? <Button size="sm" variant="secondary" onClick={() => setStatus("closed")}>Close conversation</Button>
              : <Button size="sm" variant="secondary" onClick={() => setStatus("read")}>Re-open</Button>}
          </div>
          <TipThread tip={tip} perspective="investigator" onReply={async (body) => setTip((await api<{ data: Tip }>(`/me/inbox/tips/${id}/messages`, { method: "POST", body: { body } })).data)} />
        </>
      )}
    </div>
  );
}
