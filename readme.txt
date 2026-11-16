=== OXXO PAY powered by Spin ===
Contributors: fcarrero, perafan18, urielperezb
Tags: free, cash, mexico, payment gateway
Requires at least:1.0.12
Tested up to: 6.8.5
Requires PHP: 8.1
Stable tag:1.0.12
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

WooCommerce Payment Gateway for DigitalFemsa.io: Securely process cash payments and send email notifications for successful purchases.

== Description ==

OXXO Pay powered by Spin - Online Cash Payment Provider

This extension enables your customers in Mexico to conveniently pay for their purchases in cash at any of the 25,000 OXXO stores. By integrating this extension, you can offer an alternative payment method that caters to customers who prefer or need to pay with cash. This can expand your customer base and improve your conversion rates by providing more payment options.
How It Works: Once installed and configured, customers selecting OXXO Pay as their payment method at checkout will receive a unique reference number. They can then visit any OXXO store to complete their payment in cash. Upon payment, the order status in your Magento store will be updated automatically in real time, ensuring a seamless transaction experience for both the merchant and the customer.

= UI/UX Changes: =
* Adds an OXXO Pay payment option at the checkout page.
* Displays payment instructions and the reference number on the order confirmation page and in the confirmation email.

Customizations: VALIDAR QUE SON ESTAS CUSTOMIZACIONES Y PONER LAS QUE APLIQUEN EN CADA PLUGIN.

= Customizable payment instructions. =
*Option to set a specific order status for pending OXXO payments.

= Use Cases: =
* Ideal for merchants targeting the Mexican ecommerce market.
* Perfect for customers who prefer or need to pay with cash.
* Useful for increasing sales by providing more payment options.

= Account & Pricing: =
* Requires an account at OXXO Pay Panel before installing the extension.
* Merchant discount rate charges for the use of the extension is 3.9% + IVA.
*User service fee $17 MXN in OXXO Store.

== Features ==
This extension allows your customers to pay in cash at OXXO stores in Mexico.
Easy Integration: Simple setup process to start accepting cash payments via OXXO Pay.
Real-Time Payment Updates: Automatic order status updates upon payment confirmation.
User-Friendly Experience: Clear payment instructions provided to customers.
Customizable Settings: Flexibility to adjust payment instructions and order statuses

== Security & PCI Compliance ==
This extension does not require PCI certification because it does not use customer card data.

Current version features:

* Unified API Key Integration: Streamlines the integration procedure for all existing payment modalities under one cohesive set of API Keys.
* Refined Checkout Workflow: Enhances the user experience by consolidating checkout stages. Incorporates a robust checkpoint system to streamline transactions
* Automatic order status management
* Email notifications on successful purchase

== Installation ==
Before initiating the installation process, please ensure that your WooCommerce version is 6.x, and if necessary, download an older version to match your requirements. Additionally, confirm that your server runs PHP Version 8.1 or above, as the DigitalFemsa PHP library mandates this version.
Follow these steps for a seamless installation

* WordPress Dashboard Login, Log in to your WordPress Dashboard.
* Navigate to Plugins, Access the plugins menu from the dashboard.
* Search and Locate, Click on "Add New" and enter "Spin" in the search field. Hit the "Search Plugins" button to find the desired plugin.
* Plugin Details, Explore the DigitalFemsa plugin details to gather additional information before proceeding.
* Installation, Install the plugin effortlessly by clicking on the "Install Now" button.
* API Key Configuration, Head to Woocommerce > Settings > Checkout in your WordPress Dashboard. Create your API keys from your DigitalFemsa account at https://panel.digitalfemsa.io, Enter the API keys in the designated fields to link your DigitalFemsa account with WooCommerce.
* Webhook Configuration, To dynamically manage order statuses for offline payments, set up a webhook in your DigitalFemsa account, Add the following URL as a webhook in your DigitalFemsa account: http://tusitio.com/?wc-api=wc_spin, Replace to tusitio.com with your domain name
* For a detailed tutorial on how to setup the plugin, follow the instructions in the following documentation: [https://developers.digitalfemsa.io/docs/woocommerce-1](https://developers.digitalfemsa.io/docs/woocommerce-1)

By following these steps, you'll successfully install and configure the Spin Payment Gateway plugin, ensuring a smooth integration with your WooCommerce store.

== External Service Integration ==

This plugin relies on the following third-party services to function properly:

= DigitalFemsa API (api.digitalfemsa.io) =

The plugin connects to the DigitalFemsa API to create payment orders.

- Link to Service: [https://api.digitalfemsa.io](https://api.digitalfemsa.io)
- Terms of Use: [https://www.spinnegocios.com/terminos-y-condiciones-oxxopay](https://www.spinnegocios.com/terminos-y-condiciones-oxxopay)
- Privacy Policy: [https://www.spinnegocios.com/avisodeprivacidad-oxxopay](https://www.spinnegocios.com/avisodeprivacidad-oxxopay)

= DigitalFemsa Assets (assets.digitalfemsa.io) =

The plugin connects to the DigitalFemsa Assets service to obtain icons.

- Link to Service: [https://assets.digitalfemsa.io](https://assets.digitalfemsa.io)
- Terms of Use: [https://www.spinnegocios.com/terminos-y-condiciones-oxxopay](https://www.spinnegocios.com/terminos-y-condiciones-oxxopay)
- Privacy Policy: [https://www.spinnegocios.com/avisodeprivacidad-oxxopay](https://www.spinnegocios.com/avisodeprivacidad-oxxopay)

To obtain support about issues with the plugin, please contact us at [ayudaoxxopay@digitalfemsa.com](ayudaoxxopay@digitalfemsa.com)

== Changelog ==

= 1.0.11 =
* Update SDK version and dependencies in package file.

= 1.0.10 =
* Update plugin description.

= 1.0.9 =
* Update user agent metadata.

= 1.0.8 =
* Update plugin metadata, name, logos and the readme file.

= 1.0.7 =
* Update dependencies in package file.

= 1.0.6 =
* Fixed bug on extra fees added to WC Order not being applied on the total.
* Update dependencies in composer file.

= 1.0.2 =
* add alternative url option

= 1.0.1 =
* Add assets images

= 1.0.0 =
* CRD-195 Remove Conekta by @Perafan18 in #2
* Fix some plugin errors and warnings by @Perafan18 in #3
* [CRD-267] Change Name, URL and email by @Perafan18 in #4
* CRD-348 config internacionalizacion by @fcarrero in #5
* [CRD-347] fixes by @Perafan18 in #6
* Update README.md by @sebastianreh in #7