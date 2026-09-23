import { Badge } from "@/components/ui/Badge";

export function StatusBadge({ status }: { status: string }) {
  const tone = status === "published" ? "success" : status === "draft" ? "neutral" : status === "restricted" || status === "removed" ? "danger" : "warning";
  return <Badge tone={tone}>{status[0].toUpperCase() + status.slice(1)}</Badge>;
}
