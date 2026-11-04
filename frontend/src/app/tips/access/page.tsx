import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { GuestTipThread } from "./GuestTipThread";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.tipReplies, robots: { index: false } };
}

export default async function TipAccessPage() {
  const { t } = await getI18n();
  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.tips.accessTitle}</h1>
      <p className="text-muted">{t.tips.accessLead}</p>
      <GuestTipThread />
    </div>
  );
}
