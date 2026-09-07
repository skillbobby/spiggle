# Spiggle Material Theme

Public product page and comprehensive showcase for **Spiggle Material Theme**, a Laravel 13 and Filament 5 plugin that restyles the admin panel with Material Design.

Live site: [skillbobby.github.io/spiggle/material-theme](https://skillbobby.github.io/spiggle/material-theme/)

Package source stays in its own repository. This folder is the public showcase only.

## What it is

Material Design chrome built specifically for Laravel Filament 5, utilizing Roboto typography, 24dp icons, and tonal surfaces. Tables, forms, and resources remain natively Filament, while the surrounding shell—including the sidebar, top bar, cards, login screen, and notifications—is completely restyled. Everything from your brand logo, primary colors, and density to notification preferences, global search, toolbar shortcuts, and the promo banner can be managed directly inside Theme Settings powered by Spatie Laravel Settings. No Vite or Tailwind rebuilds are required.

## 🌟 Core Features & Capabilities

| Feature | Description |
|---------|-------------|
| **Material Design Chrome** | Restyles sidebar, canvas, and cards with Roboto type, 24dp icons, and tonal surfaces while keeping Filament controls intact. |
| **Theme Settings in the Panel** | Manage brand, primary color, density, icon pack, promo banner, and dark mode directly via Spatie Laravel Settings. |
| **Brand Logo & Title** | Upload custom marks for sidebar and sign-in, configure brand title and tagline without touching CSS. |
| **Light and Dark Modes** | Includes a top bar scheme switcher or option to force dark mode and hide toggle. |
| **Toolbar Shortcuts** | Pin Lucide icons in the top bar linking to any panel URL, editable via Theme Settings. |
| **Lucide Icon Pack** | Swap Filament Heroicons for Lucide icons across navigation and theme chrome. |
| **Notifications Inbox** | Tabs for All, System, and Archive with read/delete controls and Spatie Activity Log integration. |
| **Global Search** | Search across resource records from the top bar with identifier badges showing what you opened. |
| **List View** | Toggle any Filament table to an alphabetical A–Z list with avatars and stacked columns. |
| **Additional Inclusions** | Promo banner with ticker, compact account page, dense spacing options, subdirectory support, and mobile-ready layouts. |

## Screenshots & Gallery

The public showcase features comprehensive visual previews across desktop and mobile devices:

* **Sign-in Screen ![](screenshots/login.png)**
* **Theme Settings ![](screenshots/theme.png)**
* **Filament Tables ![](screenshots/users.png)**
* **List View ![](screenshots/list-view.png)**
* **Notifications Inbox ![](screenshots/notifications.png)**
* **Notification Controls ![](screenshots/notify-panel.png)**
* **Global Search ![](screenshots/search.png)**
* **Toolbar Shortcuts ![](screenshots/shortcuts.png)**
* **Account Page ![](screenshots/account.png)**
* **Mobile Views:** 
  * ![](screenshots/mobile-dashboard.png) — Optimized dashboard view on a mobile device.
  * ![](screenshots/mobile-users.png) — Responsive users table on a phone.
  * ![](screenshots/mobile-nav.png) — Material sidebar navigation layout on mobile screens.

## Installation & Workflow

1. **Register the plugin:** Add `MaterialThemePlugin::make()` to your panel provider, configuring optional brand titles, taglines, density, and demo pages.
2. **Open Theme Settings:** Upload your logo, select primary colors, toggle density or dark mode, choose between Lucide or Heroicons, and set your promo banner.
3. **Keep your resources:** Forms and tables remain natively Filament while the entire shell, including login, account pages, and notifications, gets restyled.

## Pricing & Licensing

* **Price:** **$39** lifetime license
* **Terms:** Pay once for lifetime access to all package files with no seat limits or yearly renewals, allowing you to use it on an unlimited number of Laravel Filament apps.
* **Status:** Checkout links are coming soon; until then, licensing can be arranged by getting in touch via email.

## FAQ Summary

* **Vite / Tailwind Rebuild:** Not required; theme CSS ships directly within the package.
* **System Requirements:** Built primarily for Laravel 13 and Filament 5 on PHP 8.3+.
* **Subdirectory Compatibility:** Fully supported via Laravel's `asset()` helper (e.g., panels running at `/spiggle/admin` load seamlessly).

## Contact

[@iamspiggle](https://x.com/iamspiggle)
