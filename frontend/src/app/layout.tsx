import type { Metadata, Viewport } from "next";
import { Noto_Sans_Bengali, Noto_Serif_Bengali } from "next/font/google";
import { AuthProvider } from "@/components/auth/AuthProvider";
import { AccountBanners } from "@/components/site/AccountBanners";
import { Footer } from "@/components/site/Footer";
import { Header } from "@/components/site/Header";
import { I18nProvider } from "@/i18n/client";
import { getI18n } from "@/i18n/server";
import { SITE_URL } from "@/lib/site";
import "./globals.css";

// Self-hosted at build time (no runtime requests to Google). Covers Bangla and Latin text.
const sans = Noto_Sans_Bengali({ subsets: ["bengali", "latin"], weight: ["400", "500", "600", "700"], variable: "--font-bn-sans", display: "swap" });
const serif = Noto_Serif_Bengali({ subsets: ["bengali", "latin"], weight: ["400", "700"], variable: "--font-bn-serif", display: "swap" });

export async function generateMetadata(): Promise<Metadata> {
  const { t, locale } = await getI18n();
  return {
    metadataBase: new URL(SITE_URL),
    title: { default: `${t.brand.name} — ${t.brand.tagline}`, template: `%s — ${t.brand.name}` },
    description: t.brand.metaDescription,
    applicationName: t.brand.name,
    openGraph: { type: "website", siteName: t.brand.name, locale: locale === "bn" ? "bn_BD" : "en_GB" },
    twitter: { card: "summary_large_image" },
    alternates: { canonical: "/" },
  };
}

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#fbfaf7" },
    { media: "(prefers-color-scheme: dark)", color: "#121417" },
  ],
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const { locale, t } = await getI18n();
  return (
    <html lang={locale} className={`h-full antialiased ${sans.variable} ${serif.variable}`}>
      <body className="flex min-h-full flex-col">
        <a href="#main" className="skip-link">{t.nav.skip}</a>
        <I18nProvider locale={locale}>
          <AuthProvider>
            <Header />
            <AccountBanners />
            <main id="main" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
              {children}
            </main>
            <Footer />
          </AuthProvider>
        </I18nProvider>
      </body>
    </html>
  );
}
