# Shervinah Universal

English/Persian storefront, PHP checkout and Cardcom hosted payments. See [the Hebrew store management guide](STORE-MANAGEMENT.md).

## Store operation

- `admin/products.php`: add/edit bilingual products, fixed USD prices, packed grams, available stock and images. Archive products without deleting past orders.
- `admin/orders.php`: orders and inventory; individual orders offer packing slips, payment verification, shipment tracking and email retry controls.
- `product.html?id=...`: product details, gallery, stock and available recommendations.
- Shared cart: stay on page after adding, quantities/removal, destination shipping estimate. Guest checkout retains delivery details in sessionStorage until confirmed payment.
- Shipping remains the Israel Post ILS destination/weight tariff less ILS 10 per order, converted with the latest published BOI USD rate. No free shipping threshold.

## Persistence and deployment

Deploy only repository files into `public_html`. The first catalogue request initializes `../shervinah-orders/catalog.json`; later deployments must not overwrite this private directory. Catalogue edits, uploaded images, order snapshots and outbox jobs live there, outside the webroot. Images are served through a filename-validated image endpoint. Back up the entire private directory and private config files.

Inventory is baseline minus paid and reserved orders; admin edits translate available units into the correct baseline under an inventory lock, with stale-editor checks. Never delete ledger files or reuse product IDs. Payment attempts in a session are idempotent and an active attempt blocks a second payment page. Provider results must match terminal, amount, currency and order identity. Historical orders keep their quoted prices.

Requirements: PHP 8.1+, cURL, HTTPS, writable private storage and PHP image metadata support. Default secure cookies and allowed production origins are unchanged. Runtime secrets do not belong in Git. Order record backups are retained before writes; malformed ledger data stops sales for recovery.

## Email and fulfillment setup

PHPMailer 7.1.1 is vendored with its license from its official repository. Configure `private-mail-config.php` ONE directory above `public_html`, following the example. The selected sender is orders@shervinahuniversal.com, using Hostinger Email SMTP over implicit TLS on port 465. Create the mailbox and finish its domain configuration first. In admin/email-settings.php the owner enters its mailbox password privately and tests delivery. Old Gmail credentials are neither reused nor enabled for the new sender. Merchant alerts go to mikidx280@gmail.com and shervinahuniversal@gmail.com. Paid customer confirmations follow EN/FA checkout language. Dispatch emails follow saved tracking information.

Only verified paid orders enqueue confirmations. Outbox keys prevent duplicate normal notifications, and stable Message-IDs aid deduplication. `accepted` means SMTP acceptance, not inbox delivery. Interrupted/uncertain sends require manual review before retry; exactly-once email delivery cannot be guaranteed across an ambiguous SMTP disconnect. `admin/mail-worker.php` is CLI-only and can run via hosting cron to drain configured queued messages. Inspect per-order email state in admin.

Packing slips are internal documents, not Israel Post postage labels or tax invoices. Official postage-label purchasing and Cardcom financial-document generation are not integrated. Abandoned reservations are intentionally held: the inspected LowProfile API does not expose verified cancellation/expiry of existing payment links, so automatic release remains blocked pending provider support. No live charge or inbox delivery has been tested by this change.

## Tests

Run `php tests/shipping-payment.php` and `php tests/products-stock.php` for 70 shipping, payment-matching and inventory checks. The isolated checkout/admin suites add 56 checks. `tests/prepare-fixture.cjs` copies the app to an isolated temporary directory and replaces provider HTTP calls ONLY in that copy. No real payments or emails are made. Serve its `public_html` with PHP at `localhost:8765`, then run `tests/checkout-integration.cjs <fixture-root>` followed by `tests/admin-integration.cjs <fixture-root>`. Never serve the fixture publicly or deploy it. Production files keep strict origins and secure cookies. Test routes/files are blocked by `tests/.htaccess` on Apache.

The fixture changes catalogue and stock deliberately; use a fresh fixture for a complete run. Tested flows include duplicate checkout, forged CSRF, paid verification, private order links, stable stock, hidden products, concurrent admin edits, image uploads, shipment notifications and missing SMTP handling. A real Cardcom test-terminal flow and real mailbox delivery still require setup.

## Paused merchandise category

Keep Shervinah merchandise hidden. Restore only on the owner's request when an actual product has approved images, descriptions, USD price, packed weight and stock. Do not publish an empty category or a coming-soon card.

## Category browsing and dashboard

Shop landing shows one representative product per category; `shop.html?category=jewelry` (or existing category anchors) lists that category. Added-to-cart opens an accessible modal side panel with complementary products and red/gold checkout actions. Order admin defaults to a Hebrew dashboard with payment/fulfillment counts, filters, search, full destinations, item quantities, amounts and tracking.

Automated test fixtures and customer-facing local previews use different temporary directory prefixes. `node tests/prepare-fixture.cjs --preview` produces a clean preview with original products and an explicit local-preview banner. Never run the destructive fixture integration tests against a preview or production.

Hostinger SMTP reference: https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/
