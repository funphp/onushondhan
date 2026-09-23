import type { Metadata } from "next";
import { TipForm } from "./TipForm";

export const metadata: Metadata = { title: "Send a tip", robots: { index: false } };

export default async function NewTipPage(props: PageProps<"/tips/new">) {
  const sp = await props.searchParams;
  const investigator = typeof sp.investigator === "string" ? sp.investigator : undefined;
  const investigation = typeof sp.investigation === "string" ? sp.investigation : undefined;
  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">Send a confidential tip</h1>
      <TipForm investigator={investigator} investigation={investigation} />
    </div>
  );
}
