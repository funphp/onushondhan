import { cn } from "./cn";

export function Avatar({ name, src, size = 40, className }: { name: string; src?: string | null; size?: number; className?: string }) {
  const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase()).join("");
  if (src) {
    // eslint-disable-next-line @next/next/no-img-element -- served from our own API (sanitised rendition)
    return <img src={src} alt="" width={size} height={size} className={cn("rounded-full object-cover", className)} style={{ width: size, height: size }} />;
  }
  return (
    <span aria-hidden className={cn("inline-flex items-center justify-center rounded-full bg-accent-soft font-semibold text-accent", className)} style={{ width: size, height: size, fontSize: size * 0.38 }}>
      {initials || "?"}
    </span>
  );
}
