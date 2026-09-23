"use client";

import Link from "next/link";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { api, ApiError, errorMessage } from "@/lib/api";

export function TipForm({ investigator, investigation }: { investigator?: string; investigation?: string }) {
  const { user } = useAuth();
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [anonymous, setAnonymous] = useState(true);
  const [contactPref, setContactPref] = useState("platform_messaging");
  const [contactValue, setContactValue] = useState("");
  const [files, setFiles] = useState<FileList | null>(null);
  const [agree, setAgree] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);
  const [done, setDone] = useState<{ id: string; access_code: string | null; message: string } | null>(null);

  if (!investigator && !investigation) {
    return <Alert tone="warning">Open this page from an investigation or investigator profile to choose who receives your tip.</Alert>;
  }

  if (done) {
    return (
      <div className="space-y-4">
        <Alert tone="success" title="Tip sent" live>{done.message}</Alert>
        {done.access_code && (
          <div className="rounded-lg border-2 border-warning bg-warning-soft p-5">
            <p className="text-sm font-semibold">Your one-time access code</p>
            <p className="my-3 select-all break-all font-mono text-2xl tracking-wider" aria-label="Access code">{done.access_code}</p>
            <p className="text-sm">Write it down or store it somewhere safe <strong>now</strong>. Onushondhan only keeps a scrambled version, so it cannot be shown again or recovered. Use it on the <Link href="/tips/access" className="underline">tip replies page</Link> to read and answer the investigator.</p>
          </div>
        )}
        {user && <p className="text-sm">Follow the conversation from <Link href="/account/tips" className="underline">your tips</Link>.</p>}
      </div>
    );
  }

  const fe = (f: string) => (error instanceof ApiError ? error.fieldError(f) : undefined);

  return (
    <form
      className="space-y-5"
      onSubmit={async (e) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        const fd = new FormData();
        if (investigation) fd.append("investigation", investigation);
        else if (investigator) fd.append("investigator", investigator);
        fd.append("subject", subject);
        fd.append("message", message);
        fd.append("anonymous_to_investigator", user && !anonymous ? "0" : "1");
        fd.append("contact_preference", contactPref);
        if (contactValue && (contactPref === "email" || contactPref === "phone")) fd.append("contact_value", contactValue);
        fd.append("agree_to_content_policy", agree ? "1" : "0");
        Array.from(files ?? []).forEach((f) => fd.append("attachments[]", f));
        try {
          const res = await api<{ data: { id: string; access_code: string | null; message: string } }>("/tips", { method: "POST", body: fd });
          setDone(res.data);
        } catch (err) {
          setError(err instanceof ApiError ? err : errorMessage(err));
        } finally {
          setBusy(false);
        }
      }}
    >
      <Alert title={user ? "About your anonymity" : "Sending as a guest"}>
        {user ? (
          <p>You are signed in as <strong>{user.username}</strong>. You can hide your identity from the investigator; Onushondhan still knows which account sent the tip.</p>
        ) : (
          <p>You are not signed in, so the tip is anonymous to the platform: no account, IP address or browser details are attached to it. You will receive an access code to read replies. Onushondhan cannot promise absolute anonymity — avoid details that identify you if that matters.</p>
        )}
      </Alert>

      <TextInput label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} required minLength={3} maxLength={200} error={fe("subject")} />
      <TextArea label="Your tip" value={message} onChange={(e) => setMessage(e.target.value)} required minLength={10} maxLength={20000} rows={8} error={fe("message")}
        hint="Describe what you know. Do not include information you are not comfortable sharing with the investigator." />

      {user && <Checkbox label="Hide my identity from the investigator (recommended)" checked={anonymous} onChange={(e) => setAnonymous(e.target.checked)} />}

      <SelectInput label="How may the investigator follow up?" value={contactPref} onChange={(e) => setContactPref(e.target.value)} options={[
        { value: "platform_messaging", label: user ? "Through Onushondhan messages" : "Through Onushondhan messages (using my access code)" },
        { value: "email", label: "By email (shared with the investigator)" },
        { value: "phone", label: "By phone (shared with the investigator)" },
        { value: "none", label: "No follow-up" },
      ]} />
      {(contactPref === "email" || contactPref === "phone") && (
        <TextInput label={contactPref === "email" ? "Email to share" : "Phone to share"} type={contactPref === "email" ? "email" : "tel"} value={contactValue} onChange={(e) => setContactValue(e.target.value)} required error={fe("contact_value")}
          hint="This will be visible to the investigator and reveals who you are to them." />
      )}

      <div className="flex flex-col gap-1">
        <label htmlFor="tip-files" className="text-sm font-medium">Attachments (optional, up to 5)</label>
        <input id="tip-files" type="file" multiple onChange={(e) => setFiles(e.target.files)} className="text-sm" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.mp3,.m4a,.wav,.ogg,.txt,.csv,.doc,.docx,.xls,.xlsx,.odt,.ods" aria-describedby="tip-files-hint" />
        <p id="tip-files-hint" className="text-xs text-muted">Files can contain hidden metadata (such as location). Remove it before uploading if it could identify you.</p>
        {fe("attachments") && <p role="alert" className="text-sm text-danger">{fe("attachments")}</p>}
      </div>

      <Checkbox label={<>I have read the <Link href="/pages/content-policy" className="underline" target="_blank">Content Policy</Link> and am sending this tip in good faith.</>} checked={agree} onChange={(e) => setAgree(e.target.checked)} required error={fe("agree_to_content_policy")} />

      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy}>Send tip</Button>
    </form>
  );
}
