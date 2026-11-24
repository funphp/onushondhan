# Google Analytics 4 and Google AdSense

Both integrations are optional and switched on purely by build-time environment variables. With the
variables empty nothing Google-related is rendered, loaded or allowed by the Content-Security-Policy.

| Variable | Example | Purpose |
|---|---|---|
| `NEXT_PUBLIC_GA_MEASUREMENT_ID` | `G-AB12CD34EF` | GA4 web stream measurement id |
| `NEXT_PUBLIC_ADSENSE_CLIENT` | `ca-pub-1234567890123456` | AdSense publisher id (also drives `/ads.txt` and the `google-adsense-account` meta tag) |
| `NEXT_PUBLIC_ADSENSE_SLOT_DISPLAY` | `1234567890` | Responsive display ad unit (home page, report sidebar) |
| `NEXT_PUBLIC_ADSENSE_SLOT_ARTICLE` | `2345678901` | Unit under a report's body (falls back to the display unit) |

Values are validated against their format; malformed values are ignored. They are public ids, not
secrets, but they are **baked in at build time** — rebuild/redeploy after changing them.

## Deploying (cPanel)

The frontend is built locally by `scripts/deploy-cpanel.sh`, so export the ids in the same command:

```bash
NEXT_PUBLIC_GA_MEASUREMENT_ID=G-… NEXT_PUBLIC_ADSENSE_CLIENT=ca-pub-… NEXT_PUBLIC_ADSENSE_SLOT_DISPLAY=… NEXT_PUBLIC_ADSENSE_SLOT_ARTICLE=… DEPLOY_SSH=… SITE_URL=… ADMIN_URL=… scripts/deploy-cpanel.sh
```

For the systemd/Nginx deployment put them in `shared/frontend.env` (see `deploy/env/frontend.env.example`).

## Behaviour

- **Consent banner** (Bangla/English) appears on first visit when either service is configured; the
  choice is stored for 180 days in the first-party `ons_consent` cookie and can be changed from
  **Cookie settings** in the footer.
- **Google Consent Mode v2**: defaults are `denied` for `ad_storage`, `ad_user_data`,
  `ad_personalization` and `analytics_storage`; accepting updates all four to `granted`.
- **Analytics** is not loaded at all until the visitor accepts. Page views are sent manually on each
  route change with the path only (no query strings or fragments). Google signals and ad
  personalisation signals are disabled in the GA config.
- **AdSense** units are manual, labelled "Advertisement" / "বিজ্ঞাপন", and appear only on the home page and
  on published reports. Without consent Google serves non-personalised ads. The AdSense script is only
  requested on pages that contain a unit.
- **Excluded routes** — no page views and no ad units: `/dashboard`, `/account`, `/tips`, `/login`,
  `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/agreements`, `/report`
  (`frontend/src/lib/third-party.ts`). Tip pages are excluded so sources contacting investigators are
  not observed by third parties.
- `/ads.txt` is generated from the publisher id (404 when AdSense is off).
- Only public data (path, page title) is sent; private investigator identity never appears in either.

## Required dashboard settings

1. **GA4 → Admin → Data streams → Enhanced measurement → Page views → Advanced**: switch off
   *"Page changes based on browser history events"* — the app sends sanitised page views itself, and the
   automatic ones would include query strings and fire on excluded routes.
   In the same panel switch off **Site search** (sends search terms), **Form interactions** (would record
   that a tip or complaint form was started/submitted once Analytics is loaded) and **File downloads**
   (would send evidence download URLs). Scrolls, Outbound clicks and Video engagement can stay on.
2. **GA4 → Admin → Data collection**: leave Google signals off; set data retention as the privacy policy states.
3. **AdSense → Ads → By site**: keep **Auto ads off** (auto ads would place units on any page where the
   script is present, including after in-app navigation to excluded routes). Use the two manual units above.
4. **AdSense → Privacy & messaging**: if the site will be offered to visitors in the EEA, UK or
   Switzerland, Google requires a Google-certified consent platform there. Enable Google's own European
   regulations message (or another certified CMP) and have counsel confirm — the built-in banner is a
   first-party consent mechanism, not a certified CMP.
5. Add the site in AdSense and wait for approval; ad units stay empty until then.

## Policy text

`backend/resources/content/legal/privacy_policy.md` now has a "Cookies, analytics and advertising"
section (template — requires legal review). The seeder never overwrites published versions, so on an
existing installation publish a new Privacy Policy version and update the `privacy-policy` CMS page
through the admin panel after legal review.
