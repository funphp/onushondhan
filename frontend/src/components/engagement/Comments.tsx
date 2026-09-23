"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Button } from "@/components/ui/Button";
import { Spinner } from "@/components/ui/Card";
import { SelectInput, TextArea } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { api, ApiError, errorMessage } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import type { Comment } from "@/lib/types";

const REPORT_REASONS = [
  { value: "spam", label: "Spam" },
  { value: "harassment", label: "Harassment" },
  { value: "hate_speech", label: "Hate speech" },
  { value: "personal_information", label: "Shares personal information" },
  { value: "misinformation", label: "Misleading" },
  { value: "off_topic", label: "Off topic" },
  { value: "other", label: "Other" },
];

interface ListResponse {
  data: Comment[];
  meta: { current_page: number; last_page: number; total: number; locked: boolean };
}

export function Comments({ investigationId }: { investigationId: string }) {
  const { user } = useAuth();
  const pathname = usePathname();
  const [sort, setSort] = useState<"top" | "new">("top");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<ListResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reporting, setReporting] = useState<Comment | null>(null);

  useEffect(() => {
    let cancelled = false;
    api<ListResponse>(`/investigations/${investigationId}/comments?sort=${sort}&page=${page}`)
      .then((res) => {
        if (cancelled) return;
        setData((prev) => (page > 1 && prev ? { ...res, data: [...prev.data, ...res.data] } : res));
        setError(null);
      })
      .catch((e) => !cancelled && setError(errorMessage(e)));
    return () => {
      cancelled = true;
    };
  }, [investigationId, sort, page, user?.id]);

  const reload = () => {
    setPage(1);
    void api<ListResponse>(`/investigations/${investigationId}/comments?sort=${sort}&page=1`).then(setData).catch((e) => setError(errorMessage(e)));
  };

  return (
    <section aria-labelledby="comments-h" className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="comments-h" className="font-serif text-2xl font-bold">Discussion {data ? `(${data.meta.total})` : ""}</h2>
        <label className="text-sm">
          <span className="mr-2">Sort</span>
          <select value={sort} onChange={(e) => { setSort(e.target.value as "top" | "new"); setPage(1); }} className="rounded-md border border-line bg-surface px-2 py-1">
            <option value="top">Top</option>
            <option value="new">Newest</option>
          </select>
        </label>
      </div>

      {data?.meta.locked ? (
        <p className="text-sm text-muted">Comments are closed for this investigation.</p>
      ) : user ? (
        <CommentForm investigationId={investigationId} onPosted={reload} />
      ) : (
        <p className="text-sm"><Link href={`/login?next=${encodeURIComponent(pathname)}`} className="font-semibold text-accent underline">Sign in</Link> to join the discussion.</p>
      )}

      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      {!data ? <Spinner label="Loading comments" /> : (
        <ol className="space-y-4">
          {data.data.map((c) => (
            <CommentItem key={c.id} comment={c} investigationId={investigationId} locked={data.meta.locked} depth={0} onChanged={reload} onReport={setReporting} />
          ))}
        </ol>
      )}
      {data && data.meta.current_page < data.meta.last_page && (
        <Button variant="secondary" onClick={() => setPage((p) => p + 1)}>Load more comments</Button>
      )}
      <ReportDialog comment={reporting} onClose={() => setReporting(null)} />
    </section>
  );
}

function CommentForm({ investigationId, parentId, onPosted, onCancel }: { investigationId: string; parentId?: string; onPosted: () => void; onCancel?: () => void }) {
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  return (
    <form
      className="space-y-2"
      onSubmit={async (e) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
          await api(`/investigations/${investigationId}/comments`, { method: "POST", body: { body, parent_id: parentId } });
          setBody("");
          onPosted();
          onCancel?.();
        } catch (err) {
          setError(err instanceof ApiError ? (err.fieldError("body") ?? err.message) : errorMessage(err));
        } finally {
          setBusy(false);
        }
      }}
    >
      <TextArea label={parentId ? "Your reply" : "Add a comment"} value={body} onChange={(e) => setBody(e.target.value)} required minLength={2} maxLength={5000} error={error ?? undefined}
        hint="Be respectful. Do not post personal information about others. Plain text only." />
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={busy}>{parentId ? "Reply" : "Post comment"}</Button>
        {onCancel && <Button type="button" size="sm" variant="ghost" onClick={onCancel}>Cancel</Button>}
      </div>
    </form>
  );
}

