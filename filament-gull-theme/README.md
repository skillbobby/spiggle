<p align="center">
  <img src="screenshots/light.png" alt="Gull theme compact dashboard for Laravel Filament" width="100%">
</p>

# Gull theme for Filament 4 and 5

A panel theme that follows the Gull admin dashboard: Nunito, `#663399` primary, soft 10px cards, and the two menus Gull is known for.

Built for **Filament 4.x and 5.x** on **Laravel 11, 12, and 13** with PHP 8.2+.

[**View the theme page →**](https://skillbobby.github.io/spiggle/filament-gull-theme/)

---

## Look and feel

| Layout | How the menu works |
|---|---|
| **Compact** (default) | A 120px rail. Each parent is a Tabler icon with a 13px label under it. Hover opens a 230px white flyout of that section. Click pins the flyout; click again closes it. A triangle marks the open section. |
| **Large** | A 260px sidebar. Section labels are uppercase. Items accordion in place, icon then label. |
| **Phone** | Both layouts become an off-canvas drawer with a dimmed backdrop. Targets are at least 44px. Compact keeps the icon rail and shows children beside it. The hamburger opens and closes it. Search collapses to an icon. |

The page background is `#f8f9fa`. Cards use a 10px radius and the shadow `0 4px 20px 1px rgba(0,0,0,.06)`. Body type is Nunito at 13px inside the panel. Primary actions and the active rail item are `#663399`.

<p align="center">
  <img src="screenshots/flyout.png" alt="Compact rail with the UI kits flyout open" width="100%">
</p>

Sidebar skins match Gull’s compact colors: light, purple, midnight, indigo, pink, slate, and a purple–indigo gradient. The flyout stays white.

<p align="center">
  <img src="screenshots/purple.png" alt="Purple sidebar skin" width="100%">
</p>

## Features

- **Compact rail and flyout.** 120px icon rail, 230px secondary panel, triangle pointer, hover to preview, click to pin.
- **Large sidebar.** 260px accordion with uppercase group labels.
- **Light and dark mode.** A Light / Dark switch in the top bar, on the sign-in screen, and in Customize. Stored in `localStorage` as `gull-mode`.
- **Seven sidebar skins.** Stored as `gull-skin`. Set `'customizer' => false` to hide the gear tab.
- **Tabler icons, fixed sizes.** 26px on the rail, 18px in the flyout, 20px in the large sidebar, 22px in the top bar. Same 24×24 outline grid, stroke 2.
- **Mobile drawer.** Off-canvas, dimmed backdrop, 44px targets.
- **Filament surfaces.** Tables, alerts, buttons, badges, cards, and forms keep native behavior and pick up the Gull chrome.
- **Filament 4 and 5.** One plugin. Render hooks are resolved by name so a missing hook on one major version is skipped.

<p align="center">
  <img src="screenshots/dark.png" alt="Gull dashboard in dark mode" width="100%">
</p>

<p align="center">
  <img src="screenshots/mobile.png" alt="Gull mobile drawer" width="320">
</p>

## Components

<p align="center">
  <img src="screenshots/tables.png" alt="Orders table styled by the Gull theme" width="100%">
</p>

- **Tables.** Card surface, status pills, and a rounded search field.
- **Alerts.** Info, success, warning, and danger on tinted surfaces.
- **Buttons and badges.** Primary `#663399`, plus secondary, success, danger, and ghost.
- **Forms.** 40px fields, two-column grids, and inline errors.

<p align="center">
  <img src="screenshots/alerts.png" alt="Gull alerts" width="100%">
</p>

## Requirements

| Package | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | 11.x, 12.x, or 13.x |
| Filament | ^4.0 or ^5.0 |

## Installation

```bash
composer require gull/filament-theme
composer require secondnetwork/blade-tabler-icons
php artisan vendor:publish --tag=filament-gull-theme-config
```

Register the plugin. This works on Filament 4 and Filament 5:

```php
use Gull\FilamentTheme\GullThemePlugin;

$panel->plugin(GullThemePlugin::make());
```

The plugin sets the Nunito font, the Gull color palette, `sidebarCollapsibleOnDesktop()` (so Filament renders icons on nested items), and the theme CSS, menu script, and top-bar toggle through panel render hooks.

## Menus

Filament only prints child links when the parent has **no URL** (or that branch is already active). Parents used as rail sections must not have a URL, or the flyout will be empty until you are already inside it.

Do **not** put an icon on the navigation group. Filament throws if a group and its items both have icons. Put Tabler icons on the parent item and on each child.

```php
use Filament\Navigation\NavigationGroup;
use Gull\FilamentTheme\Gull;

$panel->navigationGroups([
    NavigationGroup::make('Applications')->collapsible(false)->items([
        Gull::parent('Dashboards', 'tabler-chart-bar', [
            Gull::link('Version 1', 'tabler-layout-dashboard', '/admin'),
            Gull::link('Version 2', 'tabler-report-analytics', '/admin/sales'),
        ]),
        Gull::parent('UI kits', 'tabler-stack-2', [
            Gull::link('Alerts', 'tabler-alert-triangle', '/admin/alerts'),
            Gull::link('Tables', 'tabler-table', '/admin/orders'),
        ]),
        Gull::link('Charts', 'tabler-chart-dots-3', '/admin/charts'),
    ]),
]);
```

Groups should be `->collapsible(false)` so a section header cannot hide the rail.

The theme turns on `sidebarCollapsibleOnDesktop()` because that is when Filament renders icons on nested items. Its own hamburger keeps the Filament sidebar open and toggles the Gull flyout (or slides the large sidebar away) instead.

## Customize

A gear tab on the right switches layout and sidebar skin. Choices are stored in `localStorage` (`gull-layout`, `gull-skin`, `gull-mode`). Defaults live in `config/filament-gull-theme.php`.

| Key | Values |
|---|---|
| `layout` | `compact` (default), `large`. Env: `GULL_LAYOUT` |
| `sidebar` | `light`, `purple`, `midnight`, `indigo`, `pink`, `slate`, `gradient`. Env: `GULL_SIDEBAR` |
| `customizer` | `true` to show the gear tab |

Icons are [Tabler outline](https://tabler.io/icons).

## Security

If you discover a security issue, email **skillbobby@outlook.com** instead of opening a public issue.

## License

MIT.
