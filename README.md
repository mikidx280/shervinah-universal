# Shervinah Universal

Bilingual English and Persian storefront with PHP checkout, Cardcom hosted payments and private order storage.

## Store management

Read [the Hebrew store management guide](STORE-MANAGEMENT.md) for stock adjustments, prices, product images and adding products without an assistant. The existing admin orders page displays stock and orders; it does not provide a product editor.

Product prices are fixed USD cents in api/commerce-data.php. Shipping uses the Israel Post destination/weight tariff in ILS, a single ILS 10 order discount, and the latest published Bank of Israel USD rate. There is no free-shipping threshold.

## Deployment

The main branch of mikidx280/shervinah-universal deploys to Hostinger. Keep credentials and order storage outside public_html and out of Git. Direct edits in Hostinger can be replaced by the next repository deployment. Production PHP needs cURL and HTTPS. Static-only local servers cannot run checkout.

Inventory is opening stock minus paid and reserved orders. Pending or uncertain payment links retain reservations until reconciled; never delete order records to restore stock.

## Paused merchandise category

Owner instruction, September 20, 2026: hide the Shervinah Universal merchandise category while no real merchandise products are available. It is removed from the shop and homepage shop navigation. Keep the unused category styles and translations available for future restoration. The homepage hamsa card uses the legacy product-merch CSS class; it is an active hamsa product, not the paused merchandise category.

Restore merchandise only when the owner asks to enable it AND there is at least one real, available merchandise product with approved images, descriptions, price, packed weight and stock. Restore its shop section and navigation links in English and Persian, add the actual product, and verify checkout and inventory support. Do not restore an empty category or a coming-soon placeholder.