function CommentItem({ comment, investigationId, locked, depth, onChanged, onReport }: { comment: Comment; investigationId: string; locked: boolean; depth: number; onChanged: () => void; onReport: (c: Comment) => void }) {
  const { user } = useAuth();
  const [replying, setReplying] = useState(false);
  const [editing, setEditing] = useState(false);
  const [editBody, setEditBody] = useState(comment.body ?? "");
  const [score, setScore] = useState(comment.score);
  const [vote, setVote] = useState(comment.viewer?.vote ?? 0);
  const [error, setError] = useState<string | null>(null);
  const isAuthor = comment.viewer?.is_author ?? false;

  const doVote = async (value: 1 | -1) => {
    setError(null);
    try {
      const res = vote === value
        ? await api<{ data: { score: number; vote: number } }>(`/comments/${comment.id}/vote`, { method: "DELETE" })
        : await api<{ data: { score: number; vote: number } }>(`/comments/${comment.id}/vote`, { method: "PUT", body: { value } });
      setScore(res.data.score);
      setVote(res.data.vote);
    } catch (e) {
      setError(errorMessage(e));
    }
  };

  return (
    <li id={`comment-${comment.id}`} className={depth > 0 ? "border-l-2 border-line pl-4" : ""}>
      <article className="rounded-md bg-surface p-3" aria-label={comment.author ? `Comment by ${comment.author.name}` : "Removed comment"}>
        {comment.body === null ? (
          <p className="text-sm italic text-muted">{comment.placeholder}</p>
        ) : (
          <>
            <header className="flex flex-wrap items-center gap-2 text-xs text-muted">
              {comment.author?.role === "investigator" && comment.author.slug ? (
                <Link href={`/investigators/${comment.author.slug}`} className="font-semibold text-ink">{comment.author.name}</Link>
              ) : (
                <span className="font-semibold text-ink">{comment.author?.name}</span>
              )}
              {comment.author?.role === "investigator" && <span className="rounded bg-accent-soft px-1.5 text-accent">Investigator</span>}
              {comment.is_pinned && <span className="rounded bg-warning-soft px-1.5 text-warning">Pinned</span>}
              <time dateTime={comment.created_at}>{formatDateTime(comment.created_at)}</time>
              {comment.edited_at && <span>(edited)</span>}
            </header>
            {editing ? (
              <form className="mt-2 space-y-2" onSubmit={async (e) => {
                e.preventDefault();
                try {
                  await api(`/comments/${comment.id}`, { method: "PATCH", body: { body: editBody } });
                  setEditing(false);
                  onChanged();
                } catch (err) {
                  setError(errorMessage(err));
                }
              }}>
                <TextArea label="Edit comment" value={editBody} onChange={(e) => setEditBody(e.target.value)} required maxLength={5000} />
                <div className="flex gap-2"><Button size="sm" type="submit">Save</Button><Button size="sm" variant="ghost" type="button" onClick={() => setEditing(false)}>Cancel</Button></div>
              </form>
            ) : (
              <p className="mt-1 whitespace-pre-line text-sm">{comment.body}</p>
            )}
            <div className="mt-2 flex flex-wrap items-center gap-3 text-xs">
              <span className="flex items-center gap-1">
                <button type="button" disabled={!user || isAuthor} aria-pressed={vote === 1} aria-label="Upvote" onClick={() => doVote(1)} className={`rounded px-1.5 py-0.5 hover:bg-accent-soft disabled:opacity-40 ${vote === 1 ? "text-accent" : ""}`}>▲</button>
                <span aria-label={`Score ${score}`}>{score}</span>
                <button type="button" disabled={!user || isAuthor} aria-pressed={vote === -1} aria-label="Downvote" onClick={() => doVote(-1)} className={`rounded px-1.5 py-0.5 hover:bg-accent-soft disabled:opacity-40 ${vote === -1 ? "text-danger" : ""}`}>▼</button>
              </span>
              {user && !locked && depth < 2 && <button type="button" className="hover:underline" onClick={() => setReplying((r) => !r)}>Reply</button>}
              {isAuthor && !editing && <button type="button" className="hover:underline" onClick={() => setEditing(true)}>Edit</button>}
              {isAuthor && (
                <button type="button" className="text-danger hover:underline" onClick={async () => {
                  if (!window.confirm("Delete this comment?")) return;
                  try {
                    await api(`/comments/${comment.id}`, { method: "DELETE" });
                    onChanged();
                  } catch (e) {
                    setError(errorMessage(e));
                  }
                }}>Delete</button>
              )}
              {user && !isAuthor && <button type="button" className="hover:underline" onClick={() => onReport(comment)}>Report</button>}
            </div>
            {error && <p role="alert" className="mt-1 text-xs text-danger">{error}</p>}
          </>
        )}
      </article>
      {replying && <div className="mt-2 pl-4"><CommentForm investigationId={investigationId} parentId={comment.id} onPosted={onChanged} onCancel={() => setReplying(false)} /></div>}
      {comment.replies.length > 0 && (
        <ol className="mt-3 space-y-3">
          {comment.replies.map((r) => <CommentItem key={r.id} comment={r} investigationId={investigationId} locked={locked} depth={depth + 1} onChanged={onChanged} onReport={onReport} />)}
        </ol>
      )}
    </li>
  );
}

function ReportDialog({ comment, onClose }: { comment: Comment | null; onClose: () => void }) {
  const [reason, setReason] = useState("spam");
  const [details, setDetails] = useState("");
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <Modal open={!!comment} onClose={() => { setStatus(null); onClose(); }} title="Report comment"
      footer={status ? <Button onClick={() => { setStatus(null); onClose(); }}>Close</Button> : (
        <Button loading={busy} onClick={async () => {
          if (!comment) return;
          setBusy(true);
          try {
            await api(`/comments/${comment.id}/report`, { method: "POST", body: { reason, details: details || undefined } });
            setStatus("Thank you. Our moderators will review this comment.");
          } catch (e) {
            setStatus(errorMessage(e));
          } finally {
            setBusy(false);
          }
        }}>Submit report</Button>
      )}>
      {status ? <p role="status">{status}</p> : (
        <div className="space-y-3">
          <SelectInput label="Reason" value={reason} onChange={(e) => setReason(e.target.value)} options={REPORT_REASONS} />
          <TextArea label="Details (optional)" value={details} onChange={(e) => setDetails(e.target.value)} maxLength={2000} />
        </div>
      )}
    </Modal>
  );
}
