import { ImageResponse } from "next/og";

export const alt = "onusondhan.com — a home for independent investigations";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

// public/brand/mark.svg, inlined so the image needs no file access at render time.
const MARK = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NCA2NCIgcm9sZT0iaW1nIiBhcmlhLWxhYmVsPSJPbnVzaG9uZGhhbiI+PGNpcmNsZSBjeD0iMjYuMCIgY3k9IjI2LjAiIHI9IjE4LjUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzBmNWM2MyIgc3Ryb2tlLXdpZHRoPSI1LjUiLz48ZyBmaWxsPSIjMGY1YzYzIj48cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNS40MSAzNC43OCkgc2NhbGUoMC4wMjYwMiAtMC4wMjYwMikiIGQ9Ik03MjUuOCAtMjQgNjU2LjIgMTguMlE2MjUuMyA2Ny44IDU4OC4zIDEwMi4xUTU1MS4zIDEzNi40IDUwNC42IDE2MC41TDUzNC4zIDI0MVE1NjQuMyAyMjIuNCA1OTYuNiAxODhRNjI5IDE1My42IDY1OC44IDkyLjhMNjQ0LjkgNzYuMlY1NTAuM0g2LjNMLTM0LjggNjIySDgwNi45TDg0OS4xIDU1MC4zSDczNC4zVi0yMS45Wk0zNTkuOSA0NlEzMDAuNCA0NiAyNDguOSA2OS42UTE5Ny40IDkzLjIgMTU0LjIgMTQzLjVRMTExLjEgMTkzLjkgNzUuNSAyNzMuN1EzOS45IDM1My42IDExLjggNDY1LjFMNjYuNSA0NzkuMlExMDIuNyAzNjUuOCAxNDYuMSAzMDAuM1ExODkuNSAyMzQuOSAyNDEuMSAyMDcuM1EyOTIuNiAxNzkuOCAzNTIuMSAxNzkuOFE0MTcuNSAxNzkuOCA0NTguNSAyMDcuNVE0OTkuNSAyMzUuMiA0OTcuOCAyOTMuNVE0OTcgMzI3LjkgNDgzLjIgMzQ5LjJRNDY5LjQgMzcwLjUgNDQ5IDM4MC41UTQyOC41IDM5MC41IDQwNi42IDM5MC41UTM5MC4xIDM5MC41IDM3Ni4xIDM4NS4xUTM2MiAzNzkuNyAzNTEuMSAzNzMuOFEzNDYuMSA0MTcuMSAzMjYuMiA0NTQuMVEzMDYuMyA0OTEuMSAyNjUuOCA1MTguOVEyMjUuMiA1NDYuNyAxNTYuOSA1NjEuN0wzMTguMSA1NjMuMVEzNDAgNTQ3LjEgMzYzLjkgNTIwLjFRMzg3LjggNDkzLjIgNDA0LjYgNDU3LjVRNDIxLjQgNDIxLjkgNDIxLjQgMzc4LjhRNDIxLjQgMzMxLjcgMzk0LjcgMzAwLjFRMzY4IDI2OC41IDMxNy41IDI2OC41UTI2NyAyNjguNSAyMzUuMyAyOTkuOVEyMDMuNiAzMzEuMyAyMDMuNiAzNzkuN1EyMDMuNiA0MzUuOCAyNDIuMyA0NjhRMjgwLjkgNTAwLjEgMzQ1LjUgNTAwLjFRNDE4IDUwMC4xIDQ2Ny43IDQ2OS44UTUxNy40IDQzOS40IDU0Mi44IDM4OC45UTU2OC4yIDMzOC40IDU2OC4yIDI3Ni45UTU2OC4yIDIyMC44IDU1MyAxNzcuOFE1MzcuOCAxMzQuOCA1MTAgMTA1LjRRNDgyLjIgNzYgNDQzLjkgNjFRNDA1LjYgNDYgMzU5LjkgNDZaIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDI2LjAgMjYuMCkgcm90YXRlKDQ1KSB0cmFuc2xhdGUoMjAuNzUgMCkiPjxwYXRoIGZpbGw9IiMwZjVjNjMiIGQ9Ik0wIC01LjYgTDkgLTUuNiBRMTQgLTQuNiAyMyAwIFExNCA0LjYgOSA1LjYgTDAgNS42IFoiLz48cGF0aCBzdHJva2U9IiNmYmZhZjciIHN0cm9rZS13aWR0aD0iMS4zIiBzdHJva2UtbGluZWNhcD0icm91bmQiIGQ9Ik0xMi41IDAgTDIxIDAiLz48Y2lyY2xlIGZpbGw9IiNmYmZhZjciIGN4PSIxMSIgY3k9IjAiIHI9IjEuNiIvPjwvZz48L3N2Zz4K";

export default function Image() {
  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", alignItems: "center", padding: 80, background: "#fbfaf7", color: "#1b1f24" }}>
        <img src={MARK} width={260} height={260} alt="" style={{ marginRight: 64 }} />
        <div style={{ display: "flex", flexDirection: "column", maxWidth: 720 }}>
          <div style={{ fontSize: 84, fontWeight: 700, letterSpacing: -2 }}>onusondhan.com</div>
          <div style={{ fontSize: 38, marginTop: 20, color: "#0f5c63" }}>A home for independent investigations</div>
          <div style={{ fontSize: 24, marginTop: 36, color: "#56606b" }}>Publication is not verification. Investigators are responsible for their own reports.</div>
        </div>
      </div>
    ),
    size,
  );
}
