import type { NextConfig } from "next";

/**
 * The browser only ever talks to this origin. /api/* and /sanctum/* are proxied to
 * Laravel (in production Nginx does the same routing), so Sanctum's session and
 * XSRF cookies stay first-party and no CORS is required.
 */
const apiOrigin = (process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const isDev = process.env.NODE_ENV !== "production";

const csp = [
  "default-src 'self'",
  // Next.js inline bootstrap scripts; no third-party scripts are loaded.
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""}`,
  "style-src 'self' 'unsafe-inline'",
  // YouTube thumbnails (i.ytimg.com) and the click-to-play player (privacy-enhanced domain only).
  "img-src 'self' data: blob: https://i.ytimg.com",
  "frame-src https://www.youtube-nocookie.com",
  "media-src 'self' blob:",
  "font-src 'self'",
  "connect-src 'self'",
  "frame-ancestors 'none'",
  "form-action 'self'",
  "base-uri 'self'",
  "object-src 'none'",
].join("; ");

const nextConfig: NextConfig = {
  output: "standalone",
  poweredByHeader: false,
  reactStrictMode: true,
  experimental: {
    // Evidence uploads (up to 100 MB) pass through the /api rewrite proxy, which buffers bodies and
    // silently truncates them at 10 MB by default.
    proxyClientMaxBodySize: "110mb",
  },
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${apiOrigin}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${apiOrigin}/sanctum/:path*` },
    ];
  },
  async redirects() {
    const pages: Record<string, string> = {
      "/about": "about",
      "/how-it-works": "how-it-works",
      "/faq": "faq",
      "/contact": "contact",
      "/terms": "terms-of-use",
      "/privacy": "privacy-policy",
      "/content-policy": "content-policy",
      "/investigator-responsibility": "investigator-responsibility",
    };
    return Object.entries(pages).map(([source, slug]) => ({ source, destination: `/pages/${slug}`, permanent: true }));
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "Content-Security-Policy", value: csp },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), interest-cohort=()" },
        ],
      },
    ];
  },
};

export default nextConfig;
