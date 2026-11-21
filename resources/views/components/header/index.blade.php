@props([
    'actions' => [],
    'actionsAlignment' => null,
    'breadcrumbs' => [],
    'heading' => null,
    'subheading' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsRenderHook;
    use VTech9NL\PolderTheme\PolderRenderHook;
    use VTech9NL\PolderTheme\PolderThemePlugin;
    use VTech9NL\PolderTheme\PolderThemeServiceProvider;

    use function Filament\Support\is_slot_empty;

    $getVisibleActionNames = function (array $actions) use (&$getVisibleActionNames): array {
        return array_map(
            fn ($action) => $action->isVisible()
                ? ($action instanceof ActionGroup ? $getVisibleActionNames($action->getActions()) : ($action instanceof Action ? $action->getName() : true))
                : null,
            $actions,
        );
    };
@endphp

@if (! filament()->getCurrentPanel()?->hasPlugin(PolderThemePlugin::ID))
    @include('polder-theme::stock.header')
@else
@php
    $isMobileNavigationVisible = app()->getProvider(PolderThemeServiceProvider::class)->isMobileNavigationVisible();
    $homeUrl = filament()->getHomeUrl();
    $showBackButton = $isMobileNavigationVisible
        && filled($heading)
        && filament()->getCurrentPanel()->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->hasBackButton()
        && ! ($this instanceof \Filament\Pages\Dashboard)
        && rtrim(request()->url(), '/') !== rtrim(url($homeUrl), '/');
    $backUrl = collect(array_keys($breadcrumbs))
        ->last(fn ($url): bool => is_string($url) && filled($url)
            && rtrim(explode('?', url($url), 2)[0], '/') !== rtrim(request()->url(), '/')) ?? $homeUrl;
    $barContent = FilamentView::renderHook(PolderRenderHook::BREADCRUMB_BAR_CONTENT, scopes: $this->getRenderHookScopes());
    $barEnd = FilamentView::renderHook(PolderRenderHook::BREADCRUMB_BAR_END, scopes: $this->getRenderHookScopes());
    $hasEmptyMobileBar = $isMobileNavigationVisible && is_slot_empty($barContent) && is_slot_empty($barEnd);
    $visibleActions = is_array($actions) ? array_values(array_filter($actions, fn ($action): bool => $action->isVisible())) : [];
    $hasCompactActions = $isMobileNavigationVisible
        && filled($visibleActions)
        && filament()->getCurrentPanel()->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->hasCompactHeaderActions();
    $hasHeadingRow = $showBackButton || $hasCompactActions;
@endphp
<div @class([
    'polder-theme-breadcrumb-bar',
    'polder-theme-breadcrumb-bar-empty-mobile' => $hasEmptyMobileBar && ! $breadcrumbs,
    'polder-theme-breadcrumb-bar-empty-phone' => $hasEmptyMobileBar && $breadcrumbs,
])>
    @if (filament()->hasNavigation() && (! filament()->hasTopbar()))
        <button
            type="button"
            x-data="{}"
            x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open($event.currentTarget)"
            x-bind:aria-label="$store.sidebar.isOpen ? @js(__('filament-panels::layout.actions.sidebar.collapse.label')) : @js(__('filament-panels::layout.actions.sidebar.expand.label'))"
            @class([
                'polder-theme-sidebar-toggle',
                'polder-theme-sidebar-toggle-desktop-only' => $isMobileNavigationVisible,
                'polder-theme-sidebar-toggle-mobile-only' => ! (filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop()),
            ])
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" /><path d="M9 3v18" /></svg>
        </button>
    @endif

    @if ($breadcrumbs)
        <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
    @endif

    @if (FilamentView::hasRenderHook(PolderRenderHook::BREADCRUMB_BAR_CONTENT, scopes: $this->getRenderHookScopes()))
        <div class="polder-theme-breadcrumb-bar-content">
            {{ $barContent }}
        </div>
    @endif

    <div class="polder-theme-breadcrumb-bar-end">
        {{ $barEnd }}
    </div>
</div>

<header
    {{
        $attributes->class([
            'fi-header',
            'fi-header-has-subheading' => filled($subheading),
            'polder-theme-compact-header' => $hasCompactActions,
            'polder-theme-header-actions-inline' => $hasCompactActions && count($visibleActions) === 1,
            'polder-theme-header-actions-menu' => $hasCompactActions && count($visibleActions) > 1,
        ])
    }}
    @if ($hasCompactActions)
        x-data="{
            isInline: @js(count($visibleActions) === 1),
            measure() {
                const actions = this.$refs.actions
                const menu = this.$refs.menu
                const container = actions?.closest('.fi-header-actions-ctn')
                const heading = this.$root.querySelector('.fi-header-heading')

                if (! actions || ! container || window.matchMedia('(min-width: 64rem)').matches) {
                    return
                }

                const gap = parseFloat(getComputedStyle(container).columnGap) || 0
                const actionsWidth = actions.getBoundingClientRect().width
                const menuWidth = this.isInline ? 0 : menu.getBoundingClientRect().width + gap
                const inlineWidth = container.getBoundingClientRect().width - menuWidth + (this.isInline ? 0 : actionsWidth + gap)
                const row = this.$root.querySelector('.polder-theme-heading-row')
                const leadWidth = row ? row.getBoundingClientRect().width - (heading?.getBoundingClientRect().width ?? 0) : 0
                const rowWidth = this.$root.clientWidth
                const headingWidth = Math.min(rowWidth * 0.4, heading?.scrollWidth ?? 0)
                const rowGap = parseFloat(getComputedStyle(this.$root).columnGap) || 0

                this.isInline = (leadWidth + headingWidth + rowGap + inlineWidth) <= rowWidth
            },
        }"
        x-init="
            measure()
            const observer = new ResizeObserver(() => measure())
            observer.observe($el)
            observer.observe($refs.actions)
        "
        x-bind:class="{
            'polder-theme-header-actions-inline': isInline,
            'polder-theme-header-actions-menu': ! isInline,
        }"
        {{-- Livewire keeps the measured mode: a server render must not reset it --}}
        wire:ignore.self
    @endif
