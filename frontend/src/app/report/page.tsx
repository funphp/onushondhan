import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { ReportForm } from "./ReportForm";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.report, robots: { index: false } };
}

export default async function ReportPage(props: PageProps<"/report">) {
  const { t } = await getI18n();
  const sp = await props.searchParams;
  const pick = (k: string) => (typeof sp[k] === "string" ? (sp[k] as string) : undefined);
  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.report.title}</h1>
      <ReportForm investigation={pick("investigation")} comment={pick("comment")} evidence={pick("evidence")} />
    </div>
  );
}
