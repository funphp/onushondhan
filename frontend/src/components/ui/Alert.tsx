import type { ReactNode } from "react";
import { cn } from "./cn";

const tones = {
  info: "border-accent/30 bg-accent-soft text-ink",
  success: "border-success/30 bg-success-soft text-ink",
  warning: "border-warning/30 bg-warning-soft text-ink",
  danger: "border-danger/30 bg-danger-soft text-ink",
};

export function Alert({ tone = "info", title, children, className, live }: { tone?: keyof typeof tones; title?: ReactNode; children?: ReactNode; className?: string; live?: boolean }) {
  return (
    <div role={tone === "danger" ? "alert" : live ? "status" : undefined} className={cn("rounded-md border p-4 text-sm", tones[tone], className)}>
      {title && <p className="mb-1 font-semibold">{title}</p>}
      {children}
    </div>
  );
}