>
    @capture($headingContent)
        @if ($hasHeadingRow)
            <div class="polder-theme-heading-row">
        @endif
        @if ($showBackButton)
                <x-filament::icon-button
                    tag="a"
                    :href="$backUrl"
                    :spa-mode="false"
                    :icon="Heroicon::OutlinedArrowLeft"
                    :label="__('polder::mobile-navigation.back')"
                    color="gray"
                    class="polder-theme-back-button"
                    x-data="{}"
                    x-on:click="if (document.referrer && new URL(document.referrer).origin === window.location.origin && window.history.length > 1) { $event.preventDefault(); window.history.back() }"
                />
        @endif
        {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, scopes: $this->getRenderHookScopes()) }}

        @if (filled($heading))
            <h1 class="fi-header-heading">
                {{ $heading }}
            </h1>
        @endif

        {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, scopes: $this->getRenderHookScopes()) }}

        @if ($hasHeadingRow)
            </div>
        @endif

        @if (filled($subheading))
            <p class="fi-header-subheading">
                {{ $subheading }}
            </p>
        @endif
    @endcapture

    @php
        $headingContent = $headingContent();
    @endphp

    @if (! is_slot_empty($headingContent))
        <div @class(['polder-theme-header-heading' => $hasCompactActions])>{{ $headingContent }}</div>
    @else
        {{ $headingContent }}
    @endif

    @php
        $beforeActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes());
        $afterActions = FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes());
    @endphp

    @capture($actionsContent)
        {{ $beforeActions }}

        @if ($actions)
            <x-filament::actions
                :actions="$actions"
                :alignment="$actionsAlignment"
                :wire:key="is_array($actions) ? $this->getId() . '.header-actions.' . md5(serialize($getVisibleActionNames($actions))) : null"
                :x-ref="$hasCompactActions ? 'actions' : null"
            />
        @endif

        {{ $afterActions }}

        @if ($hasCompactActions)
            {{-- The menu shows the same page actions: copies without key bindings, so that a click or a key mounts the action of the page one time --}}
            <div
                class="polder-theme-header-actions-menu-trigger"
                x-ref="menu"
                wire:key="{{ $this->getId() }}.header-actions-menu.{{ md5(serialize($getVisibleActionNames($actions))) }}"
            >
                {{
                    ActionGroup::make(array_map(
                        fn (Action | ActionGroup $action): Action | ActionGroup => $action instanceof Action
                            ? (clone $action)->keyBindings(null)
                            : clone $action,
                        $visibleActions,
                    ))
                        ->label(__('polder::mobile-navigation.actions'))
                        ->icon(Heroicon::EllipsisVertical)
                        ->color('gray')
                        ->dropdownPlacement('bottom-end')
                }}
            </div>
        @endif
    @endcapture

    @php
        $actionsContent = $actionsContent();
    @endphp

    @if (! is_slot_empty($actionsContent))
        <div class="fi-header-actions-ctn">
            {{ $actionsContent }}
        </div>
    @else
        {{ $actionsContent }}
    @endif
</header>
@endif
