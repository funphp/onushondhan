"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { CoverImageField } from "@/components/dashboard/CoverImageField";
import { EvidenceManager } from "@/components/dashboard/EvidenceManager";
import { ListEditor } from "@/components/dashboard/ListEditor";
import { PublishDialog } from "@/components/dashboard/PublishDialog";
import { RichTextEditor } from "@/components/dashboard/RichTextEditor";
import { StatusBadge } from "@/components/dashboard/StatusBadge";
import { Alert } from "@/components/ui/Alert";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card, Spinner } from "@/components/ui/Card";
import { Checkbox, SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Tabs } from "@/components/ui/Tabs";
import { api, ApiError, errorMessage } from "@/lib/api";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";
import type { Category, Entity, Finding, OwnerInvestigation, SourceItem, TimelineItem } from "@/lib/types";

type Loc = { country: string | null; region: string | null; district: string | null; city: string | null; area: string | null };

interface Draft {
  title: string; body: string; category: string; subcategory: string; tags: string; visibility: string;
  location_name: string; location_country: string; period_start: string; period_end: string;
  recommendations: string[]; entities: Entity[]; findings: Finding[]; timeline: TimelineItem[]; sources: SourceItem[]; locations: Loc[];
}

const toDraft = (i: OwnerInvestigation): Draft => ({
  title: i.title ?? "", body: i.body ?? "", category: i.category ?? "", subcategory: i.subcategory ?? "",
  tags: (i.tags ?? []).join(", "), visibility: i.visibility, location_name: i.location_name ?? "", location_country: i.location_country ?? "",
  period_start: i.period_start ?? "", period_end: i.period_end ?? "", recommendations: i.recommendations ?? [], entities: i.entities ?? [],
  findings: i.findings ?? [], timeline: i.timeline ?? [], sources: i.sources ?? [], locations: i.locations ?? [],
});

const toPayload = (d: Draft, revision: number, untitled: string) => ({
  revision,
  title: d.title || untitled,
  body: d.body || null,
  category: d.category || null,
  subcategory: d.category ? d.subcategory || null : null,
  tags: d.tags.split(/[,،]/).map((tag) => tag.trim()).filter(Boolean),
  visibility: d.visibility,
  location_name: d.location_name || null,
  location_country: d.location_country ? d.location_country.toUpperCase() : null,
  period_start: d.period_start || null,
  period_end: d.period_end || null,
  recommendations: d.recommendations.filter((r) => r.trim()),
  entities: d.entities.filter((e) => e.name.trim()),
  findings: d.findings.filter((item) => item.title.trim()),
  timeline: d.timeline.filter((ev) => ev.title.trim()).map((ev) => ({ ...ev, event_date: ev.event_date || null })),
  sources: d.sources.filter((s) => s.title.trim()).map((s) => ({ ...s, url: s.url || null, accessed_at: s.accessed_at || null, source_type: s.source_type || null })),
  locations: d.locations.filter((l) => l.country || l.district || l.city),
});

type SaveState = { kind: "saved"; at: string } | { kind: "saving" } | { kind: "dirty" } | { kind: "error"; message: string; conflict?: boolean; fields?: Record<string, string[]> };

const TABS = ["content", "findings", "timeline", "sources", "evidence", "settings"] as const;
const ENTITY_TYPES = ["person", "organization", "government_body", "company", "place", "other"] as const;
const SOURCE_TYPES = ["document", "website", "news_article", "official_record", "interview", "dataset", "academic", "social_media", "other"] as const;

