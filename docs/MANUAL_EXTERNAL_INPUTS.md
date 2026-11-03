# Manual External Inputs

Everything below genuinely requires a human, an external account or legal authority. Everything
else is implemented, tested and documented.

| # | Input | Why it cannot be automated | Where it goes |
|---|---|---|---|
| 1 | **Production domain + DNS** | Requires control of the domain | `server_name` in `deploy/nginx/onushondhan.conf`; `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `NEXT_PUBLIC_SITE_URL`, `NEXT_PUBLIC_ADMIN_URL` |
| 2 | **Server access** (Linux host, SSH, sudo) | External infrastructure | `docs/DEPLOYMENT.md` §1 |
| 3 | **TLS certificate** | Issued for the real domain | e.g. `sudo certbot certonly --webroot -w /var/www/letsencrypt -d <domain>`; then enable HSTS in the Nginx file |
| 4 | **MySQL credentials** (app user + read-only backup user) | Secrets | `shared/backend.env` (`DB_*`), `/etc/onushondhan/backup.env` |
| 5 | **Application secrets** | Must be generated on the server and stored in your secret manager | `php artisan key:generate --show` → `APP_KEY`; `php -r "echo base64_encode(random_bytes(32));"` → `ANALYTICS_FINGERPRINT_KEY` |
| 6 | **SMTP provider + sender domain verification (SPF/DKIM/DMARC)** | External account | `MAIL_*` in `shared/backend.env` |
| 7 | **Backup encryption key pair + off-site backup target** | The private key must be held offline by a human | `age-keygen -o key.txt` on an offline machine; put the public key in `BACKUP_AGE_RECIPIENT`, set `BACKUP_REMOTE`; then rehearse `scripts/restore.sh` |
| 8 | **Legal review** of Terms of Use, Privacy Policy, Content Policy, Investigator Responsibility Agreement, Contact details | Requires qualified counsel and jurisdiction choices | Edit in admin → *Agreement versions* (publish a reviewed v1.1; users are asked to re-accept) and *CMS pages*; clear "requires legal review" |
| 9 | **First super admin** | A real person must own the account | Person registers and verifies email on the live site, then on the server: `php artisan users:grant-role <their-email> super_admin`; they enrol TOTP on first `/admin` login |
| 10 | **SMS provider** (optional phone verification) | External account | Implement/bind `App\Contracts\SmsSender`, set `SMS_DRIVER` |
| 11 | **ClamAV on the server** | Server package/daemon | `sudo apt install clamav-daemon && sudo systemctl enable --now clamav-daemon clamav-freshclam`; set `MALWARE_SCANNER=clamav`, `EVIDENCE_REQUIRE_CLEAN_SCAN=true` |
| 12 | **Branding assets** — logo done (magnifier with অ + pen-nib handle; `frontend/public/brand/`). Optional: professional refinement, Bangla wordmark artwork | Organisational decision | `frontend/src/components/site/LogoMark.tsx`, `frontend/src/app/{icon.svg,favicon.ico,apple-icon.png,opengraph-image.tsx}`, `frontend/public/brand/` |
| 13 | **Recovery objectives** (RPO/RTO confirmation) | Business decision | `docs/OPERATIONS.md` (initial assumption: RPO 24 h, RTO 4 h) |

## Exact commands once inputs 1–7 exist

```bash
# On the server, as the deploy user
DEPLOY_REPO=git@github.com:<org>/onushondhan.git /var/www/onushondhan/current/scripts/deploy.sh <tag>   # first time: run from a checkout of the repo
cd /var/www/onushondhan/current/backend && php artisan db:seed --force     # reference data only
php artisan users:grant-role <admin-email> super_admin                     # after they register + verify
SITE_URL=https://<domain> /var/www/onushondhan/current/scripts/healthcheck.sh
```
