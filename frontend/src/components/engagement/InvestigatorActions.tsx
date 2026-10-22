"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Button, buttonClass } from "@/components/ui/Button";
import { api, errorMessage } from "@/lib/api";
import { useI18n } from "@/i18n/client";
import type { InvestigatorProfile } from "@/lib/types";

export function InvestigatorActions({ profile }: { profile: InvestigatorProfile }) {
  const { user } = useAuth();
  const { t, f } = useI18n();
  const [viewer, setViewer] = useState<InvestigatorProfile["viewer"]>(null);
  const [counts, setCounts] = useState({ followers: profile.followers_count, supporters: profile.supporters_count });
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) return;
    api<{ data: InvestigatorProfile }>(`/investigators/${profile.slug}`).then((r) => setViewer(r.data.viewer)).catch(() => undefined);
  }, [user, profile.slug]);

  const toggle = async (kind: "follow" | "support", on: boolean) => {
    setBusy(kind);
    setError(null);
    try {
      const r = await api<{ data: { viewer: Record<string, boolean>; counts: { followers: number; supporters: number } } }>(`/investigators/${profile.slug}/${kind}`, { method: on ? "DELETE" : "PUT" });
      setViewer((v) => ({ ...(v ?? { following: false, supporting: false, is_self: false }), ...r.data.viewer }));
      setCounts(r.data.counts);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap gap-2">
        {user && !viewer?.is_self && (
          <>
            <Button size="sm" variant={viewer?.following ? "primary" : "secondary"} aria-pressed={!!viewer?.following} loading={busy === "follow"} onClick={() => toggle("follow", !!viewer?.following)}>
              {viewer?.following ? t.engagement.following : t.engagement.followShort} · {f.number(counts.followers)}
            </Button>
            <Button size="sm" variant={viewer?.supporting ? "primary" : "secondary"} aria-pressed={!!viewer?.supporting} loading={busy === "support"} onClick={() => toggle("support", !!viewer?.supporting)}>
              {viewer?.supporting ? t.engagement.supporting : t.engagement.supportShort} · {f.number(counts.supporters)}
            </Button>
          </>
        )}
        {!user && <Link href={`/login?next=/investigators/${profile.slug}`} className={buttonClass("secondary", "sm")}>{t.engagement.signInToFollow}</Link>}
        {!viewer?.is_self && <Link href={`/tips/new?investigator=${profile.slug}`} className={buttonClass("primary", "sm")}>{t.investigation.sendTip}</Link>}
      </div>
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
    </div>
  );
}
