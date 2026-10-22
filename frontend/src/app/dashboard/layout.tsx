import type { Metadata } from "next";
import { DashboardShell } from "@/components/dashboard/DashboardShell";
import { getI18n } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.dashboard, robots: { index: false } };
}

export default function DashboardLayout({ children }: LayoutProps<"/dashboard">) {
  return <DashboardShell>{children}</DashboardShell>;
}
