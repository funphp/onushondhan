import { ImageResponse } from "next/og";
import { serverGetOrNull } from "@/lib/server-api";
import type { PublicInvestigation } from "@/lib/types";

export const alt = "Investigation on Onushondhan";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

/** Social card built only from public-safe fields (title, category, public attribution). */
export default async function Image({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const inv = (await serverGetOrNull<{ data: PublicInvestigation }>(`/investigations/${encodeURIComponent(id)}`, { revalidate: 300 }))?.data;
  const readable = inv && (inv.status === "published" || inv.status === "archived");
  const title = readable ? inv.title : "Investigation unavailable";

  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", justifyContent: "space-between", padding: 72, background: "#fbfaf7", color: "#1b1f24" }}>
        <div style={{ display: "flex", fontSize: 30, color: "#0f5c63", fontWeight: 600 }}>{readable && inv.category ? inv.category.name : "Onushondhan"}</div>
        <div style={{ display: "flex", fontSize: title.length > 90 ? 50 : 62, fontWeight: 700, lineHeight: 1.15 }}>{title.slice(0, 160)}</div>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-end", fontSize: 26, color: "#56606b" }}>
          <div style={{ display: "flex", flexDirection: "column" }}>
            {readable && <div style={{ display: "flex", color: "#1b1f24" }}>By {inv.investigator.public_name}</div>}
            <div style={{ display: "flex" }}>Independent report · not verified by Onushondhan</div>
          </div>
          <div style={{ display: "flex", fontWeight: 700, color: "#1b1f24" }}>Onushondhan</div>
        </div>
      </div>
    ),
    size,
  );
}
