# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- (List new features for the *next* version here)

### Changed
- (List changes in existing functionality for the *next* version here)

### Fixed
- (List bug fixes for the *next* version here)

## [0.1.51] - YYYY-MM-DD 
*(Replace YYYY-MM-DD with the current date, or the date you consider this version "released")*

### Added
- Initial public release of the Vendor Dashboard plugin.
- **Core Features:**
    - Frontend dashboard for vendors (Overview, Products, Orders, Coupons, Earnings, Profile).
    - Product management (simple products: add/edit with details, images).
    - Order management (view orders, add shipping/tracking for vendor items).
    - Coupon management (vendor-specific coupons).
    - Earnings system (per-item commission calculation, balance, history).
    - Payout settings (PayPal, Stripe Connect preferences).
    - Customizable public vendor store pages (`/partners/vendor-slug/`) with product listings, filtering, sorting, search.
    - Vendor profile customization (banner, avatar, about, policies, social links).
    - Frontend vendor registration with admin approval.
    - Admin panel for plugin settings, commission rate, vendor management (registration, assignment, deletion), pending registration approval, payout recording, and earnings reports (Overall, Vendor Performance, Product Performance, Detailed Log).
- **Shortcodes:** `[vendor_dashboard]`, `[vdb_vendor_list]`, `[vendor_public_store]`.
- **Security:** Implemented nonces, capability checks, input sanitization, output escaping, and prepared statements for DB queries.
- **Localization:** Basic setup with text domain `vendor-dashboard` and `Domain Path: /languages`.

*(Going forward, for each new version, you'll copy the `## [Unreleased]` template, update it to the new version number and date, and then list the specific changes for that version. The `[Unreleased]` section will then be fresh for the *next* set of changes.)*