export default function EditorPage() {
  const { id } = useParams<{ id: string }>();
  const { t, f } = useI18n();
  const untitled = t.editor.untitled;
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
  const [editorKey, setEditorKey] = useState(0);

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
    setEditorKey((k) => k + 1); // remount the rich-text editor with the loaded content
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
        const r = await api<{ data: OwnerInvestigation }>(`/me/investigations/${id}`, { method: "PATCH", body: toPayload(draftRef.current!, revision.current, untitled) });
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
  }, [id, untitled]);

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
        if (!window.confirm(t.editor.deleteConfirm)) return;
        await api(`/me/investigations/${id}`, { method: "DELETE" });
        router.push("/dashboard/investigations");
        return;
      }
      const r = await api<{ data: OwnerInvestigation }>(`/me/investigations/${id}/${action}`, { method: "POST", body: action === "withdraw" ? { reason: withdrawReason } : undefined });
      setInv(r.data);
      setWithdrawOpen(false);
      setNotice({ tone: "success", text: action === "withdraw" ? t.editor.withdrawnNotice : t.editor.archivedNotice });
    } catch (e) {
      setNotice({ tone: "danger", text: errorMessage(e) });
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link href="/dashboard/investigations" className="text-sm underline">{t.editor.all}</Link>
          <h1 className="mt-1 font-serif text-2xl font-bold">{draft.title || t.editor.untitled}</h1>
          <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
            <StatusBadge status={inv.status} /> <span className="font-mono">{inv.id}</span>
            {inv.current_version > 0 && <span>{t.editor.publishedVersion(f.digits(inv.current_version))}</span>}
            <span aria-live="polite" className="ml-2">
              {save.kind === "saving" && t.editor.saving}
              {save.kind === "dirty" && t.editor.unsaved}
              {save.kind === "saved" && save.at && t.editor.savedAt(f.dateTime(save.at))}
            </span>
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ButtonLink href={`/dashboard/investigations/${id}/preview`} variant="secondary">{t.editor.preview}</ButtonLink>
          {inv.current_version > 0 && <ButtonLink href={`/dashboard/investigations/${id}/versions`} variant="ghost">{t.editor.versions}</ButtonLink>}
          {inv.public_url && <ButtonLink href={inv.public_url} variant="ghost">{t.editor.viewPublic}</ButtonLink>}
          {editable && (
            <Button onClick={async () => { if (await doSave()) setPublishOpen(true); }}>
              {inv.current_version > 0 ? (inv.status === "withdrawn" ? t.editor.republish : t.editor.publishUpdate) : t.editor.reviewPublish}
            </Button>
          )}
        </div>
      </div>

      {save.kind === "error" && (
        <Alert tone="danger" title={save.conflict ? t.editor.conflictTitle : t.editor.saveFailedTitle}>
          <p>{save.message}</p>
          {save.conflict ? <Button size="sm" variant="secondary" className="mt-2" onClick={() => load()}>{t.editor.loadLatest}</Button>
            : <Button size="sm" variant="secondary" className="mt-2" onClick={() => void doSave()}>{t.common.retry}</Button>}
        </Alert>
      )}
      {inv.status === "published" && inv.has_unpublished_changes && (
        <Alert tone="warning">{t.editor.unpublishedNotice(f.digits(inv.current_version))}</Alert>
      )}
      {(inv.status === "restricted" || inv.status === "removed") && (
        <Alert tone="danger" title={t.editor.moderated(labelFor(t.statuses, inv.status).toLowerCase())}>{inv.public_status_note} {t.editor.moderatedHint}</Alert>
      )}
      {notice && <Alert tone={notice.tone} live>{notice.text}</Alert>}

      <Tabs label={t.editor.sections} tabs={TABS.map((key) => ({ key, label: t.editor.tabs[key], ...(key === "evidence" ? { badge: inv.evidence?.length ?? 0 } : {}) }))} active={tab} onChange={setTab} />

      <fieldset disabled={!editable} className="space-y-5">
        {tab === "content" && (
          <Card className="space-y-4">
            <p className="text-sm text-muted">{t.editor.contentLanguageHint}</p>
            <CoverImageField investigation={inv} disabled={!editable} onChange={(r) => setInv(r)} />
            <TextInput label={t.editor.title} value={draft.title} onChange={(e) => update({ title: e.target.value })} maxLength={250} required error={fieldErr("title")} />
            <RichTextEditor key={editorKey} label={t.editor.body} value={draft.body} onChange={(body) => update({ body })} disabled={!editable}
              error={fieldErr("body")} hint={t.editor.bodyHint} placeholder={t.editor.bodyPlaceholder} />
            <section aria-labelledby="auto-summary" className="rounded-md border border-line bg-accent-soft/40 p-4">
              <h2 id="auto-summary" className="text-sm font-medium">{t.editor.summary}</h2>
              <p className="mt-1 text-sm leading-relaxed" aria-live="polite">{inv.summary || <span className="text-muted">{t.editor.summaryEmpty}</span>}</p>
              <p className="mt-2 text-xs text-muted">{t.editor.summaryHint}</p>
            </section>
          </Card>
        )}

        {tab === "findings" && (
          <div className="space-y-5">
            <Card className="space-y-3">
              <h2 className="text-lg font-semibold">{t.editor.findings}</h2>
              <ListEditor items={draft.findings} onChange={(findings) => update({ findings })} empty={() => ({ title: "", body: "" })} addLabel={t.editor.addFinding} itemLabel={(_, i) => t.editor.findingN(f.digits(i + 1))}
                render={(item, set) => (<div className="space-y-3"><TextInput label={t.editor.finding} value={item.title} onChange={(e) => set({ title: e.target.value })} maxLength={250} /><TextArea label={t.editor.details} value={item.body ?? ""} onChange={(e) => set({ body: e.target.value })} maxLength={20000} /></div>)} />
            </Card>
            <Card className="space-y-3">
              <h2 className="text-lg font-semibold">{t.editor.recommendations}</h2>
              <ListEditor items={draft.recommendations} onChange={(recommendations) => update({ recommendations })} empty={() => ""} addLabel={t.editor.addRecommendation} itemLabel={(_, i) => t.editor.recommendationN(f.digits(i + 1))}
                render={(r, _set, i) => <TextArea label={t.editor.recommendation} value={r} onChange={(e) => update({ recommendations: draft.recommendations.map((x, k) => (k === i ? e.target.value : x)) })} maxLength={2000} rows={2} />} />
            </Card>
            <Card className="space-y-3">
              <h2 className="text-lg font-semibold">{t.editor.entities}</h2>
              <p className="text-sm text-muted">{t.editor.entitiesHint}</p>
              <ListEditor items={draft.entities} onChange={(entities) => update({ entities })} empty={() => ({ name: "", type: "organization", role: "" })} addLabel={t.editor.addEntity} itemLabel={(e, i) => e.name || t.editor.entityN(f.digits(i + 1))}
                render={(e, set) => (
                  <div className="grid gap-3 sm:grid-cols-3">
                    <TextInput label={t.editor.name} value={e.name} onChange={(ev) => set({ name: ev.target.value })} maxLength={200} />
                    <SelectInput label={t.editor.type} value={e.type} onChange={(ev) => set({ type: ev.target.value })} options={ENTITY_TYPES.map((v) => ({ value: v, label: t.entityTypes[v] }))} />
                    <TextInput label={t.editor.role} value={e.role ?? ""} onChange={(ev) => set({ role: ev.target.value })} maxLength={200} />
                  </div>
                )} />
            </Card>
          </div>
        )}

        {tab === "timeline" && (
          <Card className="space-y-3">
            <h2 className="text-lg font-semibold">{t.editor.timeline}</h2>
            <ListEditor items={draft.timeline} onChange={(timeline) => update({ timeline })} empty={() => ({ event_date: "", title: "", description: "" })} addLabel={t.editor.addEvent} itemLabel={(ev, i) => ev.title || t.editor.eventN(f.digits(i + 1))} max={200}
              render={(ev, set) => (
                <div className="grid gap-3 sm:grid-cols-[180px_1fr]">
                  <TextInput label={t.editor.date} type="date" value={ev.event_date ?? ""} onChange={(e) => set({ event_date: e.target.value })} />
                  <TextInput label={t.editor.event} value={ev.title} onChange={(e) => set({ title: e.target.value })} maxLength={250} />
                  <TextArea label={t.editor.description} value={ev.description ?? ""} onChange={(e) => set({ description: e.target.value })} maxLength={5000} rows={2} className="sm:col-span-2" />
                </div>
              )} />
          </Card>
        )}

        {tab === "sources" && (
          <Card className="space-y-3">
            <h2 className="text-lg font-semibold">{t.editor.sources}</h2>
            <p className="text-sm text-muted">{t.editor.sourcesHint}</p>
            <ListEditor items={draft.sources} onChange={(sources) => update({ sources })} empty={() => ({ title: "", source_type: "document", publisher: "", url: "", citation_text: "", accessed_at: "", is_public: true })} addLabel={t.editor.addSource} itemLabel={(s, i) => s.title || t.editor.sourceN(f.digits(i + 1))} max={200}
              render={(s, set, i) => (
                <div className="grid gap-3 sm:grid-cols-2">
                  <TextInput label={t.editor.sourceTitle} value={s.title} onChange={(e) => set({ title: e.target.value })} maxLength={250} />
                  <SelectInput label={t.editor.type} value={s.source_type ?? "other"} onChange={(e) => set({ source_type: e.target.value })} options={SOURCE_TYPES.map((v) => ({ value: v, label: t.sourceTypes[v] }))} />
                  <TextInput label={t.editor.publisher} value={s.publisher ?? ""} onChange={(e) => set({ publisher: e.target.value })} maxLength={200} />
                  <TextInput label={t.editor.url} type="url" value={s.url ?? ""} onChange={(e) => set({ url: e.target.value })} error={fieldErr(`sources.${i}.url`)} />
                  <TextInput label={t.editor.accessedOn} type="date" value={s.accessed_at ?? ""} onChange={(e) => set({ accessed_at: e.target.value })} />
                  <Checkbox label={t.editor.showPublicly} checked={s.is_public !== false} onChange={(e) => set({ is_public: e.target.checked })} className="self-end" />
                  <TextArea label={t.editor.citation} value={s.citation_text ?? ""} onChange={(e) => set({ citation_text: e.target.value })} maxLength={5000} rows={2} className="sm:col-span-2" />
                </div>
              )} />
          </Card>
        )}

        {tab === "settings" && (
          <Card className="grid gap-4 sm:grid-cols-2">
            <SelectInput label={t.editor.category} value={draft.category} onChange={(e) => update({ category: e.target.value, subcategory: "" })} placeholder={t.editor.chooseCategory} options={categories.map((c) => ({ value: c.slug, label: c.name }))} error={fieldErr("category_id") ?? fieldErr("category")} />
            <SelectInput label={t.editor.subcategory} value={draft.subcategory} onChange={(e) => update({ subcategory: e.target.value })} placeholder={t.common.none} options={(parent?.children ?? []).map((c) => ({ value: c.slug, label: c.name }))} disabled={!parent?.children?.length} />
            <TextInput label={t.editor.tags} value={draft.tags} onChange={(e) => update({ tags: e.target.value })} hint={t.editor.tagsHint} className="sm:col-span-2" />
            <TextInput label={t.editor.locationName} value={draft.location_name} onChange={(e) => update({ location_name: e.target.value })} maxLength={200} />
            <TextInput label={t.editor.country} value={draft.location_country} onChange={(e) => update({ location_country: e.target.value.slice(0, 2) })} maxLength={2} error={fieldErr("location_country")} />
            <TextInput label={t.editor.periodFrom} type="date" value={draft.period_start} onChange={(e) => update({ period_start: e.target.value })} />
            <TextInput label={t.editor.periodTo} type="date" value={draft.period_end} onChange={(e) => update({ period_end: e.target.value })} error={fieldErr("period_end")} />
            <div className="sm:col-span-2">
              <h3 className="mb-2 text-sm font-semibold">{t.editor.detailedLocations}</h3>
              <ListEditor items={draft.locations} onChange={(locations) => update({ locations })} empty={() => ({ country: "BD", region: "", district: "", city: "", area: "" })} addLabel={t.editor.addLocation} itemLabel={(l, i) => l.district || l.city || t.editor.locationN(f.digits(i + 1))} max={20}
                render={(l, set) => (
                  <div className="grid gap-3 sm:grid-cols-5">
                    <TextInput label={t.editor.countryShort} value={l.country ?? ""} maxLength={2} onChange={(e) => set({ country: e.target.value.toUpperCase() })} />
                    <TextInput label={t.editor.region} value={l.region ?? ""} onChange={(e) => set({ region: e.target.value })} />
                    <TextInput label={t.editor.district} value={l.district ?? ""} onChange={(e) => set({ district: e.target.value })} />
                    <TextInput label={t.editor.city} value={l.city ?? ""} onChange={(e) => set({ city: e.target.value })} />
                    <TextInput label={t.editor.area} value={l.area ?? ""} onChange={(e) => set({ area: e.target.value })} />
                  </div>
                )} />
            </div>
            <SelectInput label={t.editor.visibilityAfter} value={draft.visibility} onChange={(e) => update({ visibility: e.target.value })} className="sm:col-span-2" options={[
              { value: "public", label: t.editor.visibilityPublic },
              { value: "unlisted", label: t.editor.visibilityUnlisted },
              { value: "private", label: t.editor.visibilityPrivate },
            ]} />
          </Card>
        )}
      </fieldset>

      {tab === "evidence" && <EvidenceManager investigationId={inv.id} initial={inv.evidence ?? []} disabled={!editable} />}

      {inv.status !== "draft" && editable && (
        <Card as="section" className="space-y-3">
          <h2 className="text-lg font-semibold">{t.editor.lifecycle}</h2>
          <div className="flex flex-wrap gap-2">
            {inv.status === "published" && <Button variant="secondary" onClick={() => { if (window.confirm(t.editor.archiveConfirm)) void lifecycle("archive"); }}>{t.editor.archive}</Button>}
            {inv.status === "published" && <Button variant="danger" onClick={() => setWithdrawOpen(true)}>{t.editor.withdraw}</Button>}
          </div>
        </Card>
      )}
      {inv.status === "draft" && inv.current_version === 0 && (
        <p><Button variant="ghost" className="text-danger" onClick={() => void lifecycle("delete")}>{t.editor.deleteDraft}</Button></p>
      )}

      <PublishDialog open={publishOpen} onClose={() => setPublishOpen(false)} investigation={inv} beforePublish={doSave}
        onPublished={(r) => { setInv(r); setPublishOpen(false); setNotice({ tone: "success", text: t.editor.publishedNotice(f.digits(r.current_version)) }); }} />
      <Modal open={withdrawOpen} onClose={() => setWithdrawOpen(false)} title={t.editor.withdrawTitle} footer={<><Button variant="ghost" onClick={() => setWithdrawOpen(false)}>{t.common.cancel}</Button><Button variant="danger" disabled={withdrawReason.trim().length < 5} onClick={() => void lifecycle("withdraw")}>{t.editor.withdraw}</Button></>}>
        <p className="mb-3 text-sm">{t.editor.withdrawLead}</p>
        <TextArea label={t.editor.withdrawReason} value={withdrawReason} onChange={(e) => setWithdrawReason(e.target.value)} required maxLength={1000} />
      </Modal>
    </div>
  );
}
