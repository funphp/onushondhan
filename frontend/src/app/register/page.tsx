import type { Metadata } from "next";
import { getI18n } from "@/i18n/server";
import { RegisterForm } from "./RegisterForm";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.register, robots: { index: false } };
}

export default async function RegisterPage() {
  const { t } = await getI18n();
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-display text-3xl font-bold">{t.auth.registerTitle}</h1>
      <RegisterForm />
    </div>
  );
}
