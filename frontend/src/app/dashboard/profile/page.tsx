"use client";

import { useEffect, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { Card, Spinner } from "@/components/ui/Card";
import { SelectInput, TextArea, TextInput } from "@/components/ui/Field";
import { useI18n } from "@/i18n/client";
import { api, ApiError, errorMessage } from "@/lib/api";
import type { InvestigatorProfile } from "@/lib/types";

type Msg = { tone: "success" | "danger"; text: string } | null;

export default function ProfilePage() {
  const { refresh } = useAuth();
  const { t } = useI18n();
  const [p, setP] = useState<InvestigatorProfile | null>(null);
  const [form, setForm] = useState({ public_name: "", bio: "", website_url: "", identity_mode: "protected_public_identity" });
  const [priv, setPriv] = useState({ legal_or_private_name: "", private_email: "", private_phone: "", notes: "" });
  const [privNotice, setPrivNotice] = useState("");
  const [msg, setMsg] = useState<Msg>(null);
  const [privMsg, setPrivMsg] = useState<Msg>(null);
  const [avatarMsg, setAvatarMsg] = useState<Msg>(null);
  const [err, setErr] = useState<ApiError | null>(null);

  useEffect(() => {
    api<{ data: InvestigatorProfile }>("/me/investigator").then((r) => {
      setP(r.data);
      setForm({ public_name: r.data.public_name, bio: r.data.bio ?? "", website_url: r.data.website_url ?? "", identity_mode: r.data.identity_mode });
    });
    api<{ data: Record<string, string | null> }>("/me/investigator/private-identity").then((r) => {
      setPriv({ legal_or_private_name: r.data.legal_or_private_name ?? "", private_email: r.data.private_email ?? "", private_phone: r.data.private_phone ?? "", notes: r.data.notes ?? "" });
      setPrivNotice(r.data.notice ?? "");
    });
  }, []);

  if (!p) return <Spinner />;
  const nameChanging = form.public_name !== p.public_name;

  return (
    <div className="space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.profile.title}</h1>
      <Card as="section">
        <h2 className="mb-4 text-lg font-semibold">{t.profile.publicProfile}</h2>
        <form className="grid gap-4" onSubmit={async (e) => {
          e.preventDefault();
          setMsg(null);
          setErr(null);
          try {
            const r = await api<{ data: InvestigatorProfile }>("/me/investigator", { method: "PATCH", body: { ...form, bio: form.bio || null, website_url: form.website_url || null } });
            setP(r.data);
            await refresh();
            setMsg({ tone: "success", text: t.profile.saved });
          } catch (x) {
            if (x instanceof ApiError) setErr(x);
            setMsg({ tone: "danger", text: errorMessage(x) });
          }
        }}>
          <SelectInput label={t.profile.identityMode} value={form.identity_mode} onChange={(e) => setForm({ ...form, identity_mode: e.target.value })} options={[
            { value: "real_name", label: t.profile.realName }, { value: "protected_public_identity", label: t.profile.protected },
          ]} hint={t.profile.identityModeHint} />
          <TextInput label={t.profile.publicName} value={form.public_name} onChange={(e) => setForm({ ...form, public_name: e.target.value })} required error={err?.fieldError("public_name")}
            hint={nameChanging ? t.profile.nameChangeHint : undefined} />
          <TextArea label={t.profile.bio} value={form.bio} onChange={(e) => setForm({ ...form, bio: e.target.value })} maxLength={5000} />
          <TextInput label={t.profile.website} type="url" value={form.website_url} onChange={(e) => setForm({ ...form, website_url: e.target.value })} error={err?.fieldError("website_url")} />
          {msg && <Alert tone={msg.tone} live>{msg.text}</Alert>}
          <div><Button type="submit">{t.profile.saveProfile}</Button></div>
        </form>
      </Card>

      <Card as="section">
        <h2 className="mb-4 text-lg font-semibold">{t.profile.avatar}</h2>
        <div className="flex items-center gap-4">
          <Avatar name={p.public_name} src={p.avatar_url} size={72} />
          <div className="space-y-2">
            <label htmlFor="avatar" className="block text-sm font-medium">{t.profile.avatarUpload}</label>
            <input id="avatar" type="file" accept="image/jpeg,image/png,image/webp" className="text-sm" onChange={async (e) => {
              const file = e.target.files?.[0];
              if (!file) return;
              const fd = new FormData();
              fd.append("avatar", file);
              try {
                const r = await api<{ data: InvestigatorProfile }>("/me/investigator/avatar", { method: "POST", body: fd });
                setP(r.data);
                setAvatarMsg({ tone: "success", text: t.profile.avatarDone });
              } catch (x) {
                setAvatarMsg({ tone: "danger", text: errorMessage(x) });
              }
            }} />
            {form.identity_mode === "protected_public_identity" && <p className="text-xs text-muted">{t.profile.avatarProtectedHint}</p>}
          </div>
        </div>
        {avatarMsg && <Alert className="mt-3" tone={avatarMsg.tone} live>{avatarMsg.text}</Alert>}
      </Card>

      <Card as="section">
        <h2 className="mb-1 text-lg font-semibold">{t.profile.privateTitle}</h2>
        <p className="mb-4 text-sm text-muted">{privNotice}</p>
        <form className="grid gap-4 sm:grid-cols-2" onSubmit={async (e) => {
          e.preventDefault();
          try {
            await api("/me/investigator/private-identity", { method: "PUT", body: Object.fromEntries(Object.entries(priv).map(([k, v]) => [k, v || null])) });
            setPrivMsg({ tone: "success", text: t.profile.privateSaved });
          } catch (x) {
            setPrivMsg({ tone: "danger", text: errorMessage(x) });
          }
        }}>
          <TextInput label={t.onboarding.legalName} value={priv.legal_or_private_name} onChange={(e) => setPriv({ ...priv, legal_or_private_name: e.target.value })} />
          <TextInput label={t.onboarding.privateEmail} type="email" value={priv.private_email} onChange={(e) => setPriv({ ...priv, private_email: e.target.value })} />
          <TextInput label={t.onboarding.privatePhone} type="tel" value={priv.private_phone} onChange={(e) => setPriv({ ...priv, private_phone: e.target.value })} />
          <TextArea label={t.profile.notes} value={priv.notes} onChange={(e) => setPriv({ ...priv, notes: e.target.value })} maxLength={2000} className="sm:col-span-2" />
          {privMsg && <Alert className="sm:col-span-2" tone={privMsg.tone} live>{privMsg.text}</Alert>}
          <div><Button type="submit">{t.profile.savePrivate}</Button></div>
        </form>
      </Card>
    </div>
  );
}
