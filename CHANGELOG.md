# Changelog

## Unreleased

- Light mode now has the same levels as dark mode: ground (sidebar and frame), white content card, panels, and raised containers. The panels are almost white with a very light outline, so they stay visible on the white card.
- A section inside a tabs or wizard panel is lighter than the panel that holds it, as in dark mode.
- Contained tabs and wizards now use the panel level in both modes. In dark mode they keep their colour, `gray-900`.
- New settings: `--polder-theme-panel`, `--polder-theme-nested` and `--polder-theme-raised-shadow`. `--polder-theme-raised` and `--polder-theme-raised-ring` now have light mode values too.
- Dark mode keeps its colours.

## v0.3.5 - 2026-11-07

- Light mode now has three levels, like dark mode: a darker ground (frame and sidebar), a light gray content card, and white sections, tables and stat cards with a stronger outline. Before, the card and the sections were both white.
- The active sidebar item in light mode is now white with an outline, and hover states use a transparent tint, so they stay visible on the darker ground.
- New settings: `--polder-theme-track` and `--polder-theme-hover`. In light mode, `--polder-theme-raised` and `--polder-theme-raised-ring` now have values too.
- Dark mode keeps its existing colours.

## v0.3.4 - 2026-11-07

- Reverts v0.3.3. The light page content is white again, as in v0.3.2.

## v0.3.2 - 2026-10-22

- The flat centre button's label now lines up with the other labels in the mobile bar. Before, it sat 6px lower.

## v0.3.1 - 2026-10-22

- The mobile bar accepts file uploads only into the upload fields of its actions' forms, as Filament's own pages do. Other upload attempts get a 403.

## v0.3.0 - 2026-10-22

- `MobileNavigation::visible()` hides the mobile bar on the pages you choose. The closure can receive `$routeName` and `$routeParameters`.

## v0.2.1 - 2026-10-22

- The replaced page header and user menu now apply only to panels that register the plugin. Other panels in the same app render Filament's own views. Before, they showed Polder's breadcrumb bar and user menu without Polder's CSS.
- Removed a style rule for `.fi-select-wrp`, a class that Filament does not have.

## v0.2.0 - 2026-10-22

First public release.

- Inset layout: the content card floats on the frame, next to a 16rem sidebar with the user menu at the bottom. `->sidebarWidth()` changes the width.
- Breadcrumb bar with a sidebar toggle and a slot for your own content (`PolderRenderHook::BREADCRUMB_BAR_END`). On phones, and with `->topbar()`, the panel shows one sidebar toggle only.
- Light and dark mode. Dark mode has four distinct layers (frame, card, containers, fields), with clear table lines and outlines.
- Optional compact density with `->compact()`, set for each panel.
- Tenant switcher in the user menu when the panel turns Filament's tenant menu off.
- Mobile navigation: an optional bottom bar for phones, floating or docked, with links, actions, bottom sheets (your own items, the panel navigation, the user menu or a Blade view) and a raised or flat centre action. Closures can read the page the bar was opened on.
- All rules in `@layer polder-theme`. The plugin declares the full layer order at the top of `<head>`, so a plugin stylesheet that loads first cannot push the theme above Tailwind utilities.
