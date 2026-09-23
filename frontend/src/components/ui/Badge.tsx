import type { ReactNode } from "react";
import { cn } from "./cn";

const tones = {
  neutral: "bg-accent-soft text-ink",
  accent: "bg-accent text-white dark:text-paper",
  success: "bg-success-soft text-success",
  warning: "bg-warning-soft text-warning",
  danger: "bg-danger-soft text-danger",
};

export function Badge({ tone = "neutral", children, className }: { tone?: keyof typeof tones; children: ReactNode; className?: string }) {
  return <span className={cn("inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium", tones[tone], className)}>{children}</span>;
}
