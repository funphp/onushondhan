"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { EvidenceManager } from "@/components/dashboard/EvidenceManager";
import { ListEditor } from "@/components/dashboard/ListEditor";
import { PublishDialog } from "@/components/dashboard/PublishDialog";
import { StatusBadge } from "@/components/dashboard/StatusBadge";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, Spinner } from "@/components/ui/Card";
import { Checkbox, SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Tabs } from "@/components/ui/Tabs";
import { api, ApiError, errorMessage } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import type { Category, Entity, Finding, OwnerInvestigation, SourceItem, TimelineItem } from "@/lib/types";

type Loc = { country: string | null; region: string | null; district: string | null; city: string | null; area: string | null };

interface Draft {
  title: string; summary: string; body: string; category: string; subcategory: string; tags: string; visibility: string;
  location_name: string; location_country: string; period_start: string; period_end: string;
  recommendations: string[]; entities: Entity[]; findings: Finding[]; timeline: TimelineItem[]; sources: SourceItem[]; locations: Loc[];
}

const toDraft = (i: OwnerInvestigation): Draft => ({
  title: i.title ?? "", summary: i.summary ?? "", body: i.body ?? "", category: i.category ?? "", subcategory: i.subcategory ?? "",
  tags: (i.tags ?? []).join(", "), visibility: i.visibility, location_name: i.location_name ?? "", location_country: i.location_country ?? "",
  period_start: i.period_start ?? "", period_end: i.period_end ?? "", recommendations: i.recommendations ?? [], entities: i.entities ?? [],
  findings: i.findings ?? [], timeline: i.timeline ?? [], sources: i.sources ?? [], locations: i.locations ?? [],
});

const toPayload = (d: Draft, revision: number) => ({
  revision,
  title: d.title || "Untitled investigation",
  summary: d.summary || null,
  body: d.body || null,
  category: d.category || null,
  subcategory: d.category ? d.subcategory || null : null,
  tags: d.tags.split(",").map((t) => t.trim()).filter(Boolean),
  visibility: d.visibility,
  location_name: d.location_name || null,
  location_country: d.location_country ? d.location_country.toUpperCase() : null,
  period_start: d.period_start || null,
  period_end: d.period_end || null,
  recommendations: d.recommendations.filter((r) => r.trim()),
  entities: d.entities.filter((e) => e.name.trim()),
  findings: d.findings.filter((f) => f.title.trim()),
  timeline: d.timeline.filter((t) => t.title.trim()).map((t) => ({ ...t, event_date: t.event_date || null })),
  sources: d.sources.filter((s) => s.title.trim()).map((s) => ({ ...s, url: s.url || null, accessed_at: s.accessed_at || null, source_type: s.source_type || null })),
  locations: d.locations.filter((l) => l.country || l.district || l.city),
});

type SaveState = { kind: "saved"; at: string } | { kind: "saving" } | { kind: "dirty" } | { kind: "error"; message: string; conflict?: boolean; fields?: Record<string, string[]> };

const TABS = [
  { key: "content", label: "Report" },
  { key: "findings", label: "Findings & recommendations" },
  { key: "timeline", label: "Timeline" },
  { key: "sources", label: "Sources" },
  { key: "evidence", label: "Evidence" },
  { key: "settings", label: "Classification & settings" },
];

