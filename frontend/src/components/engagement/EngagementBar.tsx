"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Button } from "@/components/ui/Button";
import { api, errorMessage } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { SUPPORT_NOTE } from "@/lib/site";
import type { PublicInvestigation } from "@/lib/types";

const REACTIONS = [
  { key: "useful", label: "Useful" },
  { key: "important", label: "Important" },
  { key: "interesting", label: "Interesting" },
];

type Viewer = NonNullable<PublicInvestigation["viewer"]>;

/**
 * Personal engagement controls. Viewer state is fetched client-side so public HTML
 * stays identical (and cacheable) for everyone.
 */
export function EngagementBar({ investigation }: { investigation: Pick<PublicInvestigation, "id" | "investigator" | "counts" | "status"> }) {
  const { user } = useAuth();
  const pathname = usePathname();
  const [viewer, setViewer] = useState<Viewer | null>(null);
  const [counts, setCounts] = useState({ bookmarks: investigation.counts.bookmarks, reactions: investigation.counts.reactions, supporters: investigation.investigator.supporters_count, followers: investigation.investigator.followers_count });
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const slug = investigation.investigator.slug;
  const active = investigation.status === "published" || investigation.status === "archived";

  useEffect(() => {
    if (!user) return;
    api<{ data: PublicInvestigation }>(`/investigations/${investigation.id}`).then((r) => setViewer(r.data.viewer)).catch(() => undefined);
  }, [user, investigation.id]);

  if (!active) return null;

  if (!user) {
    return (
      <div className="rounded-lg border border-line bg-surface p-4 text-sm">
        <p><Link href={`/login?next=${encodeURIComponent(pathname)}`} className="font-semibold text-accent underline">Sign in</Link> to bookmark, react, follow or support this investigator.</p>
        <p className="mt-2 text-xs text-muted">{SUPPORT_NOTE}</p>
      </div>
    );
  }

  const run = async (key: string, fn: () => Promise<{ data: { viewer: Partial<Viewer>; counts: Record<string, number> } }>) => {
    setBusy(key);
    setError(null);
    try {
      const res = await fn();
      setViewer((v) => ({ ...(v ?? { bookmarked: false, reaction: null, following: false, supporting: false, is_owner: false }), ...res.data.viewer }));
      setCounts((c) => ({ ...c, ...res.data.counts }));
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(null);
    }
  };

  const isOwner = viewer?.is_owner ?? user.investigator?.slug === slug;

  return (
    <div className="space-y-4 rounded-lg border border-line bg-surface p-4">
      <div className="flex flex-wrap gap-2">
        <Button size="sm" variant={viewer?.bookmarked ? "primary" : "secondary"} loading={busy === "bookmark"} aria-pressed={!!viewer?.bookmarked}
          onClick={() => run("bookmark", () => api(`/investigations/${investigation.id}/bookmark`, { method: viewer?.bookmarked ? "DELETE" : "PUT" }))}>
          {viewer?.bookmarked ? "Bookmarked" : "Bookmark"} · {formatNumber(counts.bookmarks)}
        </Button>
      </div>
      <fieldset>
        <legend className="mb-2 text-sm font-semibold">Your reaction</legend>
        <div className="flex flex-wrap gap-2">
          {REACTIONS.map((r) => (
            <Button key={r.key} size="sm" variant={viewer?.reaction === r.key ? "primary" : "secondary"} aria-pressed={viewer?.reaction === r.key} loading={busy === r.key}
              onClick={() => run(r.key, () => (viewer?.reaction === r.key ? api(`/investigations/${investigation.id}/reaction`, { method: "DELETE" }) : api(`/investigations/${investigation.id}/reaction`, { method: "PUT", body: { type: r.key } })))}>
              {r.label}
            </Button>
          ))}
        </div>
      </fieldset>
      {!isOwner && (
        <div className="flex flex-wrap gap-2 border-t border-line pt-4">
          <Button size="sm" variant={viewer?.following ? "primary" : "secondary"} aria-pressed={!!viewer?.following} loading={busy === "follow"}
            onClick={() => run("follow", () => api(`/investigators/${slug}/follow`, { method: viewer?.following ? "DELETE" : "PUT" }))}>
            {viewer?.following ? "Following" : "Follow investigator"} · {formatNumber(counts.followers)}
          </Button>
          <Button size="sm" variant={viewer?.supporting ? "primary" : "secondary"} aria-pressed={!!viewer?.supporting} loading={busy === "support"}
            onClick={() => run("support", () => api(`/investigators/${slug}/support`, { method: viewer?.supporting ? "DELETE" : "PUT" }))}>
            {viewer?.supporting ? "Supporting" : "Support investigator"} · {formatNumber(counts.supporters)}
          </Button>
        </div>
      )}
      <p className="text-xs text-muted">{SUPPORT_NOTE}</p>
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
    </div>
  );
}
