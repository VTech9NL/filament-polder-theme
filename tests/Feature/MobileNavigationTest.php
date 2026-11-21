<?php

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use VTech9NL\PolderTheme\Livewire\MobileNavigationBar;
use VTech9NL\PolderTheme\MobileNavigation\MobileNavigation;
use VTech9NL\PolderTheme\MobileNavigation\MobileNavigationButton;
use VTech9NL\PolderTheme\MobileNavigation\MobileNavigationItem;
use VTech9NL\PolderTheme\PolderRenderHook;
use VTech9NL\PolderTheme\PolderThemePlugin;
use VTech9NL\PolderTheme\Tests\Fixtures\BreadcrumbPage;
use VTech9NL\PolderTheme\Tests\Fixtures\Team;
use VTech9NL\PolderTheme\Tests\Fixtures\User;

use function Orchestra\Testbench\package_path;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'secret']);
    $this->team = Team::query()->create(['name' => 'Analytical Engine']);
    $this->user->teams()->attach($this->team);

    actingAs($this->user);

    $this->ran = null;

    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->mobileNavigation(
        MobileNavigation::make()
            ->items([
                MobileNavigationItem::link('home')->label('Home')->url(fn (): string => Dashboard::getUrl()),
                MobileNavigationItem::action(fn (): Action => Action::make('scan')->label('Scan')->action(fn () => $this->ran = 'scan')),
                MobileNavigationItem::sheet('tools')->label('Tools')->items([
                    MobileNavigationItem::action(fn (): Action => Action::make('export')->label('Export')->action(fn () => $this->ran = 'export')),
                ]),
                MobileNavigationItem::sheet('more')->navigation(),
                MobileNavigationItem::sheet('account')->userMenu(),
            ])
            ->centerAction(fn (?string $routeName, array $routeParameters): Action => Action::make('logActivity')
                ->action(function () use ($routeName, $routeParameters): void {
                    $this->ran = [
                        'panel' => Filament::getCurrentPanel()?->getId(),
                        'tenant' => Filament::getTenant()?->getKey(),
                        'routeName' => $routeName,
                        'routeParameters' => $routeParameters,
                    ];
                })),
    );
});

it('renders the bar on a panel page and remembers that page', function () {
    get('/app/'.$this->team->getKey())
        ->assertOk()
        ->assertSee('polder-theme-mobile-navigation')
        ->assertSee('filament.app.pages.dashboard');
});

it('runs the app centre action with the panel, tenant and page it was mounted on', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    $bar = livewire(MobileNavigationBar::class, [
        'routeName' => 'filament.app.pages.dashboard',
        'routeParameters' => ['tenant' => (string) $this->team->getKey()],
    ]);

    Filament::setCurrentPanel('compact');
    Filament::setTenant(null, isQuiet: true);

    $bar->callAction(MobileNavigationBar::CENTER_ACTION);

    expect($this->ran)->toBe([
        'panel' => 'app',
        'tenant' => $this->team->getKey(),
        'routeName' => 'filament.app.pages.dashboard',
        'routeParameters' => ['tenant' => (string) $this->team->getKey()],
    ]);
});

it('does not let the browser change the panel, tenant or page it was mounted on', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    livewire(MobileNavigationBar::class)->set('tenantKey', 999);
})->throws(CannotUpdateLockedPropertyException::class);

it('refuses a tenant the user does not belong to', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant(Team::query()->create(['name' => 'Difference Engine']), isQuiet: true);

    $bar = livewire(MobileNavigationBar::class);
    Filament::setTenant(null, isQuiet: true);

    $bar->call('mountAction', MobileNavigationBar::CENTER_ACTION)->assertForbidden();

    expect($this->ran)->toBeNull();
});

it('refuses a signed-out request', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    $bar = livewire(MobileNavigationBar::class);
    auth()->logout();

    $bar->call('mountAction', MobileNavigationBar::CENTER_ACTION)->assertForbidden();

    expect($this->ran)->toBeNull();
});

