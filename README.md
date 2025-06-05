# Vendor Dashboard for WooCommerce

**Plugin Name:** Vendor Dashboard
**Author:** Generic
**Requires at least:** WordPress 5.8
**Requires PHP:** 7.4
**WC requires at least:** 6.0
**WC tested up to:** 8.1
**License:** GPL v2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Provides a simple yet powerful frontend dashboard for WooCommerce vendors to manage their products, orders, coupons, earnings, and public store presence.

## Description

The Vendor Dashboard plugin empowers your WooCommerce store by allowing registered vendors to manage their own inventory, track sales, handle their portion of orders, create coupons, and customize their public-facing store page. This plugin is designed to provide a streamlined experience for vendors directly from the frontend, reducing the need for WordPress admin access for routine tasks.

It is primarily focused on vendors selling **physical, simple products**.

Admins retain full control, including vendor registration approval, commission rate settings, and oversight of all vendor activities.

## Key Features

*   **Frontend Vendor Dashboard:**
    *   **Overview:** At-a-glance summary of sales, orders, product statuses, and notifications.
    *   **Product Management:** Add, edit, and manage **simple products**. Includes support for name, description, short description, SKU, regular price, sale price, stock quantity, weight, dimensions, shipping class, product category, and tags. (Note: Currently optimized for simple, physical products).
    *   **Image Management:** Upload featured images and gallery images for products.
    *   **Order Management:** View orders containing their products. Add shipping provider and tracking number for each of their items in an order.
    *   **Coupon Management:** Vendors can create and manage their own coupons (percentage, fixed cart, fixed product discounts) applicable only to their products.
    *   **Earnings & Payouts:** View detailed earnings statements, current balance, and payout history. Set preferred payout methods (PayPal, Stripe Connect).
    *   **Profile Settings:** Manage account email, brand name, brand logo (for dashboard & emails), public store avatar/logo, store banner, "About Us" content, shipping & return policies, public contact information (phone, email display), and social media links.
    *   **Notifications:** Receive notifications for new orders, product approvals, and payouts.
*   **Public Vendor Stores:**
    *   Each vendor gets a customizable public store page (`/partners/vendor-slug/`).
    *   Displays vendor banner, avatar/logo, brand name, contact info, social links, "About Us," and policies.
    *   Lists vendor's products with WooCommerce's native loop.
    *   Includes product filtering by category, sorting options (newest, price, popularity, rating, name), and search within the vendor's store.
    *   Sidebar with vendor's product categories.
*   **Vendor Registration:**
    *   Frontend registration form for aspiring vendors.
    *   Admin approval required for new vendor registrations.
    *   Email notifications to admin for pending approvals and to applicants about their status.
*   **Admin Management:**
    *   Dedicated admin page to manage plugin settings.
    *   Set a global commission rate (applies to all vendors).
    *   Register new vendors directly from the admin area.
    *   Manage existing vendors: view products, assign products by ID, delete vendors.
    *   Admin page to approve/deny pending vendor registrations.
    *   Admin page for managing vendor payouts and viewing payout history.
    *   Admin earnings reports: Overall summary, vendor performance, product performance, and detailed earnings log with date filtering.
*   **Commission System:**
    *   Calculates vendor earnings and platform commissions on a per-item basis when orders are processed/completed.
    *   Earnings are logged and made available to vendors.
*   **Shortcodes:**
    *   `[vendor_dashboard]`: Renders the main vendor login/registration and dashboard interface.
    *   `[vdb_vendor_list]`: Displays a list of all public vendor stores.
    *   `[vendor_public_store vendor_slug="your-vendor-slug"]`: Displays a link/button to a specific vendor's store.

## Installation

1.  **Download:** Download the plugin `.zip` file. *(From GitHub, download the latest release `.zip` file from the [Releases page](LINK_TO_YOUR_GITHUB_RELEASES_PAGE_HERE_LATER).)*
2.  **Upload:** In your WordPress admin panel, go to **Plugins > Add New** and click **Upload Plugin**.
3.  **Choose File:** Select the downloaded `.zip` file and click **Install Now**.
4.  **Activate:** Once installed, click **Activate Plugin**.
5.  **Setup Pages:**
    *   Upon activation, the plugin attempts to create two pages:
        *   "Vendor Dashboard" (slug: `vendordashboard`) with the shortcode `[vendor_dashboard]`
        *   "Vendor Stores" (slug: `vendorpublicstore`) with the shortcode `[vdb_vendor_list]`
    *   Verify these pages were created and their content. If not, create them manually and add the respective shortcodes. The Vendor Dashboard page is essential for vendors to log in and manage their accounts.
6.  **Configure Settings:**
    *   Navigate to **Vendor Admin > Settings** in your WordPress admin menu.
    *   Set your desired **Vendor Commission Rate (%)**. This is a global rate.
    *   Review other settings as needed.
7.  **Flush Rewrite Rules:**
    *   Go to **Settings > Permalinks** in your WordPress admin and simply click **Save Changes** once (without making any changes). This ensures the public store URL structure (`/partners/vendor-slug/`) works correctly.

