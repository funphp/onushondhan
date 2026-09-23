"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Checkbox, TextInput } from "@/components/ui/Field";
import { api, ApiError, errorMessage } from "@/lib/api";
import type { Me } from "@/lib/types";

const EMAIL_PREFS: [string, string][] = [
  ["comments", "Comments on my investigations and replies to me"],
  ["tips", "New tips and tip messages (no tip content is emailed)"],
  ["followed_investigators", "New investigations from investigators I follow"],
  ["moderation", "Moderation and complaint updates"],
];

export default function AccountSettingsPage() {
  const { user, setUser } = useAuth();
  const router = useRouter();
  if (!user) return null;
  return (
    <div className="space-y-6">
      <h1 className="font-serif text-3xl font-bold">Account settings</h1>
      <ProfileCard user={user} onSaved={setUser} />
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
  const [name, setName] = useState(user.name);
  const [username, setUsername] = useState(user.username);
  const [email, setEmail] = useState(user.email);
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-4 text-lg font-semibold">Profile</h2>
      <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); void s.run(async () => {
        const r = await api<{ data: Me }>("/me/profile", { method: "PATCH", body: { name, username, email } });
        onSaved(r.data);
        return r.data.email_verified ? "Profile saved." : "Profile saved. Please verify your new email address.";
      }); }}>
        <TextInput label="Full name (private)" value={name} onChange={(e) => setName(e.target.value)} required error={s.err?.fieldError("name")} />
        <TextInput label="Public username" value={username} onChange={(e) => setUsername(e.target.value)} required error={s.err?.fieldError("username")} hint="Shown next to your comments." />
        <TextInput label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required error={s.err?.fieldError("email")} className="sm:col-span-2" hint="Changing your email requires verifying the new address." />
        <div className="sm:col-span-2 space-y-2">{s.msg && <Alert tone={s.msg.tone} live>{s.msg.text}</Alert>}<Button type="submit" loading={s.busy}>Save profile</Button></div>
      </form>
    </Card>
  );
}

function NotificationPrefs({ user, onSaved }: { user: Me; onSaved: (u: Me) => void }) {
  const [prefs, setPrefs] = useState<Record<string, boolean>>(() => Object.fromEntries(EMAIL_PREFS.map(([k]) => [k, user.notification_preferences.email?.[k] ?? true])));
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-4 text-lg font-semibold">Email notifications</h2>
      <fieldset className="space-y-2">
        <legend className="sr-only">Email me about</legend>
        {EMAIL_PREFS.map(([k, label]) => <Checkbox key={k} label={label} checked={prefs[k]} onChange={(e) => setPrefs({ ...prefs, [k]: e.target.checked })} />)}
      </fieldset>
      <div className="mt-4 space-y-2">
        {s.msg && <Alert tone={s.msg.tone} live>{s.msg.text}</Alert>}
        <Button loading={s.busy} onClick={() => s.run(async () => {
          const r = await api<{ data: Me }>("/me/profile", { method: "PATCH", body: { notification_preferences: { email: prefs } } });
          onSaved(r.data);
          return "Preferences saved.";
        })}>Save preferences</Button>
      </div>
    </Card>
  );
}

function PasswordCard() {
  const [current, setCurrent] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-4 text-lg font-semibold">Password</h2>
      <form className="grid gap-4 sm:grid-cols-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => {
        const r = await api<{ message: string }>("/auth/password", { method: "PUT", body: { current_password: current, password, password_confirmation: confirm } });
        setCurrent(""); setPassword(""); setConfirm("");
        return r.message;
      }); }}>
        <TextInput label="Current password" type="password" autoComplete="current-password" value={current} onChange={(e) => setCurrent(e.target.value)} required error={s.err?.fieldError("current_password")} />
        <TextInput label="New password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={10} error={s.err?.fieldError("password")} />
        <TextInput label="Confirm" type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
        <div className="sm:col-span-3 space-y-2">{s.msg && <Alert tone={s.msg.tone} live>{s.msg.text}</Alert>}<Button type="submit" loading={s.busy}>Change password</Button></div>
      </form>
    </Card>
  );
}

function PhoneCard({ user, onSaved }: { user: Me; onSaved: (u: Me) => void }) {
  const [phone, setPhone] = useState(user.phone ?? "");
  const [code, setCode] = useState("");
  const [sent, setSent] = useState(false);
  const s = useSubmit();
  return (
    <Card as="section">
      <h2 className="mb-1 text-lg font-semibold">Phone verification (optional)</h2>
      <p className="mb-4 text-sm text-muted">Helps protect the community from fake accounts. Your number is never shown publicly. {user.phone_verified && <strong>Verified: {user.phone}</strong>}</p>
      {!sent ? (
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => { await api("/me/phone", { method: "POST", body: { phone } }); setSent(true); return "Code sent."; }); }}>
          <TextInput label="Phone (international format)" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} required placeholder="+8801…" />
          <Button type="submit" loading={s.busy}>Send code</Button>
        </form>
      ) : (
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => { const r = await api<{ data: Me }>("/me/phone/verify", { method: "POST", body: { code } }); onSaved(r.data); setSent(false); return "Phone verified."; }); }}>
          <TextInput label="6-digit code" inputMode="numeric" value={code} onChange={(e) => setCode(e.target.value)} required minLength={6} maxLength={6} />
          <Button type="submit" loading={s.busy}>Verify</Button>
        </form>
      )}
      {s.msg && <Alert className="mt-3" tone={s.msg.tone} live>{s.msg.text}</Alert>}
    </Card>
  );
}

function DeactivateCard({ onDone }: { onDone: () => void }) {
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState(false);
  const s = useSubmit();
  return (
    <Card as="section" className="border-danger/40">
      <h2 className="mb-1 text-lg font-semibold">Deactivate account</h2>
      <p className="mb-4 text-sm text-muted">Signs you out everywhere and disables the account. Published investigations remain subject to the Terms; contact support for data requests.</p>
      <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); void s.run(async () => { await api("/me/deactivate", { method: "POST", body: { password } }); onDone(); return "Account deactivated."; }); }}>
        <TextInput label="Confirm with your password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required error={s.err?.fieldError("password")} />
        <Checkbox label="I understand this will deactivate my account." checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />
        {s.msg?.tone === "danger" && <Alert tone="danger">{s.msg.text}</Alert>}
        <Button type="submit" variant="danger" disabled={!confirm} loading={s.busy}>Deactivate account</Button>
      </form>
    </Card>
  );
}