it('shows the configured items and marks the current page', function () {
    get('/app/'.$this->team->getKey())
        ->assertOk()
        ->assertSeeInOrder(['Home', 'Scan', 'Tools', 'More', 'Account'])
        ->assertSee('aria-current="page"', escape: false);
});

it('runs an action item from the bar and from a custom sheet', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    livewire(MobileNavigationBar::class)->callAction('polderItem1');
    expect($this->ran)->toBe('scan');

    livewire(MobileNavigationBar::class)->callAction('polderItem2_0');
    expect($this->ran)->toBe('export');
});

it('lists the panel navigation in the More sheet', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    $more = collect(livewire(MobileNavigationBar::class)->get('items'))->firstWhere('label', 'More');

    expect(collect($more['groups'])->pluck('items')->flatten(1)->pluck('label'))->toContain('Dashboard');
});

it('offers the user menu and the other tenants in the Account sheet', function () {
    $this->user->teams()->attach(Team::query()->create(['name' => 'Difference Engine']));

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $bar = substr($html, strpos($html, 'class="polder-theme-mobile-navigation '));

    expect($bar)
        ->toContain(__('filament-panels::layout.actions.logout.label'))
        ->toContain('Difference Engine');
});

it('hides the bar where the app says so', function () {
    $navigation = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation();

    $navigation->visible(fn (?string $routeName): bool => $routeName !== 'filament.app.pages.dashboard');
    get('/app/'.$this->team->getKey())->assertOk()->assertDontSee('polder-theme-mobile-navigation');

    $navigation->visible(fn (?string $routeName): bool => $routeName === 'filament.app.pages.dashboard');
    get('/app/'.$this->team->getKey())->assertOk()->assertSee('polder-theme-mobile-navigation');
});

it('accepts file uploads only into the fields of its actions', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);

    livewire(MobileNavigationBar::class)->call('_startUpload', 'notAField', [], false)->assertForbidden();
});

it('renders no bar on a panel without mobile navigation', function () {
    get('/compact')->assertOk()->assertDontSee('polder-theme-mobile-navigation');
});

it('marks a custom sheet active from its links unless explicitly overridden', function () {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);
    $sheet = MobileNavigationItem::sheet('earnings')->items([
        MobileNavigationItem::link('tips')->isActiveWhen(fn (?string $routeName): bool => $routeName === 'tips'),
    ]);
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([$sheet]);

    livewire(MobileNavigationBar::class, ['routeName' => 'tips'])->assertSet('items.0.active', true);
    livewire(MobileNavigationBar::class, ['routeName' => 'other'])->assertSet('items.0.active', false);
    $sheet->isActiveWhen(false);
    livewire(MobileNavigationBar::class, ['routeName' => 'tips'])->assertSet('items.0.active', false);
});

it('uses an explicit active condition for every kind of sheet', function (string $source) {
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->team, isQuiet: true);
    $sheet = MobileNavigationItem::sheet('profile');
    match ($source) {
        'items' => $sheet->items([]),
        'navigation' => $sheet->navigation(),
        'userMenu' => $sheet->userMenu(),
        'view' => $sheet->view('filament::components.loading-indicator'),
    };
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([$sheet]);

    livewire(MobileNavigationBar::class)->assertSet('items.0.active', false);
    $sheet->isActiveWhen(fn (?string $routeName): bool => $routeName === 'profile');
    livewire(MobileNavigationBar::class, ['routeName' => 'profile'])->assertSet('items.0.active', true);
})->with(['items', 'navigation', 'userMenu', 'view']);

