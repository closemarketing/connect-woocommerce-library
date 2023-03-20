=== Connect WooCommerce Holded ===
Contributors: closemarketing, davidperez, sacrajaimez, freemius
Tags: holded, woocommerce, connect woocommerce
Donate link: https://close.marketing/go/donate/
Requires at least: 4.0
Requires PHP: 5.6
Tested up to: 6.1

Syncs Products and data from Holded software to WooCommerce.

== Description ==

This plugin allows you to import simple products from Holded to WooCommerce. 

It creates a new menu in WooCommerce > Connect Holded.

You can import simple products, and it will create new products if it does not find the SKU code from your WooCommerce. If the SKU exists, it will import all data except title and description from the product. The stock will be imported as well.

¡We have a Premium version!
These are the features:
- Import categories from Holded.
- Import attributes as brands or others.
- Import variable products.
- Automate the syncronization.
- Send Orders to Holded.
- Send generated Holded Document attached in WooCommerce notifications.
- Option to select the Design of the generated Holded document.
- Import pack products from Holded.

[You could buy it here](https://en.close.technology/connect-woocommerce-holded/)

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your
WordPress installation and then activate the Plugin from Plugins page.

== Developers ==
[Official Repository GitHub](https://github.com/closemarketing/import-holded-products-woocommerce)

== Changelog ==
= 2.1.2 =
*   Fix: Prevents table sync not created.

= 2.1.1 =
*   Fix: Blank filter not working.
*   Fix: Error importing products in database.

= 2.1.0 =
*   New Admin design.
*   Protected folder invoice.
*   Order columns for API.
*   Added option for Categories as attribute.
*   Fix: error message order.
*   Fix: Shipping order cost fixed not implemented in order.
*   Fix: Fatal error no products in automation.
*   Fix: Faster manual sync.
*   Fix: Errors in PHP8.
*   Premium Fix: Shipping order info updated.
*   Premium Fix: Fix not importing variables products.

= 2.0.1 =
*   Fix: Filtered product if empty.
*   Fix: Error rates empty.

= 2.0 =
*   Removed Freemius as engine sell.
*   Removed Support to Easy Digital Downloads.
*   Add Tags as list (separated with commas).
*   Add VAT Info in checkout.
*   Option to Company field in checkout.
*   Premium: Add PDF generated from Holded.
*   Premium: Better sync management WooCommerce Action Scheduler.
*   Premium: Refactoring code from free and fremium.
*   Premium: Select design in document holded.

= 1.4 =
*   Option to not create document if order is free.

= 1.3 =
*   Sync orders to Holded (Premium) automatically and force manually for past orders.
*   Sync Pack products to Holded (Premium).
*   Fix: Attributes duplicated in variation product not imported.
*   Fix: Categories not imported in simple products.

= 1.2 =
*   Automate your syncronization! (Premium).
*   Option email when is finished (Premium).
*   Fix sku saved for EDD.
*   Better metavalue search for SKU.
*   Fix Holded Pagination (thanks to itSerra).
*   Fix SKU variation (thanks to itSerra).

= Earlier versions =

For the changelog of earlier versions, please refer to the separate changelog.txt file.

== Links ==
*	[Closemarketing](https://close.marketing/)
*	[Closemarketing plugins](https://profiles.wordpress.org/closemarketing/#content-plugins)
