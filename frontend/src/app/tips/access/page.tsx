import type { Metadata } from "next";
import { GuestTipThread } from "./GuestTipThread";

export const metadata: Metadata = { title: "Tip replies", robots: { index: false } };

export default function TipAccessPage() {
  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">Read replies to your tip</h1>
      <p className="text-muted">Enter the access code you received when you sent your tip as a guest.</p>
      <GuestTipThread />
    </div>
  );
}