it('renders disabled links with a title and no href in the bar and custom sheets', function (bool $inSheet) {
    $item = MobileNavigationItem::link('room')->url('/blocked-room')->isActiveWhen(true)
        ->disabled(fn (?string $routeName): bool => $routeName === 'filament.app.pages.dashboard')
        ->title(fn (array $routeParameters): string => 'Blocked for '.$routeParameters['tenant']);
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()
        ->items([$inSheet ? MobileNavigationItem::sheet('tools')->items([$item]) : $item]);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $nodes = (new DOMXPath($dom))->query('//*[@aria-disabled="true"]');
    expect($nodes)->toHaveCount(1);
    $entry = $nodes->item(0);
    expect($entry->nodeName)->toBe('span')
        ->and($entry->hasAttribute('href'))->toBeFalse()
        ->and($entry->getAttribute('title'))->toBe('Blocked for '.$this->team->getKey())
        ->and($entry->getAttribute('class'))->toContain('polder-theme-disabled')->not->toContain('polder-theme-active');
})->with([false, true]);

it('renders escaped titles on each entry type and keeps enabled links', function () {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([
        MobileNavigationItem::link('room')->url('/room')->disabled(false)->title('Room "ready"'),
        MobileNavigationItem::action(fn (): Action => Action::make('scan'))->title('Scan title'),
        MobileNavigationItem::sheet('tools')->title('Tools title'),
    ]);

    get('/app/'.$this->team->getKey())->assertOk()
        ->assertSee('href="/room"', escape: false)
        ->assertSee('title="Room &quot;ready&quot;"', escape: false)
        ->assertSee('title="Scan title"', escape: false)
        ->assertSee('title="Tools title"', escape: false)
        ->assertDontSee('aria-disabled="true"', escape: false);
});

it('marks a sheet active when a child URL matches the current page', function () {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([
        MobileNavigationItem::sheet('earnings')->items([
            MobileNavigationItem::link('tips')->url(fn (): string => Dashboard::getUrl()),
        ]),
    ]);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $entry = (new DOMXPath($dom))->query('//button[@aria-haspopup="dialog"]')->item(0);
    expect($entry->getAttribute('class'))->toContain('polder-theme-active');
});

it('hides the mobile header toggle only when navigation is visible on the current page', function (string $state) {
    $plugin = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID);
    if ($state === 'absent') {
        $plugin->mobileNavigation(null);
    } else {
        $plugin->getMobileNavigation()->visible(fn (?string $routeName, array $routeParameters): bool => $state === 'visible' && $routeName === 'filament.app.pages.dashboard'
            && $routeParameters['tenant'] === (string) $this->team->getKey());
    }

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $header = Str::betweenFirst($html, 'class="polder-theme-breadcrumb-bar', 'class="fi-header');

    expect($header)->toContain('polder-theme-sidebar-toggle')
        ->not->toContain('fi-user-menu-trigger');
    expect(str_contains($header, 'polder-theme-sidebar-toggle-desktop-only'))->toBe($state === 'visible');
})->with(['visible', 'hidden', 'absent']);

it('renders an escaped icon badge on links actions and sheets with page-aware colours', function (string $type) {
    $item = match ($type) {
        'link' => MobileNavigationItem::link('messages')->url('/messages'),
        'action' => MobileNavigationItem::action(fn (): Action => Action::make('messages')),
        'sheet' => MobileNavigationItem::sheet('messages'),
    };
    $item->badge(fn (?string $routeName): string => $routeName === 'filament.app.pages.dashboard' ? '<12>' : 'wrong page')
        ->badgeColor(fn (array $routeParameters): string => $routeParameters['tenant'] === (string) $this->team->getKey() ? 'danger' : 'gray');
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([$item]);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $badge = (new DOMXPath($dom))->query('//span[contains(@class, "polder-theme-mobile-navigation-icon")]/span[contains(@class, "polder-theme-mobile-navigation-badge")]');

    expect($badge)->toHaveCount(1);
    expect(trim($badge->item(0)->textContent))->toBe('<12>');
    expect($badge->item(0)->getAttribute('class'))->toContain('fi-color-danger');
    expect($html)->toContain('&lt;12&gt;')->not->toContain('<12>');
})->with(['link', 'action', 'sheet']);

