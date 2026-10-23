<?php

namespace App\Filament\Pages;

use App\Features\Feature;
use App\Features\FeatureManager;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * First-setup screen for the company's feature flags. Flags already set are shown
 * read-only; the ones not set yet can be chosen once and are then locked.
 * Changing a locked flag is deliberately not possible here (features:change).
 */
class CompanyFeatures extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Company Features';

    protected static ?string $title = 'Company Features';

    protected static string $view = 'filament.pages.company-features';

    public ?array $data = [];

    public function mount(): void
    {
        $features = app(FeatureManager::class);

        $this->form->fill(collect($features->pending())->mapWithKeys(
            fn (Feature $feature) => [$this->field($feature) => $features->active($feature)]
        )->all());
    }

    public function form(Form $form): Form
    {
        $pending = app(FeatureManager::class)->pending();

        return $form
            ->schema($pending === [] ? [] : [
                Section::make('Not set yet')
                    ->description('Choose each feature once. After you lock them, a change needs a reason and a server command, so pick what this company actually does.')
                    ->schema(collect($pending)->map(
                        fn (Feature $feature) => Toggle::make($this->field($feature))
                            ->label($feature->label())
                            ->helperText($feature->description())
                    )->all()),
            ])
            ->statePath('data');
    }

    /** @return list<array{feature: Feature, on: bool, set_at: ?string, set_by: string}> */
    public function getLockedFeatures(): array
    {
        $features = app(FeatureManager::class);

        return collect(Feature::cases())
            ->filter(fn (Feature $feature) => $features->isInitialized($feature))
            ->map(function (Feature $feature) use ($features) {
                $record = $features->record($feature);

                return [
                    'feature' => $feature,
                    'on' => $features->active($feature),
                    'set_at' => $record->initialized_at?->format('j M Y'),
                    'set_by' => $this->describeSource($record->source),
                ];
            })
            ->values()
            ->all();
    }

    public function hasPendingFeatures(): bool
    {
        return app(FeatureManager::class)->pending() !== [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lock')
                ->label('Save and lock')
                ->visible(fn () => $this->hasPendingFeatures())
                ->requiresConfirmation()
                ->modalHeading('Lock these feature choices?')
                ->modalDescription('They apply to everyone in this company. Changing one later needs a reason and a server command.')
                ->modalSubmitActionLabel('Lock')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $features = app(FeatureManager::class);
        $state = $this->form->getState();

        $choices = collect($features->pending())->mapWithKeys(
            fn (Feature $feature) => [$feature->value => (bool) ($state[$this->field($feature)] ?? false)]
        )->all();

        $written = $features->initialize($choices, 'user:'.auth()->id());

        Notification::make()
            ->title(count($written).' feature '.str('choice')->plural(count($written)).' locked')
            ->success()
            ->send();

        $this->data = [];
        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    // Form state keys can't contain dots, which Feature values do.
    private function field(Feature $feature): string
    {
        return str_replace('.', '__', $feature->value);
    }

    private function describeSource(string $source): string
    {
        if (str_starts_with($source, 'preset:')) {
            return 'the '.substr($source, 7).' preset';
        }

        if (str_starts_with($source, 'user:')) {
            return User::find((int) substr($source, 5))?->name ?? 'a deleted user';
        }

        return 'a server command';
    }
}