## Usage

### For Vendors

1.  **Registration:** New vendors can register via the form on the page containing the `[vendor_dashboard]` shortcode (if they are not logged in and choose the "Register" option). Their application will be sent to the site admin for approval.
2.  **Login:** Approved vendors can log in using the form on the page with the `[vendor_dashboard]` shortcode.
3.  **Dashboard Navigation:** Once logged in, vendors will see their dashboard with sections for Overview, Products, Orders, Coupons, Earnings, and Profile Settings.
4.  **Managing Products:**
    *   Go to the "Products" tab.
    *   Click "Add New Product" or "Edit" an existing product.
    *   Fill in the product details. Note: The editor is currently designed for **simple, physical products**.
    *   New products are saved as "Draft" and require admin review and approval before they are published on the site.
5.  **Managing Orders:**
    *   Go to the "Orders" tab to see orders containing their items.
    *   For "Processing" or "On-Hold" orders, vendors can add shipping provider, tracking number, and date shipped for their items.
    *   Saving shipping information may update the order status to "Completed" if all shippable items in the order are tracked.
6.  **Managing Coupons:**
    *   Go to the "Coupons" tab.
    *   Click "Add New Coupon" or "Edit" an existing one.
    *   Define coupon code, discount type, amount, usage restrictions, and limits. Coupons created by vendors will only apply to their own products.
7.  **Profile Settings:**
    *   Update brand name, contact email, logos, public store page details (banner, avatar, about, policies, social links), and payout preferences.

### For Site Admins

1.  **Vendor Registrations:**
    *   Navigate to **Vendor Admin > Pending Registrations**.
    *   Review applications and "Approve" or "Deny" them. Approved vendors will have their role changed from 'Subscriber' to 'Vendor'.
2.  **Commission Settings:**
    *   Set the platform's global commission percentage under **Vendor Admin > Settings**.
3.  **Vendor Management:**
    *   Add new vendors directly via **Vendor Admin > Settings > Register New Vendor**.
    *   Manage existing vendors, assign products, or delete vendors from the list on the main **Vendor Admin > Settings** page.
4.  **Payouts:**
    *   Go to **Vendor Admin > Payouts**.
    *   Select a vendor to view their available balance and payout details.
    *   Record payouts made to vendors, specifying the amount, method used, and any reference/notes.
5.  **Earnings Reports:**
    *   Access various reports under **Vendor Admin > Earnings Reports**, including overall platform summary, vendor-specific performance, product performance, and a detailed earnings log. Filter reports by date, vendor, or category.

## Shortcodes

*   **`[vendor_dashboard]`**
    *   Renders the primary interface for vendors.
    *   Displays a login/registration form for logged-out users.
    *   Displays the full vendor dashboard for logged-in vendors.
    *   **Usage:** Add this shortcode to a dedicated page (e.g., "Vendor Dashboard").

*   **`[vdb_vendor_list]`**
    *   Displays a grid or list of all approved vendors with links to their public stores.
    *   **Attributes:**
        *   `orderby` (string): How to sort the vendors. Accepts `display_name` (default), `ID`, `user_login`, `user_nicename`, `user_email`, `user_registered`, `post_count`.
        *   `order` (string): Sort order. `ASC` (default) or `DESC`.
        *   `number` (int): Number of vendors to display. Default is `-1` (all).
    *   **Example:** `[vdb_vendor_list orderby="user_registered" order="DESC" number="10"]`
    *   **Usage:** Add this shortcode to a page to showcase your vendors (e.g., "Our Partners").

*   **`[vendor_public_store]`**
    *   Creates a link/button to a specific vendor's public store page.
    *   **Attributes:**
        *   `vendor_slug` (string, **required**): The user_nicename (slug) of the vendor.
    *   **Example:** `[vendor_public_store vendor_slug="cool-gadgets-inc"]`
    *   **Usage:** Useful for featuring specific vendors on blog posts or other pages.

## Current Limitations & Future Considerations

This plugin provides a solid foundation for a vendor marketplace. However, there are areas for potential expansion and features not yet implemented:

**Current Limitations:**

*   **Product Types:** The frontend product editor is currently optimized for **simple products**. Management of **variable products** or **downloadable/virtual products** from the vendor dashboard is not supported.
*   **Shipping Management:** Vendors can assign existing WooCommerce shipping classes. However, they cannot define their own shipping zones or complex shipping rules. Shipping calculation relies on the main site's WooCommerce settings.
*   **Commission Structure:** A single, global commission rate applies to all vendors. Per-vendor or per-product commission rates are not currently supported.
*   **Product Attributes:** The frontend product editor does not currently support creating or managing custom WooCommerce product attributes (e.g., for variations, filtering). Product tags are supported.
*   **Vendor-Specific Analytics:** While vendors see sales summaries, more detailed analytics on product views, customer engagement, or conversion rates are not yet available in the dashboard.
*   **Admin/Vendor UX:** The user interface for both vendors and admins is functional but could be enhanced for a more polished user experience.
*   **No Direct Payout Integration:** The plugin logs earnings and allows admins to record manual payouts. It does not automatically process payouts through PayPal or Stripe Connect; this remains an admin task.
*   **Limited Product Import/Export:** No built-in tools for vendors to bulk import or export their products.