it('omits empty and zero badges and uses primary for a visible badge by default', function (string|int|null $value) {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()->items([
        MobileNavigationItem::link('messages')->url('/messages')->badge($value),
    ]);

    $response = get('/app/'.$this->team->getKey())->assertOk();

    if ($value === 5) {
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $badge = (new DOMXPath($dom))->query('//span[contains(@class, "polder-theme-mobile-navigation-badge")]');
        expect($badge)->toHaveCount(1);
        expect($badge->item(0)->getAttribute('class'))->toContain('fi-color-primary');
    } else {
        $response->assertDontSee('polder-theme-mobile-navigation-badge');
    }
})->with([null, 0, '0', '', 5]);

it('renders each sheet as a bottom sheet element that the items open and close', function () {
    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $sheets = $xpath->query('//div[@data-bottom-sheet-id]');

    expect(collect($sheets)->map(fn (DOMElement $sheet): string => $sheet->getAttribute('data-bottom-sheet-id'))->all())
        ->toBe(['polder-theme-polderItem2', 'polder-theme-polderItem3', 'polder-theme-polderItem4']);

    foreach ($sheets as $sheet) {
        expect($sheet->hasAttribute('x-bottom-sheet:element'))->toBeTrue()
            ->and($sheet->getAttribute('data-bottom-sheet-initial-height'))->toBe('content')
            ->and($sheet->hasAttribute('wire:ignore.self'))->toBeTrue()
            ->and($sheet->hasAttribute('data-bottom-sheet-overlay'))->toBeFalse()
            ->and($xpath->query('./div[@data-bottom-sheet-content]', $sheet)->length)->toBe(1);
    }

    expect($xpath->query('//button[@aria-haspopup="dialog"]')->item(0)->getAttribute('x-on:click'))
        ->toBe("\$dispatch('bottom-sheet:open', { id: 'polder-theme-polderItem2' })");
    expect($xpath->query('//*[@data-bottom-sheet-id="polder-theme-polderItem2"]//button[contains(@class, "polder-theme-mobile-navigation-tile")]')->item(0)->getAttribute('x-on:click'))
        ->toBe("\$dispatch('bottom-sheet:close', { id: 'polder-theme-polderItem2' })");
    expect($xpath->query('//*[@data-bottom-sheet-id="polder-theme-polderItem3"]//a')->item(0)->getAttribute('x-on:click'))
        ->toBe("\$dispatch('bottom-sheet:close', { id: 'polder-theme-polderItem3' })");
    expect($xpath->query('//*[@data-bottom-sheet-id="polder-theme-polderItem4"]//*[contains(@class, "fi-dropdown-list")]')->item(0)->getAttribute('x-on:click'))
        ->toContain("\$dispatch('bottom-sheet:close', { id: 'polder-theme-polderItem4' })");
    expect($html)->not->toContain('polder-theme-mobile-navigation-sheet-backdrop')
        ->not->toContain('polder-theme-mobile-navigation-sheet-handle')
        ->not->toContain('x-on:click="sheet');
});

it('hides empty breadcrumb bars only where their breadcrumbs cannot show', function (string $page, string $hideClass) {
    get('/app/'.$this->team->getKey().$page)->assertOk()->assertSee($hideClass);
})->with([
    'dashboard below lg' => ['', 'polder-theme-breadcrumb-bar-empty-mobile'],
    'breadcrumbs below sm' => ['/breadcrumb-page', 'polder-theme-breadcrumb-bar-empty-phone'],
]);

