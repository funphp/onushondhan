"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Checkbox, TextInput } from "@/components/ui/Field";
import { LanguageSwitcher } from "@/components/site/LanguageSwitcher";
import { useI18n } from "@/i18n/client";
import { api, ApiError, errorMessage } from "@/lib/api";
import type { Me } from "@/lib/types";

const EMAIL_PREFS = [
  ["comments", "prefComments"],
  ["tips", "prefTips"],
  ["followed_investigators", "prefFollowed"],
  ["moderation", "prefModeration"],
] as const;

export default function AccountSettingsPage() {
  const { user, setUser } = useAuth();
  const { t } = useI18n();
  const router = useRouter();
  if (!user) return null;
  return (
    <div className="space-y-6">
      <h1 className="font-serif text-3xl font-bold">{t.account.settingsTitle}</h1>
      <ProfileCard user={user} onSaved={setUser} />
      <Card as="section">
        <h2 className="mb-1 text-lg font-semibold">{t.account.language}</h2>
        <p className="mb-4 text-sm text-muted">{t.account.languageHint}</p>
        <LanguageSwitcher />
      </Card>
      <NotificationPrefs user={user} onSaved={setUser} />
      <PasswordCard />
      <PhoneCard user={user} onSaved={setUser} />
      <DeactivateCard onDone={() => { setUser(null); router.push("/"); }} />
    </div>
  );
}

function useSubmit() {
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ tone: "success" | "danger"; text: string } | null>(null);
  const [err, setErr] = useState<ApiError | null>(null);
  const run = async (fn: () => Promise<string>) => {
    setBusy(true);
    setMsg(null);
    setErr(null);
    try {
      setMsg({ tone: "success", text: await fn() });
    } catch (e) {
      if (e instanceof ApiError) setErr(e);
      setMsg({ tone: "danger", text: errorMessage(e) });
    } finally {
      setBusy(false);
    }
  };
  return { busy, msg, err, run };
}

function ProfileCard({ user, onSaved }: { user: Me; onSaved: (u: Me) => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(user.name);
  const [username, setUsername] = useState(user.username);
  const [email, setEmail] = useState(user.email);
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-4 text-lg font-semibold">{t.account.profile}</h2>
      <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); void s.run(async () => {
        const r = await api<{ data: Me }>("/me/profile", { method: "PATCH", body: { name, username, email } });
        onSaved(r.data);
        return r.data.email_verified ? t.account.profileSaved : t.account.profileSavedVerify;
      }); }}>
        <TextInput label={t.account.fullNamePrivate} value={name} onChange={(e) => setName(e.target.value)} required error={s.err?.fieldError("name")} />
        <TextInput label={t.account.publicUsername} value={username} onChange={(e) => setUsername(e.target.value)} required error={s.err?.fieldError("username")} hint={t.account.usernameHint} />
        <TextInput label={t.auth.email} type="email" value={email} onChange={(e) => setEmail(e.target.value)} required error={s.err?.fieldError("email")} className="sm:col-span-2" hint={t.account.emailChangeHint} />
        <div className="sm:col-span-2 space-y-2">{s.msg && <Alert tone={s.msg.tone} live>{s.msg.text}</Alert>}<Button type="submit" loading={s.busy}>{t.account.saveProfile}</Button></div>
      </form>
    </Card>
  );
}

function NotificationPrefs({ user, onSaved }: { user: Me; onSaved: (u: Me) => void }) {
  const { t } = useI18n();
  const [prefs, setPrefs] = useState<Record<string, boolean>>(() => Object.fromEntries(EMAIL_PREFS.map(([k]) => [k, user.notification_preferences.email?.[k] ?? true])));
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-4 text-lg font-semibold">{t.account.emailNotifications}</h2>
      <fieldset className="space-y-2">
        <legend className="sr-only">{t.account.emailMeAbout}</legend>
        {EMAIL_PREFS.map(([k, label]) => <Checkbox key={k} label={t.account[label]} checked={prefs[k]} onChange={(e) => setPrefs({ ...prefs, [k]: e.target.checked })} />)}
      </fieldset>
      <div className="mt-4 space-y-2">
        {s.msg && <Alert tone={s.msg.tone} live>{s.msg.text}</Alert>}
        <Button loading={s.busy} onClick={() => s.run(async () => {
          const r = await api<{ data: Me }>("/me/profile", { method: "PATCH", body: { notification_preferences: { email: prefs } } });
          onSaved(r.data);
          return t.account.prefsSaved;
        })}>{t.account.savePrefs}</Button>
      </div>
    </Card>
  );
}

