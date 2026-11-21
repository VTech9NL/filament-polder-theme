# Changelog

## v0.4.2 - 2026-11-21

- Fix the icons-only Flat centre icon colour and remove its fill in light and dark mode.

## v0.4.1 - 2026-11-21

- Hide the empty breadcrumb bar below `lg` when mobile navigation is visible; breadcrumbs alone keep it only from `sm` up. Keep bars with hook output and safe-area spacing above the page heading.
- Add opt-in `MobileNavigation::iconsOnly(bool|Closure $condition = true)` for larger, centred bar icons without visible labels, preserving accessible labels, badges and sheet titles. An icons-only Flat centre action matches the other entries with a primary-coloured icon and no fill; labelled Flat and raised buttons keep their existing style.

## v0.4.0 - 2026-11-21

- New dependency: `vtech9nl/filament-bottom-sheet` ^0.2. Polder's `theme.css` has the `@source` line for it. Rebuild the panel theme.
- The mobile bar sheets are element sheets of the plugin, with the ids `polder-theme-polderItem<n>` (`<n>`: the item position, from 0). They open at the height of their content, snap at 25, 50, 75 and 100%, and close with a drag down, an outside tap or Escape.
- A link or an action in a sheet closes the sheet with `bottom-sheet:close` before it runs.
- Removed: the theme sheet state, backdrop, transition and handle, and their CSS classes `polder-theme-mobile-navigation-sheet`, `-sheet-backdrop`, `-sheet-panel-hidden` and `-sheet-handle`. The content classes stay.

## v0.3.13 - 2026-11-21

- Define the mobile navigation height on `:root` so page content reserves space above the mobile bar below `lg`, including the bottom safe area.

## v0.3.12 - 2026-11-21

- Hide the breadcrumb bar's sidebar toggle below `lg` when mobile navigation is visible on the current page. Desktop and pages without the bar keep their usual behaviour; the account menu is available through a `sheet()->userMenu()` item.
- Mobile links, actions and sheets support `badge()` and `badgeColor()` on their icons, including closures with page context. Badges default to `primary` and hide for null, empty or zero values.

## v0.3.11 - 2026-11-21

- Custom mobile sheets are active when one of their links is active. `isActiveWhen()` overrides this and can mark any sheet active.
- Mobile links support `disabled()` in the bar and custom sheets, rendering a gray span without a link. All entry types support `title()`, including closures with page context.

## v0.3.10 - 2026-11-20

- Switches keep a full-size track and an even knob gap in compact density.

## v0.3.9 - 2026-11-19

- The breadcrumb bar has a full-width slot, `PolderRenderHook::BREADCRUMB_BAR_CONTENT`, between the breadcrumbs and the end slot. It renders no box when no hook is registered.

## v0.3.8 - 2026-11-08

- Keep section header actions at the top right in narrow sections while the heading and description wrap in the remaining space.

## v0.3.7 - 2026-11-08

- Refresh the header override for Filament 5.11.2; retain the compatible dark-mode check for earlier Filament 5 releases.

- Hide Filament’s in-sidebar collapse and expand buttons on desktop when Polder’s header toggle is present, leaving a single toggle in both sidebar states. Mobile sidebar controls stay available.

## v0.3.6 - 2026-11-07

- Light mode now has the same levels as dark mode: ground (sidebar and frame), white content card, panels, and raised containers. The panels are almost white with a very light outline, so they stay visible on the white card.
- A section inside a tabs or wizard panel is lighter than the panel that holds it, as in dark mode.
- Contained tabs and wizards now use the panel level in both modes. In dark mode they keep their colour, `gray-900`.
- New settings: `--polder-theme-panel`, `--polder-theme-nested` and `--polder-theme-raised-shadow`. `--polder-theme-raised` and `--polder-theme-raised-ring` now have light mode values too.
- Dark mode keeps its colours.
- The sidebar toggle in the breadcrumb bar now shows on desktop only when the panel can collapse its sidebar on desktop. Before, it showed on every panel and did nothing on panels that cannot collapse. On phones it always shows.

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