it('keeps breadcrumb bars with actual scoped hook output and renders it once', function (string $hook, string $output, bool $empty, string $page, string $scope, string $hideClass) {
    $calls = 0;
    FilamentView::registerRenderHook($hook, function () use ($output, &$calls): string {
        $calls++;

        return $output;
    }, scopes: $scope);

    $html = get('/app/'.$this->team->getKey().$page)->assertOk()->getContent();
    expect(str_contains($html, $hideClass))->toBe($empty)
        ->and($calls)->toBe(1);
})->with([PolderRenderHook::BREADCRUMB_BAR_CONTENT, PolderRenderHook::BREADCRUMB_BAR_END])
    ->with(['content' => ['Broadcast status', false], 'empty' => [" \n<!-- empty -->", true]])
    ->with([
        'dashboard' => ['', Dashboard::class, 'polder-theme-breadcrumb-bar-empty-mobile'],
        'breadcrumbs' => ['/breadcrumb-page', BreadcrumbPage::class, 'polder-theme-breadcrumb-bar-empty-phone'],
    ]);

it('keeps the breadcrumb bar when mobile navigation is hidden or absent', function (bool $absent) {
    $plugin = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID);
    $absent ? $plugin->mobileNavigation(null) : $plugin->getMobileNavigation()->visible(false);

    get('/app/'.$this->team->getKey())->assertOk()->assertDontSee('polder-theme-breadcrumb-bar-empty-');
})->with([true, false]);

it('makes icons only optional and page aware while preserving accessible labels and sheet contents', function (bool $enabled) {
    $navigation = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation();
    expect($navigation->isIconsOnly())->toBeFalse();
    $navigation->iconsOnly(fn (?string $routeName): bool => $enabled && $routeName === 'filament.app.pages.dashboard');
    $navigation->items([
        MobileNavigationItem::link('inbox')->label('Inbox')->icon(Heroicon::OutlinedInbox)->url('/inbox')->badge(3),
        MobileNavigationItem::link('disabled')->label('Disabled')->disabled(),
        MobileNavigationItem::action(fn (): Action => Action::make('scan')->label('Scan')),
        MobileNavigationItem::sheet('tools')->label('Tools')->items([MobileNavigationItem::link('export')->label('Export')->url('/export')]),
    ])->centerAction(fn (): Action => Action::make('create')->label('Create'), MobileNavigationButton::Flat);

    $html = get('/app/'.$this->team->getKey())->assertOk()->getContent();
    $bar = Str::betweenFirst(Str::after($html, 'class="polder-theme-mobile-navigation polder-theme-mobile-navigation-'), '<nav', '</nav>');
    expect(str_contains($bar, 'polder-theme-mobile-navigation-icons-only'))->toBe($enabled)
        ->and(str_contains($bar, 'polder-theme-mobile-navigation-label'))->toBe(! $enabled)
        ->and($bar)->toContain('polder-theme-mobile-navigation-center-flat', 'polder-theme-mobile-navigation-badge')
        ->and($html)->toContain('aria-label="Tools"', '>Export</span>');
    if ($enabled) {
        expect($bar)->toContain('aria-label="Inbox"', 'aria-label="Disabled"', 'aria-label="Scan"', 'aria-label="Tools"', 'aria-label="Create"');
        expect(Str::after(file_get_contents(package_path('resources/css/theme.css')), '.polder-theme-mobile-navigation-icons-only {'))
            ->toMatch('/& \.polder-theme-mobile-navigation-center-flat\s*\{[^{}]*& \.polder-theme-mobile-navigation-icon\s*\{\s*@apply size-7 bg-transparent text-primary-600 dark:bg-transparent dark:text-primary-400;/');
    }
})->with([true, false]);

it('renders an accessible mobile back link before the heading using the last linked breadcrumb', function () {
    $html = get('/app/'.$this->team->getKey().'/breadcrumb-page')->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $buttons = $xpath->query('//header//a[contains(@class, "polder-theme-back-button")]');

    expect($buttons)->toHaveCount(1);
    $button = $buttons->item(0);
    expect($button->getAttribute('href'))->toBe('/somewhere');
    expect($button->getAttribute('aria-label'))->toBe('Back');
    expect($xpath->query('following-sibling::h1', $button))->toHaveCount(1);
    expect($button->getAttribute('x-on:click'))->toContain('document.referrer', 'window.location.origin', 'window.history.length > 1', 'window.history.back()');
});

