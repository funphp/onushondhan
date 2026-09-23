# Environment & Secrets

## Backend baseline

```dotenv
APP_NAME=Onushondhan
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=onushondhan
DB_USERNAME=
DB_PASSWORD=

LOG_CHANNEL=stack
LOG_LEVEL=warning

QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file
```

Use the exact environment variable names required by the selected Laravel version/packages. Do not assume unsupported variables.

## Frontend
Only expose variables intentionally safe for the browser. Use a public API base URL variable with a `NEXT_PUBLIC_` prefix only when the value is non-secret.

## Secrets
Never commit:
- APP_KEY
- database password
- SMTP credentials
- cloud/API credentials
- OAuth secrets
- webhook signing secrets
- monitoring auth tokens

Use server environment or an approved secret manager.

## Local development
Provide `.env.example` files. Create seed/demo credentials only for local development and clearly mark them as non-production.
