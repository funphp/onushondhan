"use client";

import Link from "next/link";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { useI18n } from "@/i18n/client";
import { api, ApiError, errorMessage } from "@/lib/api";

export function TipForm({ investigator, investigation }: { investigator?: string; investigation?: string }) {
  const { user } = useAuth();
  const { t } = useI18n();
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
    return <Alert tone="warning">{t.tips.pickTarget}</Alert>;
  }

  if (done) {
    return (
      <div className="space-y-4">
        <Alert tone="success" title={t.tips.sent} live>{done.message}</Alert>
        {done.access_code && (
          <div className="rounded-lg border-2 border-warning bg-warning-soft p-5">
            <p className="text-sm font-semibold">{t.tips.accessCodeTitle}</p>
            <p className="my-3 select-all break-all font-mono text-2xl tracking-wider" aria-label={t.tips.accessCodeLabel}>{done.access_code}</p>
            <p className="text-sm">{t.tips.accessCodeHelp} <Link href="/tips/access" className="underline">{t.tips.repliesPage}</Link></p>
          </div>
        )}
        {user && <p className="text-sm">{t.tips.followFromAccount} <Link href="/account/tips" className="underline">{t.tips.yourTips}</Link>.</p>}
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
      <Alert title={user ? t.tips.anonymityTitleMember : t.tips.anonymityTitleGuest}>
        {user ? (
          <p>{t.tips.anonymityMember(user.username)}</p>
        ) : (
          <p>{t.tips.anonymityGuest}</p>
        )}
      </Alert>

      <TextInput label={t.tips.subject} value={subject} onChange={(e) => setSubject(e.target.value)} required minLength={3} maxLength={200} error={fe("subject")} />
      <TextArea label={t.tips.yourTip} value={message} onChange={(e) => setMessage(e.target.value)} required minLength={10} maxLength={20000} rows={8} error={fe("message")}
        hint={t.tips.yourTipHint} />

      {user && <Checkbox label={t.tips.hideIdentity} checked={anonymous} onChange={(e) => setAnonymous(e.target.checked)} />}

      <SelectInput label={t.tips.followUp} value={contactPref} onChange={(e) => setContactPref(e.target.value)} options={[
        { value: "platform_messaging", label: user ? t.tips.viaPlatformMember : t.tips.viaPlatformGuest },
        { value: "email", label: t.tips.viaEmail },
        { value: "phone", label: t.tips.viaPhone },
        { value: "none", label: t.tips.noFollowUp },
      ]} />
      {(contactPref === "email" || contactPref === "phone") && (
        <TextInput label={contactPref === "email" ? t.tips.emailToShare : t.tips.phoneToShare} type={contactPref === "email" ? "email" : "tel"} value={contactValue} onChange={(e) => setContactValue(e.target.value)} required error={fe("contact_value")}
          hint={t.tips.contactHint} />
      )}

      <div className="flex flex-col gap-1">
        <label htmlFor="tip-files" className="text-sm font-medium">{t.tips.attachments}</label>
        <input id="tip-files" type="file" multiple onChange={(e) => setFiles(e.target.files)} className="text-sm" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.mp3,.m4a,.wav,.ogg,.txt,.csv,.doc,.docx,.xls,.xlsx,.odt,.ods" aria-describedby="tip-files-hint" />
        <p id="tip-files-hint" className="text-xs text-muted">{t.tips.attachmentsHint}</p>
        {fe("attachments") && <p role="alert" className="text-sm text-danger">{fe("attachments")}</p>}
      </div>

      <Checkbox label={<>{t.tips.agreePrefix} <Link href="/pages/content-policy" className="underline" target="_blank">{t.footer.contentPolicy}</Link> {t.tips.agreeSuffix}</>} checked={agree} onChange={(e) => setAgree(e.target.checked)} required error={fe("agree_to_content_policy")} />

      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy}>{t.tips.send}</Button>
    </form>
  );
}
