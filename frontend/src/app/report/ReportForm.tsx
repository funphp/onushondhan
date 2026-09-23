"use client";

import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { api, ApiError, errorMessage } from "@/lib/api";

const TYPES = [
  { value: "factual_dispute", label: "Factual dispute — I believe the report is wrong" },
  { value: "privacy", label: "Privacy — it exposes private information about me or others" },
  { value: "personal_information", label: "Personal information (addresses, ID numbers, phone numbers)" },
  { value: "copyright", label: "Copyright — it uses my material without permission" },
  { value: "harassment", label: "Harassment or threats" },
  { value: "prohibited_content", label: "Illegal or prohibited content" },
  { value: "spam", label: "Spam or manipulation" },
  { value: "other_terms_violation", label: "Other violation of the Terms" },
];

export function ReportForm({ investigation, comment, evidence }: { investigation?: string; comment?: string; evidence?: string }) {
  const { user } = useAuth();
  const [type, setType] = useState("factual_dispute");
  const [description, setDescription] = useState("");
  const [email, setEmail] = useState("");
  const [goodFaith, setGoodFaith] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);
  const [done, setDone] = useState<string | null>(null);

  if (!investigation && !comment && !evidence) {
    return <Alert tone="warning">Use the “Report” link on the investigation, comment or evidence you want to report.</Alert>;
  }
  if (done) return <Alert tone="success" title="Complaint received" live>{done}</Alert>;
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
      <SelectInput label="What is the problem?" value={type} onChange={(e) => setType(e.target.value)} options={TYPES} />
      {type === "factual_dispute" && (
        <Alert>Onushondhan does not independently verify reports or decide factual disputes. We will record your dispute, may ask the investigator to review it, and will act where the Content Policy is breached. Include the specific statements and any evidence.</Alert>
      )}
      <TextArea label="Details" value={description} onChange={(e) => setDescription(e.target.value)} required minLength={20} maxLength={10000} rows={7} error={fe("description")}
        hint="The investigator may see the category and your description, but never who filed the complaint." />
      {!user && <TextInput label="Your email (so we can follow up)" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required error={fe("reporter_email")} />}
      <Checkbox label="The information I have provided is accurate and submitted in good faith." checked={goodFaith} onChange={(e) => setGoodFaith(e.target.checked)} required error={fe("good_faith")} />
      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy}>Submit complaint</Button>
    </form>
  );
}
