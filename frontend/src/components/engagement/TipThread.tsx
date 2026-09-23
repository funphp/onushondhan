"use client";

import { useState } from "react";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { TextArea } from "@/components/ui/Field";
import { errorMessage } from "@/lib/api";
import { formatBytes, formatDateTime, humanize } from "@/lib/format";
import type { Tip } from "@/lib/types";

/** Conversation view shared by the investigator inbox and the tipster side. */
export function TipThread({ tip, perspective, onReply }: { tip: Tip; perspective: "investigator" | "tipster"; onReply?: (body: string) => Promise<void> }) {
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const closed = tip.status === "closed";
  const mine = perspective === "investigator" ? "investigator" : "tipster";

  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <h2 className="text-xl font-semibold">{tip.subject}</h2>
        <p className="text-sm text-muted">
          {perspective === "investigator" ? (
            tip.sender?.anonymous ? <>From an anonymous {tip.sender.type === "guest" ? "guest (no account)" : "member"}</> : <>From <strong>{tip.sender?.username}</strong></>
          ) : (
            <>To {tip.investigator.public_name}</>
          )}
          {tip.investigation && <> · about “{tip.investigation.title}”</>} · {formatDateTime(tip.created_at)}
        </p>
        <Badge tone={closed ? "neutral" : "accent"}>{humanize(tip.status)}</Badge>
        {perspective === "investigator" && tip.contact_value && (
          <p className="text-sm">Contact shared by tipster ({tip.contact_preference}): <strong>{tip.contact_value}</strong></p>
        )}
      </header>

      <div className="whitespace-pre-line rounded-lg border border-line bg-surface p-4">{tip.message}</div>

      {tip.attachments && tip.attachments.length > 0 && (
        <ul className="space-y-1 text-sm">
          {tip.attachments.map((a) => (
            <li key={a.id}>
              {a.download_url && a.scan_status !== "infected" ? <a href={a.download_url} className="text-accent underline">{a.name}</a> : <span>{a.name}</span>}
              <span className="text-muted"> · {formatBytes(a.size_bytes)} · scan: {humanize(a.scan_status)}</span>
            </li>
          ))}
        </ul>
      )}

      <ol className="space-y-3" aria-label="Messages">
        {(tip.messages ?? []).map((m, i) => (
          <li key={i} className={`max-w-[85%] rounded-lg p-3 text-sm ${m.from === mine ? "ml-auto bg-accent-soft" : "border border-line bg-surface"}`}>
            <p className="mb-1 text-xs text-muted">{m.from === "investigator" ? "Investigator" : "Tipster"} · {formatDateTime(m.created_at)}</p>
            <p className="whitespace-pre-line">{m.body}</p>
          </li>
        ))}
      </ol>

      {onReply && !closed && (
        <form className="space-y-2" onSubmit={async (e) => {
          e.preventDefault();
          setBusy(true);
          setError(null);
          try {
            await onReply(body);
            setBody("");
          } catch (err) {
            setError(errorMessage(err));
          } finally {
            setBusy(false);
          }
        }}>
          <TextArea label="Reply" value={body} onChange={(e) => setBody(e.target.value)} required maxLength={10000} error={error ?? undefined} />
          <Button type="submit" size="sm" loading={busy}>Send reply</Button>
        </form>
      )}
      {closed && <p className="text-sm text-muted">This conversation has been closed.</p>}
    </div>
  );
}
