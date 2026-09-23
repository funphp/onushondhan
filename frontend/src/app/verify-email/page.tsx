import type { Metadata } from "next";
import { VerifyEmail } from "./VerifyEmail";

export const metadata: Metadata = { title: "Verify your email", robots: { index: false } };

export default async function VerifyEmailPage(props: PageProps<"/verify-email">) {
  const sp = await props.searchParams;
  const s = (k: string) => (typeof sp[k] === "string" ? (sp[k] as string) : "");
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-serif text-3xl font-bold">Verify your email</h1>
      <VerifyEmail params={{ id: s("id"), hash: s("hash"), expires: s("expires"), signature: s("signature") }} justSent={s("sent") === "1"} />
    </div>
  );
}
