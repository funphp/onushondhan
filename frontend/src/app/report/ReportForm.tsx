"use client";

import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { useI18n } from "@/i18n/client";
import { api, ApiError, errorMessage } from "@/lib/api";

const TYPES = ["factual_dispute", "privacy", "personal_information", "copyright", "harassment", "prohibited_content", "spam", "other_terms_violation"] as const;

export function ReportForm({ investigation, comment, evidence }: { investigation?: string; comment?: string; evidence?: string }) {
  const { user } = useAuth();
  const { t } = useI18n();
  const [type, setType] = useState("factual_dispute");
  const [description, setDescription] = useState("");
  const [email, setEmail] = useState("");
  const [goodFaith, setGoodFaith] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);
  const [done, setDone] = useState<string | null>(null);

  if (!investigation && !comment && !evidence) {
    return <Alert tone="warning">{t.report.pickTarget}</Alert>;
  }
  if (done) return <Alert tone="success" title={t.report.received} live>{done}</Alert>;
  const fe = (f: string) => (error instanceof ApiError ? error.fieldError(f) : undefined);

  return (
    <form className="space-y-5" onSubmit={async (e) => {
      e.preventDefault();
      setBusy(true);
      setError(null);
      try {
        const res = await api<{ data: { message: string } }>("/complaints", { method: "POST", body: {
          complaint_type: type, description, investigation, comment, evidence, reporter_email: user ? undefined : email, good_faith: goodFaith,
        } });
        setDone(res.data.message);
      } catch (err) {
        setError(err instanceof ApiError ? err : errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <SelectInput label={t.report.problem} value={type} onChange={(e) => setType(e.target.value)} options={TYPES.map((value) => ({ value, label: t.complaintTypes[value] }))} />
      {type === "factual_dispute" && (
        <Alert>{t.report.factualNote}</Alert>
      )}
      <TextArea label={t.report.details} value={description} onChange={(e) => setDescription(e.target.value)} required minLength={20} maxLength={10000} rows={7} error={fe("description")}
        hint={t.report.detailsHint} />
      {!user && <TextInput label={t.report.email} type="email" value={email} onChange={(e) => setEmail(e.target.value)} required error={fe("reporter_email")} />}
      <Checkbox label={t.report.goodFaith} checked={goodFaith} onChange={(e) => setGoodFaith(e.target.checked)} required error={fe("good_faith")} />
      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy}>{t.report.submit}</Button>
    </form>
  );
}
