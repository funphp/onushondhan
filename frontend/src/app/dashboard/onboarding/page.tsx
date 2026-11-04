"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Checkbox, TextArea, TextInput } from "@/components/ui/Field";
import { Markdown } from "@/components/ui/Markdown";
import { useI18n } from "@/i18n/client";
import { api, ApiError, errorMessage } from "@/lib/api";
import type { Agreement } from "@/lib/types";

export default function OnboardingPage() {
  const { user, refresh } = useAuth();
  const { t } = useI18n();
  const router = useRouter();
  const [mode, setMode] = useState<"real_name" | "protected_public_identity">("protected_public_identity");
  const [publicName, setPublicName] = useState("");
  const [bio, setBio] = useState("");
  const [website, setWebsite] = useState("");
  const [legalName, setLegalName] = useState("");
  const [privateEmail, setPrivateEmail] = useState("");
  const [privatePhone, setPrivatePhone] = useState("");
  const [agreement, setAgreement] = useState<Agreement | null>(null);
  const [accept, setAccept] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);

  useEffect(() => {
    if (user?.investigator) router.replace("/dashboard");
    api<{ data: Agreement }>("/agreements/investigator_responsibility").then((r) => setAgreement(r.data)).catch(() => undefined);
  }, [user, router]);

  if (!user) return null;
  if (!user.email_verified) return <Alert tone="warning" title={t.onboarding.verifyFirst}>{t.onboarding.verifyFirstBody} <Link href="/verify-email" className="underline">{t.onboarding.resend}</Link></Alert>;
  const fe = (k: string) => (error instanceof ApiError ? error.fieldError(k) : undefined);

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.onboarding.title}</h1>
      <p className="text-muted">{t.onboarding.lead}</p>
      <form className="space-y-6" onSubmit={async (e) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
          await api("/me/investigator", { method: "POST", body: {
            public_name: publicName, bio: bio || null, website_url: website || null, identity_mode: mode, accept_investigator_agreement: accept,
            private_identity: { legal_or_private_name: legalName || null, private_email: privateEmail || null, private_phone: privatePhone || null },
          } });
          await refresh();
          router.push("/dashboard");
        } catch (err) {
          setError(err instanceof ApiError ? err : errorMessage(err));
        } finally {
          setBusy(false);
        }
      }}>
        <Card as="section">
          <fieldset className="space-y-3">
            <legend className="mb-2 text-lg font-semibold">{t.onboarding.appearance}</legend>
            {([
              ["real_name", t.onboarding.realName, t.onboarding.realNameDesc],
              ["protected_public_identity", t.onboarding.protected, t.onboarding.protectedDesc],
            ] as const).map(([value, label, desc]) => (
              <label key={value} className="flex cursor-pointer gap-3 rounded-md border border-line p-3 has-[:checked]:border-accent">
                <input type="radio" name="mode" value={value} checked={mode === value} onChange={() => setMode(value)} className="mt-1" />
                <span><span className="font-medium">{label}</span><span className="block text-sm text-muted">{desc}</span></span>
              </label>
            ))}
          </fieldset>
          <div className="mt-4 grid gap-4">
            <TextInput label={mode === "real_name" ? t.onboarding.nameReal : t.onboarding.namePseudonym} value={publicName} onChange={(e) => setPublicName(e.target.value)} required minLength={2} maxLength={120} error={fe("public_name")}
              hint={mode === "protected_public_identity" ? t.onboarding.pseudonymHint : undefined} />
            <TextArea label={t.onboarding.bio} value={bio} onChange={(e) => setBio(e.target.value)} maxLength={5000} />
            <TextInput label={t.onboarding.website} type="url" value={website} onChange={(e) => setWebsite(e.target.value)} error={fe("website_url")} />
          </div>
        </Card>

        <Card as="section">
          <h2 className="mb-1 text-lg font-semibold">{t.onboarding.privateTitle}</h2>
          <p className="mb-4 text-sm text-muted">{t.onboarding.privateLead}</p>
          <div className="grid gap-4 sm:grid-cols-3">
            <TextInput label={t.onboarding.legalName} value={legalName} onChange={(e) => setLegalName(e.target.value)} />
            <TextInput label={t.onboarding.privateEmail} type="email" value={privateEmail} onChange={(e) => setPrivateEmail(e.target.value)} error={fe("private_identity.private_email")} />
            <TextInput label={t.onboarding.privatePhone} type="tel" value={privatePhone} onChange={(e) => setPrivatePhone(e.target.value)} />
          </div>
        </Card>

        {agreement && (
          <Card as="section">
            <h2 className="mb-2 text-lg font-semibold">{agreement.title} <span className="text-sm font-normal text-muted">v{agreement.version}</span></h2>
            <div className="max-h-72 overflow-y-auto rounded border border-line p-4" tabIndex={0} aria-label={t.onboarding.agreementText}><Markdown className="prose-report text-base">{agreement.content}</Markdown></div>
            <Checkbox className="mt-4" checked={accept} onChange={(e) => setAccept(e.target.checked)} required error={fe("accept_investigator_agreement")}
              label={t.onboarding.acceptAgreement} />
          </Card>
        )}
        {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
        <Button type="submit" loading={busy}>{t.onboarding.create}</Button>
      </form>
    </div>
  );
}
