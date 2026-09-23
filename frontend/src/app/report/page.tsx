import type { Metadata } from "next";
import { ReportForm } from "./ReportForm";

export const metadata: Metadata = { title: "Report content", robots: { index: false } };

export default async function ReportPage(props: PageProps<"/report">) {
  const sp = await props.searchParams;
  const pick = (k: string) => (typeof sp[k] === "string" ? (sp[k] as string) : undefined);
  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">Report content</h1>
      <ReportForm investigation={pick("investigation")} comment={pick("comment")} evidence={pick("evidence")} />
    </div>
  );
}
