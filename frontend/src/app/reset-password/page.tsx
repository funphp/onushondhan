import type { Metadata } from "next";
import { ResetForm } from "./ResetForm";

export const metadata: Metadata = { title: "Choose a new password", robots: { index: false } };

export default async function ResetPasswordPage(props: PageProps<"/reset-password">) {
  const sp = await props.searchParams;
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-serif text-3xl font-bold">Choose a new password</h1>
      <ResetForm token={typeof sp.token === "string" ? sp.token : ""} email={typeof sp.email === "string" ? sp.email : ""} />
    </div>
  );
}
