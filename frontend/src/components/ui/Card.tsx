"use client";

import type { ReactNode } from "react";
import { useI18n } from "@/i18n/client";
import { cn } from "./cn";

export function Card({ children, className, as: Tag = "div" }: { children: ReactNode; className?: string; as?: "div" | "section" | "article" | "aside" }) {
  return <Tag className={cn("rounded-lg border border-line bg-surface p-5", className)}>{children}</Tag>;
}

export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="rounded-lg border border-dashed border-line p-8 text-center">
      <p className="font-medium">{title}</p>
      {children && <div className="mt-2 text-sm text-muted">{children}</div>}
    </div>
  );
}

export function Spinner({ label }: { label?: string }) {
  const { t } = useI18n();
  return (
    <div role="status" className="flex items-center gap-2 text-sm text-muted">
      <span aria-hidden className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
      {label ?? t.common.loading}…
    </div>
  );
}