**Potential Future Features/Improvements:**

*   Frontend support for Variable and Downloadable/Virtual products.
*   More granular commission options (per-vendor, per-category).
*   Enhanced vendor product attribute management.
*   Vendor-specific shipping zone configuration.
*   Direct payout integrations (e.g., Stripe Connect automation, PayPal Payouts API).
*   Vendor store reviews and ratings system.
*   Advanced analytics and reports for vendors.
*   Staff/sub-accounts for vendors.
*   Improved UI/UX for both vendor dashboard and admin areas.
*   Enhanced notification system (e.g., low stock alerts configurable by vendors).
*   Product import/export tools for vendors.
*   Frontend order notes/communication between vendor and customer.

## Hooks (For Developers)

*(This section can be expanded as you identify key filters/actions developers might use)*

**Filters:**

*   `vdb_social_platforms`: Allows modification of the available social media platforms in profile settings.
*   `vdb_social_icon_classes`: Allows modification of Font Awesome classes for social media icons.
*   `vdb_earnings_trigger_order_statuses`: Allows changing which order statuses trigger earning calculation.
*   `woocommerce_coupon_is_valid_for_product`: Used by the plugin to ensure vendor coupons only apply to their products.
*   `woocommerce_coupon_is_valid`: Used by the plugin for overall coupon validity checks related to vendor items in the cart.

**Actions:**

*   `vdb_public_store_after_header ( $vendor_user )`: Action hook after the public store header.
*   `vdb_public_store_sidebar_before ( $vendor_user )`: Action hook before the public store sidebar content.
*   `vdb_public_store_sidebar_after ( $vendor_user )`: Action hook after the public store sidebar content.
*   Standard WooCommerce and WordPress hooks are used extensively (e.g., `wp_ajax_...`, `admin_menu`, `init`, `wp_enqueue_scripts`, `woocommerce_order_status_changed`, etc.).

## Screenshots

*(Links to screenshots will be added here. You can store images in an `assets/screenshots/` directory in your repo and link them like `assets/screenshots/01-dashboard-overview.png`)*

1.  Vendor Dashboard - Overview Page (`assets/screenshots/01-dashboard-overview.png`)
2.  Vendor Dashboard - Product Management Interface (`assets/screenshots/02-product-management.png`)
3.  Vendor Dashboard - Product Editor Form (`assets/screenshots/03-product-editor.png`)
4.  Vendor Dashboard - Order Management (`assets/screenshots/04-order-management.png`)
5.  Vendor Dashboard - Coupon Editor (`assets/screenshots/05-coupon-editor.png`)
6.  Vendor Dashboard - Earnings Report (`assets/screenshots/06-earnings-report.png`)
7.  Vendor Dashboard - Profile Settings (`assets/screenshots/07-profile-settings.png`)
8.  Public Vendor Store Page - Example (`assets/screenshots/08-public-store.png`)
9.  Admin - Vendor Registrations (`assets/screenshots/09-admin-registrations.png`)
10. Admin - Payouts Management (`assets/screenshots/10-admin-payouts.png`)
11. Admin - Earnings Report (`assets/screenshots/11-admin-earnings-report.png`)

## Frequently Asked Questions (FAQ)

*   **Q: Can vendors sell variable products or digital downloads?**
    *   A: Currently, the frontend product editor is optimized for simple, physical products. Variable and downloadable/virtual product management from the vendor dashboard is not yet supported.
*   **Q: How are shipping costs handled for vendors?**
    *   A: Vendors can assign existing WooCommerce shipping classes to their products. The main site's WooCommerce shipping zone and method settings handle the actual shipping cost calculation. Vendors can add tracking information for their shipments.
*   **Q: Does the plugin support per-vendor commission rates?**
    *   A: No, currently there is one global commission rate that applies to all vendors, configurable by the site admin.
*   **Q: How are payouts managed?**
    *   A: Currently there is no integration to automatically manage payouts. Payouts are the responsibility of the site admin. The Vendor's Earnings page currently states: "Your earnings become available as soon as the order is completed or processing. Payouts for your available balance are processed by the site administrators weekly on Thursdays."

## Contributing

Contributions are welcome! If you'd like to contribute, please:

1.  Fork the repository.
2.  Create a new branch for your feature or bug fix (`git checkout -b feature/your-feature-name` or `bugfix/issue-number`).
3.  Commit your changes with clear, descriptive messages.
4.  Push your branch to your fork (`git push origin feature/your-feature-name`).
5.  Submit a Pull Request to the `main` (or `master`) branch of this repository.

Please try to follow WordPress coding standards and ensure your changes do not introduce security vulnerabilities.

## Changelog

See `CHANGELOG.md` or check the [Releases page](LINK_TO_YOUR_GITHUB_RELEASES_PAGE_HERE_LATER).