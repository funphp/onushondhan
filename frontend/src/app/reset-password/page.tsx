import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { ResetForm } from "./ResetForm";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.reset, robots: { index: false } };
}

export default async function ResetPasswordPage(props: PageProps<"/reset-password">) {
  const { t } = await getI18n();
  const sp = await props.searchParams;
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.auth.resetTitle}</h1>
      <ResetForm token={typeof sp.token === "string" ? sp.token : ""} email={typeof sp.email === "string" ? sp.email : ""} />
    </div>
  );
}
