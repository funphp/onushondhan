import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { LoginForm } from "./LoginForm";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.signIn, robots: { index: false } };
}

export default async function LoginPage(props: PageProps<"/login">) {
  const { t } = await getI18n();
  const sp = await props.searchParams;
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-serif text-3xl font-bold">{t.auth.signInTitle}</h1>
      <LoginForm next={typeof sp.next === "string" ? sp.next : undefined} />
    </div>
  );
}
