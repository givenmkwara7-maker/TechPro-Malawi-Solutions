# TechMalawi WooCommerce Theme

## Install
1. Copy the `techmalawi` folder to `wp-content/themes/` and activate **TechMalawi** in Appearance → Themes.
2. Install and activate WooCommerce; set currency to **MWK** in WooCommerce → Settings → General.
3. Create product categories with these slugs: `smartphones`, `laptops`, `audio`, and `accessories`.
4. Mark selected products as Featured to populate the home-page deals grid.
5. In WooCommerce → Settings → Payments, enable Direct bank transfer, Cash on delivery, and **Airtel Money / TNM Mpamba** (the included payment-verification simulation). COD is automatically hidden outside Lilongwe when a shipping city is provided. Use a PayChangu gateway plugin when ready for live card payments.
6. In WooCommerce → Settings → Shipping, add **TechMalawi Regional Delivery** to your Malawi shipping zone. It charges MWK 2,000 in Lilongwe, MWK 5,000 in Blantyre/Mzuzu, and MWK 7,500 elsewhere nationwide.
7. Update the placeholder WhatsApp number (`265990000000`) in `functions.php` and `front-page.php`, plus the footer contact details.

WooCommerce supplies the data models and REST endpoints:
- Products: `/wp-json/wc/v3/products`
- Cart (Store API): `/wp-json/wc/store/v1/cart`
- Orders: `/wp-json/wc/v3/orders`

For security, WooCommerce API endpoints require API keys or authenticated sessions; never expose write keys in browser code.
