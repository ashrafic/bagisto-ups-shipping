# Changelog

All notable changes are documented in this file, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) conventions.

## [0.2.0] - 2026-10-15

### Added

- Shipping labels: create from the admin order screen (Ship API), private-disk PDF storage, download, void with UPS confirmation, label cost capture
- Tracking: refresh status and scan events from the admin (Track API), `ups_shipments` table, "UPS Shipments" admin page with menu and ACL entries
- Address validation: `AddressValidationService` on the UPS XAV API plus the `ups:validate-address` artisan command
- Label button injected into the admin order view (appears when the carrier is active and the order has a shipping address)
- Labels enforce the shipper contact number and account number with actionable error messages

## [Unreleased]

### Added

- UPS carrier for Bagisto 2.x with live rates via the UPS REST API (Rating v1 Shop)
- Sandbox/production mode with OAuth2 client-credential authentication and token caching
- Admin configuration: credentials, allowed services, weight unit, packaging type, handling fee, rate cache TTL, debug logging
- Package packing with store-unit conversion and weight-based splitting
- Negotiated-rate mapping, handling fees (fixed/percent), fingerprinted rate caching
- `ups:test` artisan smoke command
- GitHub Actions CI (Pint + Pest across Laravel 11/12)
