<span class="polder-theme-mobile-navigation-icon">
    @if (filled($entry['icon']))
        {!! $entry['icon'] !!}
    @elseif (($entry['sheet'] ?? null) === \VTech9NL\PolderTheme\MobileNavigation\MobileNavigationItem::SHEET_USER_MENU)
        <x-filament-panels::avatar.user :user="filament()->auth()->user()" loading="lazy" />
    @endif
    @if (filled($entry['badge'] ?? null) && ($entry['badge'] !== 0) && ($entry['badge'] !== '0'))
        <x-filament::badge :color="$entry['badgeColor'] ?? 'primary'" size="xs" class="polder-theme-mobile-navigation-badge">
            {{ $entry['badge'] }}
        </x-filament::badge>
    @endif
</span>

<span class="polder-theme-mobile-navigation-label">{{ $entry['label'] }}</span>