it('falls back to the tenant panel home when no breadcrumb links are shown', function () {
    Filament::getPanel('app')->breadcrumbs(false);

    $html = get('/app/'.$this->team->getKey().'/breadcrumb-page')->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $button = (new DOMXPath($dom))->query('//header//a[contains(@class, "polder-theme-back-button")]')->item(0);

    expect($button->getAttribute('href'))->toBe(url('/app/'.$this->team->getKey()));
});

it('omits the back button on the dashboard and a configured panel home', function () {
    get('/app/'.$this->team->getKey())->assertOk()->assertDontSee('polder-theme-back-button');

    Filament::getPanel('app')->homeUrl(url('/app/'.$this->team->getKey().'/breadcrumb-page'));

    get('/app/'.$this->team->getKey().'/breadcrumb-page')->assertOk()->assertDontSee('polder-theme-back-button');
});

it('omits the back button when disabled or mobile navigation is hidden or absent', function (string $state) {
    $plugin = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID);
    match ($state) {
        'disabled' => $plugin->getMobileNavigation()->backButton(false),
        'hidden' => $plugin->getMobileNavigation()->visible(false),
        'absent' => $plugin->mobileNavigation(null),
    };

    get('/app/'.$this->team->getKey().'/breadcrumb-page')->assertOk()->assertDontSee('polder-theme-back-button');
})->with(['disabled', 'hidden', 'absent']);

it('evaluates the back button condition with the current page context', function (bool $enabled) {
    Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID)->getMobileNavigation()
        ->backButton(fn (?string $routeName, array $routeParameters): bool => $enabled
            && $routeName === 'filament.app.pages.breadcrumb-page'
            && $routeParameters['tenant'] === (string) $this->team->getKey());

    $response = get('/app/'.$this->team->getKey().'/breadcrumb-page')->assertOk();

    $enabled ? $response->assertSee('polder-theme-back-button') : $response->assertDontSee('polder-theme-back-button');
})->with([true, false]);

it('puts the header actions on the heading row as buttons and as one menu with the bar', function () {
    PolderHeaderActionsPage::$actions = fn (): array => [
        Action::make('requestPayout')->label('Request payout')->color('primary')->keyBindings(['mod+p'])
            ->requiresConfirmation()->modalHeading('Confirm the payout')->action(fn () => $this->ran = 'payout'),
    ];

    $page = headerActionsPage();
    $html = $page->html();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $header = $xpath->query('//header[contains(@class, "fi-header")]')->item(0);

    expect($header->getAttribute('class'))->toContain('polder-theme-compact-header', 'polder-theme-header-actions-inline')
        ->not->toContain('polder-theme-header-actions-menu ')
        ->and($header->getAttribute('x-data'))->toContain('isInline: true', 'scrollWidth', 'naturalWidth(container, this.$refs.menu)')
        ->not->toContain('0.4')
        ->and($header->getAttribute('x-init'))->toContain('ResizeObserver', 'document.fonts')
        ->and($xpath->query('.//*[contains(@class, "polder-theme-heading-row")]/h1', $header))->toHaveCount(1)
        ->and($xpath->query('.//*[contains(@class, "fi-header-actions-ctn")]/*[@x-ref="actions"]//button[contains(@class, "fi-btn")]', $header))->toHaveCount(1)
        ->and($xpath->query('.//*[@x-ref="menu"]//*[@aria-label="Actions"]', $header))->toHaveCount(1)
        ->and($xpath->query('.//*[@x-ref="menu"]//*[contains(concat(" ", @class, " "), " fi-dropdown-list-item ")]', $header))->toHaveCount(1)
        ->and(actionTriggers($xpath, 'requestPayout'))->toBe(2)
        ->and(substr_count($html, 'x-mousetrap.global.mod-p'))->toBe(1);

    $page->call('mountAction', 'requestPayout')->assertActionMounted('requestPayout');
    expect($page->get('mountedActions'))->toHaveCount(1);

    $page->callMountedAction();
    expect($this->ran)->toBe('payout');
});

