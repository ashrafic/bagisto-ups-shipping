# UPS Shipping for Bagisto

Live UPS rates at checkout for [Bagisto](https://github.com/bagisto/bagisto) 2.x,
built on the modern UPS REST API (OAuth 2 client credentials).

> **Status:** pre-release (v0.1.x). Rates only — tracking, labels and address
> validation are on the roadmap.

## Features

- Live UPS rates at checkout across all configured services (Ground, 2nd Day Air, Worldwide, …)
- Sandbox / production mode toggle — test with sandbox credentials, go live by flipping the mode
- Negotiated-rate aware (uses your account number when configured)*
- Handling fee: fixed amount or percentage per service
- Smart packing: cart items packed into weight-capped packages (unit conversion LBS/KGS)
- Rate caching with configurable TTL to protect your API quota
- `php artisan ups:test` smoke command to verify credentials from the CLI
- Multi-channel aware configuration, error handling that never breaks checkout

\* Negotiated rates require a UPS account number and are pending live verification.

## Requirements

- PHP 8.3+
- Bagisto 2.2 or newer
- UPS developer credentials (free at [developer.ups.com](https://developer.ups.com) — sandbox works without a UPS account)

## Installation

```bash
composer require ashrafic/bagisto-ups-shipping
```

Package discovery registers everything automatically — no config file edits needed.
Clear caches: `php artisan optimize:clear`.

## Configuration

Admin → **Configure → Sales → Shipping Methods → UPS Shipping**:

1. Set **Status** to enabled
2. Leave **Mode** on *Sandbox* while testing
3. Paste your **Client ID** and **Client Secret** from the UPS developer portal
4. Select the **Allowed Services** you want to offer
5. Save, then verify from the CLI:

```bash
php artisan ups:test --postcode=10001
```

You should see a table of sandbox rates. Switch **Mode** to *Production* and swap in
production credentials when your UPS app is approved for production.

## Notes & limitations

- Rate quotes are assumed to be in the store's base currency; a warning is logged on mismatch.
- Weight comes from product weights; items without weight are not rated.
- Shipping tax follows your store's tax settings (the *Calculate Tax* toggle stores the
  flag on the rate; Bagisto core currently applies shipping tax via tax categories).

## License

[MIT](LICENSE)