export default function EditorPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const [inv, setInv] = useState<OwnerInvestigation | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [tab, setTab] = useState("content");
  const [save, setSave] = useState<SaveState>({ kind: "saved", at: "" });
  const [publishOpen, setPublishOpen] = useState(false);
  const [withdrawOpen, setWithdrawOpen] = useState(false);
  const [withdrawReason, setWithdrawReason] = useState("");
  const [notice, setNotice] = useState<{ tone: "success" | "danger"; text: string } | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  const revision = useRef(0);
  const dirty = useRef(false);
  const saving = useRef<Promise<boolean> | null>(null);
  const draftRef = useRef<Draft | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const apply = useCallback((r: { data: OwnerInvestigation }) => {
    setInv(r.data);
    const d = toDraft(r.data);
    setDraft(d);
    draftRef.current = d;
    revision.current = r.data.revision;
    dirty.current = false;
    setSave({ kind: "saved", at: r.data.updated_at });
  }, []);

  const load = useCallback(() => {
    api<{ data: OwnerInvestigation }>(`/me/investigations/${id}`).then(apply).catch((e) => setLoadError(errorMessage(e)));
  }, [id, apply]);

  useEffect(() => {
    api<{ data: OwnerInvestigation }>(`/me/investigations/${id}`).then(apply).catch((e) => setLoadError(errorMessage(e)));
    api<{ data: Category[] }>("/categories").then((r) => setCategories(r.data)).catch(() => undefined);
  }, [id, apply]);

  const doSave = useCallback(async (): Promise<boolean> => {
    if (saving.current) await saving.current;
    if (!dirty.current || !draftRef.current) return true;
    dirty.current = false;
    setSave({ kind: "saving" });
    const run = (async () => {
      try {
        const r = await api<{ data: OwnerInvestigation }>(`/me/investigations/${id}`, { method: "PATCH", body: toPayload(draftRef.current!, revision.current) });
        revision.current = r.data.revision;
        setInv(r.data);
        setSave(dirty.current ? { kind: "dirty" } : { kind: "saved", at: r.data.updated_at });
        return true;
      } catch (e) {
        dirty.current = true;
        const conflict = e instanceof ApiError && e.code === "revision_conflict";
        setSave({ kind: "error", message: errorMessage(e), conflict, fields: e instanceof ApiError ? e.errors : undefined });
        return false;
      } finally {
        saving.current = null;
      }
    })();
    saving.current = run;
    return run;
  }, [id]);

  // Warn before leaving with unsaved changes.
  useEffect(() => {
    const h = (e: BeforeUnloadEvent) => {
      if (dirty.current) e.preventDefault();
    };
    window.addEventListener("beforeunload", h);
    return () => window.removeEventListener("beforeunload", h);
  }, []);

  const update = (patch: Partial<Draft>) => {
    setDraft((d) => {
      const next = { ...(d as Draft), ...patch };
      draftRef.current = next;
      return next;
    });
    dirty.current = true;
    setSave({ kind: "dirty" });
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => void doSave(), 1500);
  };

  if (loadError) return <Alert tone="danger">{loadError}</Alert>;
  if (!inv || !draft) return <Spinner />;

  const editable = ["draft", "published", "withdrawn"].includes(inv.status);
  const parent = categories.find((c) => c.slug === draft.category);
  const fieldErr = (k: string) => (save.kind === "error" ? save.fields?.[k]?.[0] : undefined);

  const lifecycle = async (action: "archive" | "withdraw" | "delete") => {
    try {
      if (action === "delete") {
        if (!window.confirm("Delete this draft permanently? This cannot be undone.")) return;
        await api(`/me/investigations/${id}`, { method: "DELETE" });
        router.push("/dashboard/investigations");
        return;
      }
      const r = await api<{ data: OwnerInvestigation }>(`/me/investigations/${id}/${action}`, { method: "POST", body: action === "withdraw" ? { reason: withdrawReason } : undefined });
      setInv(r.data);
      setWithdrawOpen(false);
      setNotice({ tone: "success", text: action === "withdraw" ? "Withdrawn. Readers now see a withdrawal notice instead of the report." : "Archived. The report stays readable but comments are closed." });
    } catch (e) {
      setNotice({ tone: "danger", text: errorMessage(e) });
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link href="/dashboard/investigations" className="text-sm underline">← All investigations</Link>
          <h1 className="mt-1 font-serif text-2xl font-bold">{draft.title || "Untitled investigation"}</h1>
          <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
            <StatusBadge status={inv.status} /> <span className="font-mono">{inv.id}</span>
            {inv.current_version > 0 && <span>· published version {inv.current_version}</span>}
            <span aria-live="polite" className="ml-2">
              {save.kind === "saving" && "Saving…"}
              {save.kind === "dirty" && "Unsaved changes"}
              {save.kind === "saved" && save.at && `Saved ${formatDateTime(save.at)}`}
            </span>
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ButtonLink href={`/dashboard/investigations/${id}/preview`} variant="secondary">Preview</ButtonLink>
          {inv.current_version > 0 && <ButtonLink href={`/dashboard/investigations/${id}/versions`} variant="ghost">Versions</ButtonLink>}
          {inv.public_url && <ButtonLink href={inv.public_url} variant="ghost">View public page</ButtonLink>}
          {editable && (
            <Button onClick={async () => { if (await doSave()) setPublishOpen(true); }}>
              {inv.current_version > 0 ? (inv.status === "withdrawn" ? "Republish" : "Publish update") : "Review & publish"}
            </Button>
          )}
        </div>
      </div>

      {save.kind === "error" && (
        <Alert tone="danger" title={save.conflict ? "This investigation was changed in another window." : "Your changes could not be saved."}>
          <p>{save.message}</p>
          {save.conflict ? <Button size="sm" variant="secondary" className="mt-2" onClick={() => load()}>Load the latest version (discards unsaved edits)</Button>
            : <Button size="sm" variant="secondary" className="mt-2" onClick={() => void doSave()}>Retry</Button>}
        </Alert>
      )}
      {inv.status === "published" && inv.has_unpublished_changes && (
        <Alert tone="warning">You have edits that are not public yet. Readers still see version {inv.current_version} until you publish an update.</Alert>
      )}
      {(inv.status === "restricted" || inv.status === "removed") && (
        <Alert tone="danger" title={`This investigation has been ${inv.status} by moderators.`}>{inv.public_status_note} Check your notifications for details. Editing is disabled.</Alert>
      )}
      {notice && <Alert tone={notice.tone} live>{notice.text}</Alert>}

      <Tabs label="Editor sections" tabs={TABS.map((t) => (t.key === "evidence" ? { ...t, badge: inv.evidence?.length ?? 0 } : t))} active={tab} onChange={setTab} />

      <fieldset disabled={!editable} className="space-y-5">
        {tab === "content" && (
          <Card className="space-y-4">
            <TextInput label="Title" value={draft.title} onChange={(e) => update({ title: e.target.value })} maxLength={250} required error={fieldErr("title")} />
            <TextArea label="Summary" value={draft.summary} onChange={(e) => update({ summary: e.target.value })} maxLength={2000} rows={3} error={fieldErr("summary")}
              hint="Shown in listings and search results. At least 20 characters to publish." />
            <TextArea label="Report body (Markdown)" value={draft.body} onChange={(e) => update({ body: e.target.value })} rows={20} maxLength={200000} error={fieldErr("body")}
              hint="Use ## for headings, - for lists, [text](https://…) for links. HTML is not rendered. Clearly distinguish documented facts from allegations and opinion." className="font-mono" />
          </Card>
        )}

        {tab === "findings" && (
          <div className="space-y-5">
            <Card className="space-y-3">
              <h2 className="text-lg font-semibold">Findings</h2>
              <ListEditor items={draft.findings} onChange={(findings) => update({ findings })} empty={() => ({ title: "", body: "" })} addLabel="Add finding" itemLabel={(_, i) => `Finding ${i + 1}`}
                render={(f, set) => (<div className="space-y-3"><TextInput label="Finding" value={f.title} onChange={(e) => set({ title: e.target.value })} maxLength={250} /><TextArea label="Details" value={f.body ?? ""} onChange={(e) => set({ body: e.target.value })} maxLength={20000} /></div>)} />
            </Card>
            <Card className="space-y-3">
              <h2 className="text-lg font-semibold">Recommendations</h2>
              <ListEditor items={draft.recommendations} onChange={(recommendations) => update({ recommendations })} empty={() => ""} addLabel="Add recommendation" itemLabel={(_, i) => `Recommendation ${i + 1}`}
                render={(r, _set, i) => <TextArea label="Recommendation" value={r} onChange={(e) => update({ recommendations: draft.recommendations.map((x, k) => (k === i ? e.target.value : x)) })} maxLength={2000} rows={2} />} />
            </Card>
            <Card className="space-y-3">
              <h2 className="text-lg font-semibold">People &amp; entities referenced</h2>
              <p className="text-sm text-muted">Structured references only. Listing someone is not a claim of wrongdoing — describe their role neutrally.</p>
              <ListEditor items={draft.entities} onChange={(entities) => update({ entities })} empty={() => ({ name: "", type: "organization", role: "" })} addLabel="Add entity" itemLabel={(e, i) => e.name || `Entity ${i + 1}`}
                render={(e, set) => (
                  <div className="grid gap-3 sm:grid-cols-3">
                    <TextInput label="Name" value={e.name} onChange={(ev) => set({ name: ev.target.value })} maxLength={200} />
                    <SelectInput label="Type" value={e.type} onChange={(ev) => set({ type: ev.target.value })} options={["person", "organization", "government_body", "company", "place", "other"].map((v) => ({ value: v, label: v.replace("_", " ") }))} />
                    <TextInput label="Role" value={e.role ?? ""} onChange={(ev) => set({ role: ev.target.value })} maxLength={200} />
                  </div>
                )} />
            </Card>
          </div>
        )}

        {tab === "timeline" && (
          <Card className="space-y-3">
            <h2 className="text-lg font-semibold">Timeline</h2>
            <ListEditor items={draft.timeline} onChange={(timeline) => update({ timeline })} empty={() => ({ event_date: "", title: "", description: "" })} addLabel="Add event" itemLabel={(t, i) => t.title || `Event ${i + 1}`} max={200}
              render={(t, set) => (
                <div className="grid gap-3 sm:grid-cols-[180px_1fr]">
                  <TextInput label="Date" type="date" value={t.event_date ?? ""} onChange={(e) => set({ event_date: e.target.value })} />
                  <TextInput label="Event" value={t.title} onChange={(e) => set({ title: e.target.value })} maxLength={250} />
                  <TextArea label="Description" value={t.description ?? ""} onChange={(e) => set({ description: e.target.value })} maxLength={5000} rows={2} className="sm:col-span-2" />
                </div>
              )} />
          </Card>
        )}

        {tab === "sources" && (
          <Card className="space-y-3">
            <h2 className="text-lg font-semibold">Sources</h2>
            <p className="text-sm text-muted">Mark confidential sources as not public — they are kept with your working copy but never published.</p>
            <ListEditor items={draft.sources} onChange={(sources) => update({ sources })} empty={() => ({ title: "", source_type: "document", publisher: "", url: "", citation_text: "", accessed_at: "", is_public: true })} addLabel="Add source" itemLabel={(s, i) => s.title || `Source ${i + 1}`} max={200}
              render={(s, set, i) => (
                <div className="grid gap-3 sm:grid-cols-2">
                  <TextInput label="Title" value={s.title} onChange={(e) => set({ title: e.target.value })} maxLength={250} />
                  <SelectInput label="Type" value={s.source_type ?? "other"} onChange={(e) => set({ source_type: e.target.value })} options={["document", "website", "news_article", "official_record", "interview", "dataset", "academic", "social_media", "other"].map((v) => ({ value: v, label: v.replace("_", " ") }))} />
                  <TextInput label="Publisher" value={s.publisher ?? ""} onChange={(e) => set({ publisher: e.target.value })} maxLength={200} />
                  <TextInput label="URL (http/https)" type="url" value={s.url ?? ""} onChange={(e) => set({ url: e.target.value })} error={fieldErr(`sources.${i}.url`)} />
                  <TextInput label="Accessed on" type="date" value={s.accessed_at ?? ""} onChange={(e) => set({ accessed_at: e.target.value })} />
                  <Checkbox label="Show this source publicly" checked={s.is_public !== false} onChange={(e) => set({ is_public: e.target.checked })} className="self-end" />
                  <TextArea label="Citation / notes" value={s.citation_text ?? ""} onChange={(e) => set({ citation_text: e.target.value })} maxLength={5000} rows={2} className="sm:col-span-2" />
                </div>
              )} />
          </Card>
        )}

        {tab === "settings" && (
          <Card className="grid gap-4 sm:grid-cols-2">
            <SelectInput label="Category" value={draft.category} onChange={(e) => update({ category: e.target.value, subcategory: "" })} placeholder="Choose a category" options={categories.map((c) => ({ value: c.slug, label: c.name }))} error={fieldErr("category_id") ?? fieldErr("category")} />
            <SelectInput label="Subcategory" value={draft.subcategory} onChange={(e) => update({ subcategory: e.target.value })} placeholder="None" options={(parent?.children ?? []).map((c) => ({ value: c.slug, label: c.name }))} disabled={!parent?.children?.length} />
            <TextInput label="Tags (comma separated)" value={draft.tags} onChange={(e) => update({ tags: e.target.value })} hint="Up to 15." className="sm:col-span-2" />
            <TextInput label="Location (display name)" value={draft.location_name} onChange={(e) => update({ location_name: e.target.value })} maxLength={200} />
            <TextInput label="Country code (e.g. BD)" value={draft.location_country} onChange={(e) => update({ location_country: e.target.value.slice(0, 2) })} maxLength={2} error={fieldErr("location_country")} />
            <TextInput label="Investigation period — from" type="date" value={draft.period_start} onChange={(e) => update({ period_start: e.target.value })} />
            <TextInput label="Investigation period — to" type="date" value={draft.period_end} onChange={(e) => update({ period_end: e.target.value })} error={fieldErr("period_end")} />
            <div className="sm:col-span-2">
              <h3 className="mb-2 text-sm font-semibold">Detailed locations</h3>
              <ListEditor items={draft.locations} onChange={(locations) => update({ locations })} empty={() => ({ country: "BD", region: "", district: "", city: "", area: "" })} addLabel="Add location" itemLabel={(l, i) => l.district || l.city || `Location ${i + 1}`} max={20}
                render={(l, set) => (
                  <div className="grid gap-3 sm:grid-cols-5">
                    <TextInput label="Country" value={l.country ?? ""} maxLength={2} onChange={(e) => set({ country: e.target.value.toUpperCase() })} />
                    <TextInput label="Division/region" value={l.region ?? ""} onChange={(e) => set({ region: e.target.value })} />
                    <TextInput label="District" value={l.district ?? ""} onChange={(e) => set({ district: e.target.value })} />
                    <TextInput label="City/upazila" value={l.city ?? ""} onChange={(e) => set({ city: e.target.value })} />
                    <TextInput label="Area" value={l.area ?? ""} onChange={(e) => set({ area: e.target.value })} />
                  </div>
                )} />
            </div>
            <SelectInput label="Visibility after publishing" value={draft.visibility} onChange={(e) => update({ visibility: e.target.value })} className="sm:col-span-2" options={[
              { value: "public", label: "Public — listed, searchable and indexable" },
              { value: "unlisted", label: "Unlisted — only people with the link; not indexed" },
              { value: "private", label: "Private — not reachable publicly" },
            ]} />
          </Card>
        )}
      </fieldset>

      {tab === "evidence" && <EvidenceManager investigationId={inv.id} initial={inv.evidence ?? []} disabled={!editable} />}

      {inv.status !== "draft" && editable && (
        <Card as="section" className="space-y-3">
          <h2 className="text-lg font-semibold">Lifecycle</h2>
          <div className="flex flex-wrap gap-2">
            {inv.status === "published" && <Button variant="secondary" onClick={() => { if (window.confirm("Archive this investigation? It stays readable, comments close and it can no longer be updated.")) void lifecycle("archive"); }}>Archive</Button>}
            {inv.status === "published" && <Button variant="danger" onClick={() => setWithdrawOpen(true)}>Withdraw</Button>}
          </div>
        </Card>
      )}
      {inv.status === "draft" && inv.current_version === 0 && (
        <p><Button variant="ghost" className="text-danger" onClick={() => void lifecycle("delete")}>Delete draft</Button></p>
      )}

      <PublishDialog open={publishOpen} onClose={() => setPublishOpen(false)} investigation={inv} beforePublish={doSave}
        onPublished={(r) => { setInv(r); setPublishOpen(false); setNotice({ tone: "success", text: `Published version ${r.current_version}.` }); }} />
      <Modal open={withdrawOpen} onClose={() => setWithdrawOpen(false)} title="Withdraw investigation" footer={<><Button variant="ghost" onClick={() => setWithdrawOpen(false)}>Cancel</Button><Button variant="danger" disabled={withdrawReason.trim().length < 5} onClick={() => void lifecycle("withdraw")}>Withdraw</Button></>}>
        <p className="mb-3 text-sm">Readers will see a notice that you withdrew this investigation, with the reason below. The report text, evidence and comments will no longer be shown. The record is preserved.</p>
        <TextArea label="Public reason for withdrawal" value={withdrawReason} onChange={(e) => setWithdrawReason(e.target.value)} required maxLength={1000} />
      </Modal>
    </div>
  );
}
