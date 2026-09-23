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
import { api, ApiError, errorMessage } from "@/lib/api";
import type { Agreement } from "@/lib/types";

export default function OnboardingPage() {
  const { user, refresh } = useAuth();
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
  if (!user.email_verified) return <Alert tone="warning" title="Verify your email first">Check your inbox for the verification link, then return here. <Link href="/verify-email" className="underline">Resend</Link></Alert>;
  const fe = (k: string) => (error instanceof ApiError ? error.fieldError(k) : undefined);

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">Become an investigator</h1>
      <p className="text-muted">Investigators publish under their own responsibility. Onushondhan hosts and moderates your work but does not verify or endorse it.</p>
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
            <legend className="mb-2 text-lg font-semibold">How do you want to appear publicly?</legend>
            {([
              ["real_name", "Under my real name", "Your reports show your real name."],
              ["protected_public_identity", "Under a protected public identity", "Readers see only the public name you choose. Onushondhan keeps the link to your account and any private details you add, with strictly limited, logged staff access. This is not a guarantee of absolute anonymity (for example, against lawful orders)."],
            ] as const).map(([value, label, desc]) => (
              <label key={value} className="flex cursor-pointer gap-3 rounded-md border border-line p-3 has-[:checked]:border-accent">
                <input type="radio" name="mode" value={value} checked={mode === value} onChange={() => setMode(value)} className="mt-1" />
                <span><span className="font-medium">{label}</span><span className="block text-sm text-muted">{desc}</span></span>
              </label>
            ))}
          </fieldset>
          <div className="mt-4 grid gap-4">
            <TextInput label={mode === "real_name" ? "Your name as it should appear" : "Public name (pseudonym)"} value={publicName} onChange={(e) => setPublicName(e.target.value)} required minLength={2} maxLength={120} error={fe("public_name")}
              hint={mode === "protected_public_identity" ? "Choose a name that does not reveal who you are." : undefined} />
            <TextArea label="Short bio (public)" value={bio} onChange={(e) => setBio(e.target.value)} maxLength={5000} />
            <TextInput label="Website (public, optional)" type="url" value={website} onChange={(e) => setWebsite(e.target.value)} error={fe("website_url")} />
          </div>
        </Card>

        <Card as="section">
          <h2 className="mb-1 text-lg font-semibold">Private identity (optional)</h2>
          <p className="mb-4 text-sm text-muted">Never shown publicly. Encrypted at rest and visible only to you and specifically authorised staff; every staff access is logged.</p>
          <div className="grid gap-4 sm:grid-cols-3">
            <TextInput label="Legal or private name" value={legalName} onChange={(e) => setLegalName(e.target.value)} />
            <TextInput label="Private email" type="email" value={privateEmail} onChange={(e) => setPrivateEmail(e.target.value)} error={fe("private_identity.private_email")} />
            <TextInput label="Private phone" type="tel" value={privatePhone} onChange={(e) => setPrivatePhone(e.target.value)} />
          </div>
        </Card>

        {agreement && (
          <Card as="section">
            <h2 className="mb-2 text-lg font-semibold">{agreement.title} <span className="text-sm font-normal text-muted">v{agreement.version}</span></h2>
            <div className="max-h-72 overflow-y-auto rounded border border-line p-4" tabIndex={0} aria-label="Agreement text"><Markdown className="prose-report text-base">{agreement.content}</Markdown></div>
            <Checkbox className="mt-4" checked={accept} onChange={(e) => setAccept(e.target.checked)} required error={fe("accept_investigator_agreement")}
              label="I have read and accept the Investigator Responsibility Agreement. I understand I am responsible for what I publish." />
          </Card>
        )}
        {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
        <Button type="submit" loading={busy}>Create investigator profile</Button>
      </form>
    </div>
  );
}
