# GitHub to InfinityFree

The repository uses `.github/workflows/ci-deploy.yml`. Pushes and pull requests
to `main` check PHP 8.3 syntax, verify public routes, and test routing and private
configuration. Pushes to `main` deploy only when `INFINITYFREE_DEPLOY_ENABLED`
is set to `true` and validation succeeds. Deployment starts disabled.

InfinityFree permits GitHub Actions uploads through FTP:
https://forum.infinityfree.com/t/using-github-actions-for-automatic-upload-to-infinityfree/111008/3

## Private settings

Credentials belong in `config/local.php`, which is ignored by Git, or in
environment variables. `config/local.example.php` documents the settings.
The current local credentials were retained privately during preparation.
GitHub does not receive that file, `sendgrid.env`, database exports, customer
profile pictures, or return videos. Database configuration code in `config/db.php`
is now safe to track because it loads private settings instead of containing them.

For another developer's checkout, copy `config/local.example.php` to
`config/local.php`, configure their development database, and use sandbox keys.

## Configure GitHub Actions

Open repository Settings > Secrets and variables > Actions. Create these secrets
at repository scope or in an environment named `infinityfree`:

| Secret | Value |
| --- | --- |
| `FTP_SERVER` | InfinityFree's FTP hostname, from FTP Details |
| `FTP_USERNAME` | FTP account username |
| `FTP_PASSWORD` | FTP account password |
| `DB_HOST` | The hosted database's MySQL hostname |
| `DB_NAME` | The hosted database name |
| `DB_USER` | Hosted database username |
| `DB_PASSWORD` | Hosted database password |
| `PAYPAL_CLIENT_ID` | Production or sandbox PayPal client ID |
| `PAYPAL_CLIENT_SECRET` | Matching PayPal secret |
| `SENDGRID_API_KEY` | Your SendGrid key with mail sending permission |

Create these variables at the same scope:

| Variable | Value |
| --- | --- |
| `FTP_SERVER_DIR` | Exact website directory, ending in `/htdocs/`, usually `/htdocs/` or `/your-domain/htdocs/` |
| `SITE_URL` | Complete HTTPS website URL |
| `MAIL_FROM` | Verified sender email address |
| `MAIL_FROM_NAME` | Sender display name, or leave unset for `NewGrounds` |
| `PAYPAL_API_URL` | `https://api-m.sandbox.paypal.com` or `https://api-m.paypal.com`, matching the keys |

The switch `INFINITYFREE_DEPLOY_ENABLED` must be a **repository-level Actions
variable** because GitHub evaluates the deployment job before loading its
environment. Leave it unset or `false` until first-deployment preparation is done.
FTP details are separate from PayPal, SendGrid, and database credentials.

The deploy job generates a private `config/local.php` from these settings and
uploads it with the application. Secret values are not printed or saved in Git.
The default FTP protocol follows InfinityFree's FTP setup; FTP itself is unencrypted.

## First deployment from the original hosted layout

1. Download a backup of the site's `htdocs` folder and export its database.
2. Compare the hosted login with `app/auth/login.php`. The local login is explicitly
   marked as intentionally vulnerable lab code. Review it before deploying to a
   public site; the workflow does not repair or security-audit that login.
3. Keep the hosted `uploads/` folder. The workflow excludes all uploads and does
   not replace or delete customer files or product images.
4. Test the reorganized application in a separate hosted website directory first.
   Configure `FTP_SERVER_DIR` to that website's `htdocs/` directory.
5. When switching the existing site, remove the original root PHP handlers after
   backup, keeping the new `index.php`. Existing root files bypass the new router.
   Shared root files such as `auth_check.php`, `init.php`, `shipping_logic.php`, and
   `notification_manager.php` have also moved to `includes/`.
6. Set the repository variable `INFINITYFREE_DEPLOY_ENABLED=true`, then run the
   workflow from Actions or push to `main`.
7. Verify login, products, cart, checkout, mail, redirects, and PayPal in sandbox.

Deployment uploads code and private runtime configuration. It does not migrate
the database or remove files from the old layout that were never tracked by the
FTP deployment action. `dangerous-clean-slate` is disabled to retain hosted files.
Keep the FTP action's sync-state file so subsequent deployments can track changes.

Existing keys from an older public commit remain in Git history; if any credentials
were previously published, rotate them rather than relying on file removal.
