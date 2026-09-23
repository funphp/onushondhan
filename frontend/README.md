# Onushondhan — frontend (Next.js 16)

App Router, TypeScript, Tailwind CSS 4. Public pages are server-rendered from the Laravel API
(`API_INTERNAL_URL`); personal state is fetched client-side through the same-origin `/api` proxy
(Sanctum cookie sessions). See the root `README.md` for setup.

- `src/app` — routes (public, auth, `/account`, `/dashboard` investigator workspace)
- `src/components` — UI primitives (`ui/`), investigation/engagement/dashboard components
- `src/lib` — typed API clients (`server-api.ts` for RSC, `api.ts` for the browser), formatting, types
- `npm run dev | build | lint | typecheck | test | test:e2e`
- E2E: start `../scripts/e2e-backend.sh` (API on :8001), then `npx playwright test`.
