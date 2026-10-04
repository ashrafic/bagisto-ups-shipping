# Changelog

All notable changes are documented in this file, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) conventions.

## [Unreleased]

### Added

- UPS carrier for Bagisto 2.x with live rates via the UPS REST API (Rating v1 Shop)
- Sandbox/production mode with OAuth2 client-credential authentication and token caching
- Admin configuration: credentials, allowed services, weight unit, packaging type, handling fee, rate cache TTL, debug logging
- Package packing with store-unit conversion and weight-based splitting
- Negotiated-rate mapping, handling fees (fixed/percent), fingerprinted rate caching
- `ups:test` artisan smoke command
- GitHub Actions CI (Pint + Pest across Laravel 11/12)
