import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { VerifyEmail } from "./VerifyEmail";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.verify, robots: { index: false } };
}

export default async function VerifyEmailPage(props: PageProps<"/verify-email">) {
  const { t } = await getI18n();
  const sp = await props.searchParams;
  const s = (k: string) => (typeof sp[k] === "string" ? (sp[k] as string) : "");
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.auth.verifyTitle}</h1>
      <VerifyEmail params={{ id: s("id"), hash: s("hash"), expires: s("expires"), signature: s("signature") }} justSent={s("sent") === "1"} />
    </div>
  );
}
