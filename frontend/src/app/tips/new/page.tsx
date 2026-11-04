import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { TipForm } from "./TipForm";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.tip, robots: { index: false } };
}

export default async function NewTipPage(props: PageProps<"/tips/new">) {
  const { t } = await getI18n();
  const sp = await props.searchParams;
  const investigator = typeof sp.investigator === "string" ? sp.investigator : undefined;
  const investigation = typeof sp.investigation === "string" ? sp.investigation : undefined;
  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.tips.newTitle}</h1>
      <TipForm investigator={investigator} investigation={investigation} />
    </div>
  );
}
