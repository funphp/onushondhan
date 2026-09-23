import type { Metadata } from "next";
import { LoginForm } from "./LoginForm";

export const metadata: Metadata = { title: "Sign in", robots: { index: false } };

export default async function LoginPage(props: PageProps<"/login">) {
  const sp = await props.searchParams;
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-serif text-3xl font-bold">Sign in</h1>
      <LoginForm next={typeof sp.next === "string" ? sp.next : undefined} />
    </div>
  );
}
