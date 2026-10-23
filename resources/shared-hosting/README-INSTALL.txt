POWERNEST ESTATE - INSTALLING ON DIRECTADMIN (NO SSH OR PHP COMMANDS NEEDED)
============================================================================

What is in this zip
  powernest/     The application. It must NOT be inside public_html.
  public_html/   The public web folder (front page, icons, styles, installer).

1. Choose PHP 8.3 (or 8.4)
   DirectAdmin -> Domain Setup (or "Select PHP Version") -> pick PHP 8.3+ for the domain.

2. Create the database
   DirectAdmin -> MySQL Management -> Create new database.
   Write down the database name, user and password (they start with your username).

3. Upload and extract
   DirectAdmin -> File Manager -> open  /domains/YOURDOMAIN.com/
   Upload this zip there and choose "Extract". You should now have:
       /domains/YOURDOMAIN.com/powernest
       /domains/YOURDOMAIN.com/public_html
   If public_html contains DirectAdmin's default "index.html", delete it.
   (Subdomain? Extract next to the subdomain's folder instead, e.g.
    /domains/YOURDOMAIN.com/public_html/estate/ -> put "powernest" in
    /domains/YOURDOMAIN.com/ and the CONTENTS of public_html into .../estate/.)

4. Turn on SSL (recommended before installing)
   DirectAdmin -> SSL Certificates -> Let's Encrypt.

5. Run the installer
   Visit https://YOURDOMAIN.com  - you are sent to the installer automatically.
   Fill in the database details, your admin account and the estate name.
   Payment/electricity keys are optional and can be added later.
   The installer deletes itself when it finishes.

6. Add the cron job (required)
   The final installer screen shows your private cron address. In
   DirectAdmin -> Advanced Features -> Cron Jobs, set every field to *  and use:
       curl -fsS "https://YOURDOMAIN.com/cron/YOUR-TOKEN" >/dev/null 2>&1
   (Your token is also in powernest/.env as CRON_TOKEN.)

Later changes
  Settings live in  powernest/.env  (edit with File Manager -> Edit).
  Real payments and token vending only start when  INTEGRATIONS_LIVE=true.
  Use NEW (rotated) Stron / Paystack / Payaza credentials, not the old ones.

Updating to a new version
  Upload the new zip, extract it over the old files, but keep your existing
  powernest/.env and powernest/storage folder. Then open
  https://YOURDOMAIN.com/cron/YOUR-TOKEN once - new database changes are
  applied automatically.

Troubleshooting
  White page / error 500: check powernest/storage/logs/ for the latest log.
  "powernest folder not found": powernest and public_html are not side by side.
  Start the installer again: delete powernest/storage/installed.lock and
  powernest/.env, then upload public_html/install/ from the zip again.
