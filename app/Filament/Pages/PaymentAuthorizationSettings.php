<?php

namespace App\Filament\Pages;

use App\Models\Hris\PaymentAuthorizationSetting;
use App\Models\User;
use App\Services\Hris\PaymentAuthorizationSettingsService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class PaymentAuthorizationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?string $navigationLabel = 'Payment Authorization Settings';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.payment-authorization-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = PaymentAuthorizationSetting::current();

        $this->form->fill([
            'finance_signer_1_user_id' => $settings->finance_signer_1_user_id,
            'finance_signer_2_user_id' => $settings->finance_signer_2_user_id,
            'executive_signer_user_id' => $settings->executive_signer_user_id,
            'require_two_finance_signatures' => $settings->require_two_finance_signatures,
            'require_executive_signature' => $settings->require_executive_signature,
            'high_value_threshold' => $settings->high_value_threshold,
            'allow_creator_to_sign' => $settings->allow_creator_to_sign,
            'allow_executor_to_sign' => $settings->allow_executor_to_sign,
        ]);
    }

    public function form(Form $form): Form
    {
        $financeOptions = fn () => User::permission('payment_bundles.sign.finance')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $executiveOptions = fn () => User::permission('payment_bundles.sign.executive')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return $form
            ->schema([
                Section::make('Finance Signers')
                    ->columns(2)
                    ->schema([
                        Select::make('finance_signer_1_user_id')
                            ->label('Finance Signer 1')
                            ->options($financeOptions)
                            ->searchable(),

                        Select::make('finance_signer_2_user_id')
                            ->label('Finance Signer 2')
                            ->options($financeOptions)
                            ->searchable(),

                        Toggle::make('require_two_finance_signatures')
                            ->label('Require Two Finance Signatures')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Executive Signer')
                    ->schema([
                        Select::make('executive_signer_user_id')
                            ->label('Executive Signer')
                            ->options($executiveOptions)
                            ->searchable(),

                        Toggle::make('require_executive_signature')
                            ->label('Require Executive Signature')
                            ->default(true),
                    ]),

                Section::make('Threshold & Signing Restrictions')
                    ->columns(2)
                    ->schema([
                        TextInput::make('high_value_threshold')
                            ->label('High-value Threshold')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp'),

                        Toggle::make('allow_creator_to_sign')
                            ->label('Allow Creator to Sign')
                            ->columnSpanFull(),

                        Toggle::make('allow_executor_to_sign')
                            ->label('Allow Executor to Sign')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $model = PaymentAuthorizationSetting::current();
        $model->fill($state);

        try {
            app(PaymentAuthorizationSettingsService::class)->validate($model);
        } catch (\RuntimeException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        $model->save();

        Notification::make()
            ->success()
            ->title('Payment authorization settings saved.')
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo('payment_bundles.manage_signers', 'web');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }
}
