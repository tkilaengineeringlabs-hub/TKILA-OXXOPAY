[![PHP](https://img.shields.io/badge/PHP-v8.1-success.svg)](https://www.php.net/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-10.3.0-success.svg)](https://woocommerce.com/)

# OXXO PAY powered by Spin WooCommerce Plugin

## Content

1. [About](#about)
    * [What the project does](#what-the-project-does)
    * [Why it's useful](#why-its-useful)
    * [Technologies used](#technologies-used)
    * [Compatibility](#compatibility)

2. [How to set up the plugin](#how-to-set-up-the-plugin)
    * [Prerequisites](#prerequisites)
    * [Installation](#installation)

3. [License](#license)

## About

### What the project does

The OXXO PAY powered by Spin WooCommerce plugin project is designed to function as a connector between WooCommerce and OXXO. This is an Open Source and free plugin. It bundles functionality to process cash payments securely as well as send email notifications to your customers when they complete a successful purchase.

### Why it's useful

The system facilitates the receipt of cash payments via OXXO and the integration of these payments on WooCommerce. This is useful for companies and users who wish to offer a cash payment option for their WooCommerce shops.

### Technologies used

* [PHP >= 8.1](https://www.php.net/)
* [Femsa PHP library](https://github.com/digitalfemsa/femsa-php)
* [WooCommerce](https://woocommerce.com/)

### Compatibility

This plugin has been tested on:

* WordPress 6.8.3
* WooCommerce 10.3.5

## How to set up the plugin

### Prerequisites
- Install [PHP >= 8.1](https://www.php.net/downloads.php)

### Installation

1. Log in to your WordPress Dashboard.
2. Navigate to Plugins and access the plugins menu from the dashboard.
3. Click on "Add New" and enter "Spin Payment Gateway" in the search field. Hit the "Search Plugins" button to find the desired plugin.
4. Explore the DigitalFemsa plugin details to gather additional information before proceeding.
5. Install the plugin effortlessly by clicking on the "Install Now" button.
6. API Key Configuration:
    * Head to WooCommerce > Settings > Checkout in your WordPress Dashboard.
    * Create your API keys from your DigitalFemsa account at https://panel.digitalfemsa.io
    * Enter the API keys in the designated fields to link your DigitalFemsa account with WooCommerce.
7. Webhook Configuration:
    * To dynamically manage order statuses for offline payments, set up a webhook in your DigitalFemsa account.
    * Add the following URL as a webhook in your DigitalFemsa account: `http://tusitio.com/?wc-api=wc_digitalfemsa`. Replace `tusitio.com` with your domain name.

### License

Developed in Mexico by [DigitalFemsa](https://www.digitalfemsa.com). Available under the [MIT License](LICENSE).