it('starts several header actions in the menu and keeps a page group a group', function () {
    PolderHeaderActionsPage::$actions = fn (): array => [
        Action::make('first')->label('First')->icon(Heroicon::OutlinedStar),
        Action::make('hidden')->label('Hidden')->hidden(),
        ActionGroup::make([
            Action::make('second')->label('Second'),
            Action::make('third')->label('Third'),
        ])->label('More things'),
    ];

    $dom = new DOMDocument;
    @$dom->loadHTML(headerActionsPage()->html());
    $xpath = new DOMXPath($dom);
    $header = $xpath->query('//header[contains(@class, "fi-header")]')->item(0);
    $menu = $xpath->query('.//*[@x-ref="menu"]', $header)->item(0);

    expect($header->getAttribute('class'))->toContain('polder-theme-header-actions-menu')
        ->and($header->getAttribute('x-data'))->toContain('isInline: false')
        ->and($menu->textContent)->toContain('First', 'More things', 'Second', 'Third')->not->toContain('Hidden')
        ->and(strpos($menu->textContent, 'First'))->toBeLessThan(strpos($menu->textContent, 'More things'))
        ->and($xpath->query('.//*[contains(@class, "fi-dropdown")]//*[contains(@class, "fi-dropdown")]', $menu)->length)->toBeGreaterThan(0);
});

it('keeps the header actions hooks in the actions container with the menu after them', function () {
    FilamentView::registerRenderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, fn (): string => '<span id="polder-before">Before</span>');
    FilamentView::registerRenderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, fn (): string => '<span id="polder-after">After</span>');
    PolderHeaderActionsPage::$actions = fn (): array => [Action::make('first'), Action::make('second')];

    $html = headerActionsPage()->html();

    expect(substr_count($html, 'id="polder-before"'))->toBe(1)
        ->and(substr_count($html, 'id="polder-after"'))->toBe(1)
        ->and($html)->toMatch('/id="polder-before".*x-ref="actions".*id="polder-after".*x-ref="menu"/s');
});

it('keeps the stock header actions without the bar or with compact header actions off', function (string $state) {
    $plugin = Filament::getPanel('app')->getPlugin(PolderThemePlugin::ID);
    match ($state) {
        'disabled' => $plugin->getMobileNavigation()->compactHeaderActions(false),
        'page-aware' => $plugin->getMobileNavigation()->compactHeaderActions(fn (?string $routeName): bool => $routeName === 'elsewhere'),
        'hidden' => $plugin->getMobileNavigation()->visible(false),
        'absent' => $plugin->mobileNavigation(null),
    };
    PolderHeaderActionsPage::$actions = fn (): array => [Action::make('first')->label('First')];

    $html = headerActionsPage()->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    expect($html)->toContain('fi-header-actions-ctn', 'First')
        ->not->toContain('polder-theme-compact-header', 'x-ref="menu"', 'x-ref="actions"', 'polder-theme-header-actions-inline')
        ->and(actionTriggers(new DOMXPath($dom), 'first'))->toBe(1);
})->with(['disabled', 'page-aware', 'hidden', 'absent']);

it('leaves a page without header actions as it was', function () {
    PolderHeaderActionsPage::$actions = fn (): array => [];

    expect(headerActionsPage()->html())->not->toContain('polder-theme-compact-header', 'x-ref="menu"');
});

function headerActionsPage(): Testable
{
    Filament::setCurrentPanel('app');
    Filament::setTenant(test()->team);

    return livewire(PolderHeaderActionsPage::class);
}

function actionTriggers(DOMXPath $xpath, string $name): int
{
    return $xpath->query("//*[@*[name()='wire:click' and contains(., \"mountAction('{$name}'\")]]")->length;
}

class PolderHeaderActionsPage extends BreadcrumbPage
{
    public static ?Closure $actions = null;

    protected function getHeaderActions(): array
    {
        return (static::$actions)();
    }
}
