# UPS Shipping for Bagisto

[![CI](https://github.com/ashrafic/bagisto-ups-shipping/actions/workflows/ci.yml/badge.svg)](https://github.com/ashrafic/bagisto-ups-shipping/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Bagisto 2.x](https://img.shields.io/badge/Bagisto-2.x-green.svg)](https://github.com/bagisto/bagisto)

Live UPS rates at checkout, shipping labels, tracking, void, and address
validation for [Bagisto](https://github.com/bagisto/bagisto) 2.x — built on the
modern UPS REST API (OAuth 2 client credentials).

## Features

**Checkout**
- Live UPS rates across all configured services (Ground, 2nd Day Air, Next Day Air, Worldwide, …)
- Negotiated-rate aware (uses your account number when configured)*
- Handling fee: fixed amount or percentage
- Smart packing: cart items packed into weight-capped packages (unit conversion LBS/KGS)
- Rate caching with configurable TTL to protect your API quota
- Error handling that never breaks checkout — the carrier hides itself and logs the cause

**Admin fulfillment**
- Create shipping labels from the order screen (service picker, PDF on a private disk)
- Download and void labels; label cost captured per shipment
- Tracking refresh: status + scan events pulled from the UPS Track API
- "UPS Shipments" admin page with menu and ACL entries (role permissions supported)

**Developer experience**
- Sandbox / production mode toggle — test with sandbox credentials, go live by flipping the mode
- `php artisan ups:test` — smoke-test credentials and print sample rates
- `php artisan ups:validate-address` — validate any address against the UPS database
- 39 automated tests, Pint-clean, CI on PHP 8.3 × Laravel 11/12

\* Negotiated rates require a UPS account number.

## Requirements

- PHP 8.3+
- Bagisto 2.2 or newer
- UPS developer credentials (free at [developer.ups.com](https://developer.ups.com) — sandbox works without a UPS account; **labels require an account number**)

## Installation

```bash
composer require ashrafic/bagisto-ups-shipping
php artisan optimize:clear
```

Package discovery registers everything automatically — no config file edits needed.

## Configuration

Admin → **Configure → Sales → Shipping Methods → UPS Shipping**:

1. Set **Status** to enabled
2. Leave **Mode** on *Sandbox* while testing
3. Paste your **Client ID** and **Client Secret** from the UPS developer portal
4. Enter your **UPS Account Number** (required for labels and negotiated rates)
5. Select the **Allowed Services** you want to offer
6. Save, then verify from the CLI:

```bash
php artisan ups:test --postcode=10001
```

You should see a table of sandbox rates. Switch **Mode** to *Production* and swap in
production credentials when your UPS app is approved for production.

For labels, complete the **Origin** (Configure → Sales → Shipping Settings):
UPS refuses to create labels without the store contact number and full
address — same rule as the customer's phone on the order.

## Notes & limitations

- Rate quotes are assumed to be in the store's base currency; a warning is logged on mismatch.
- Weight comes from product weights; items without weight are not rated.
- Shipping tax follows your store's tax settings (the *Calculate Tax* toggle stores the
  flag on the rate; Bagisto core currently applies shipping tax via tax categories).

## License

[MIT](LICENSE)
