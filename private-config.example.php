<?php
/**
 * Template for the real config file the app expects.
 *
 * IMPORTANT — where this file actually goes:
 *   admin/index.php and api/submit.php both look for it at
 *   dirname(__DIR__, 2) . '/private-config.php' — that is TWO levels above
 *   this project's folder, i.e. OUTSIDE the site's own directory and
 *   therefore outside the web root. That is intentional: it keeps database
 *   credentials and the admin password hash unreachable from a browser even
 *   if something is misconfigured on the server.
 *
 * Setup:
 *   1. Copy this file to: <parent-of-parent-folder>/private-config.php
 *      (NOT inside this project folder, and never commit the real file —
 *      it's already covered by .gitignore).
 *   2. Fill in every value below with the real ones for this environment.
 *   3. Generate admin_password_hash by running this once, anywhere PHP is
 *      available (php -a, or a throwaway local script), and pasting the
 *      output string in below:
 *        echo password_hash('choose-a-strong-admin-password', PASSWORD_DEFAULT);
 *   4. Generate ip_salt with any long random string, e.g.:
 *        echo bin2hex(random_bytes(32));
 *   5. Run schema.sql against the database before testing any form.
 */

return [
    // MySQL/MariaDB connection (from hPanel → Databases)
    'db_host' => 'localhost',
    'db_name' => 'REPLACE_WITH_DATABASE_NAME',
    'db_user' => 'REPLACE_WITH_DATABASE_USER',
    'db_pass' => 'REPLACE_WITH_DATABASE_PASSWORD',

    // Output of password_hash() for the admin dashboard login — see step 3 above.
    // Never store the plain-text password here.
    'admin_password_hash' => 'REPLACE_WITH_PASSWORD_HASH_OUTPUT',

    // Long random string used to HMAC-hash submitter IPs before storing them
    // (see ip_hash in schema.sql) — see step 4 above.
    'ip_salt' => 'REPLACE_WITH_LONG_RANDOM_STRING',

    // Where new-inquiry notification emails are sent (api/submit.php)
    'notification_email' => 'shervinahuniversal@gmail.com',
];