function PasswordCard() {
  const { t } = useI18n();
  const [current, setCurrent] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-4 text-lg font-semibold">{t.account.password}</h2>
      <form className="grid gap-4 sm:grid-cols-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => {
        const r = await api<{ message: string }>("/auth/password", { method: "PUT", body: { current_password: current, password, password_confirmation: confirm } });
        setCurrent(""); setPassword(""); setConfirm("");
        return r.message;
      }); }}>
        <TextInput label={t.account.currentPassword} type="password" autoComplete="current-password" value={current} onChange={(e) => setCurrent(e.target.value)} required error={s.err?.fieldError("current_password")} />
        <TextInput label={t.account.newPassword} type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={10} error={s.err?.fieldError("password")} />
        <TextInput label={t.account.confirm} type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
        <div className="sm:col-span-3 space-y-2">{s.msg && <Alert tone={s.msg.tone} live>{s.msg.text}</Alert>}<Button type="submit" loading={s.busy}>{t.account.changePassword}</Button></div>
      </form>
    </Card>
  );
}

function PhoneCard({ user, onSaved }: { user: Me; onSaved: (u: Me) => void }) {
  const { t } = useI18n();
  const [phone, setPhone] = useState(user.phone ?? "");
  const [code, setCode] = useState("");
  const [sent, setSent] = useState(false);
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-1 text-lg font-semibold">{t.account.phone}</h2>
      <p className="mb-4 text-sm text-muted">{t.account.phoneLead} {user.phone_verified && <strong>{t.account.phoneVerified(user.phone ?? "")}</strong>}</p>
      {!sent ? (
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => { await api("/me/phone", { method: "POST", body: { phone } }); setSent(true); return t.account.codeSent; }); }}>
          <TextInput label={t.account.phoneLabel} type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} required placeholder="+8801…" />
          <Button type="submit" loading={s.busy}>{t.account.sendCode}</Button>
        </form>
      ) : (
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => { const r = await api<{ data: Me }>("/me/phone/verify", { method: "POST", body: { code } }); onSaved(r.data); setSent(false); return t.account.phoneDone; }); }}>
          <TextInput label={t.account.codeLabel} inputMode="numeric" value={code} onChange={(e) => setCode(e.target.value)} required minLength={6} maxLength={6} />
          <Button type="submit" loading={s.busy}>{t.account.verify}</Button>
        </form>
      )}
      {s.msg && <Alert className="mt-3" tone={s.msg.tone} live>{s.msg.text}</Alert>}
    </Card>
  );
}

function DeactivateCard({ onDone }: { onDone: () => void }) {
  const { t } = useI18n();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState(false);
  const s = useSubmit();
  return (
    <Card as="section" className="border-danger/40">
      <h2 className="mb-1 text-lg font-semibold">{t.account.deactivate}</h2>
      <p className="mb-4 text-sm text-muted">{t.account.deactivateLead}</p>
      <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => { await api("/me/deactivate", { method: "POST", body: { password } }); onDone(); return t.account.deactivated; }); }}>
        <TextInput label={t.account.confirmPassword} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required error={s.err?.fieldError("password")} />
        <Checkbox label={t.account.deactivateConfirm} checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />
        {s.msg?.tone === "danger" && <Alert tone="danger">{s.msg.text}</Alert>}
        <Button type="submit" variant="danger" disabled={!confirm} loading={s.busy}>{t.account.deactivate}</Button>
      </form>
    </Card>
  );
}
