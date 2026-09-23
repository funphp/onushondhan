import type { MetadataRoute } from "next";
import { absoluteUrl } from "@/lib/site";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: ["/api/", "/admin", "/dashboard", "/account", "/tips/", "/report", "/login", "/register", "/reset-password", "/verify-email", "/agreements"] }],
    sitemap: absoluteUrl("/sitemap.xml"),
  };
}
