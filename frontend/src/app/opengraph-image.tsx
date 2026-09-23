import { ImageResponse } from "next/og";

export const alt = "Onushondhan — a home for independent investigations";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

export default function Image() {
  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", justifyContent: "center", padding: 80, background: "#fbfaf7", color: "#1b1f24" }}>
        <div style={{ fontSize: 88, fontWeight: 700, letterSpacing: -2 }}>Onushondhan</div>
        <div style={{ fontSize: 40, marginTop: 24, color: "#0f5c63" }}>A home for independent investigations</div>
        <div style={{ fontSize: 26, marginTop: 40, color: "#56606b", maxWidth: 900 }}>Publication is not verification. Investigators are responsible for their own reports.</div>
      </div>
    ),
    size,
  );
}
