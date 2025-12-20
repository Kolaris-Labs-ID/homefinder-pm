<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class EditProfile extends BaseEditProfile
{
    public ?array $mobilePasswordData = [];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Profile Information'))
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                Section::make(__('Update Password'))
                    ->description(__('Ensure your account is using a long, random password to stay secure.'))
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ]),
            ]);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->model($this->getUser())
                    ->statePath('data'),
            ),
            'mobilePasswordForm' => $this->mobilePasswordForm(
                $this->makeForm()
                    ->model($this->getUser())
                    ->statePath('mobilePasswordData'),
            ),
        ];
    }

    public function mobilePasswordForm(Form $form): Form
    {
        $user = $this->getUser();
        $hasPassword = !empty($user->getAuthPassword());
        $isGoogleUser = !empty($user->google_id);

        return $form
            ->schema([
                Section::make(__('Mobile App Password'))
                    ->description(
                        $isGoogleUser && !$hasPassword
                            ? __('You signed in with Google. Set a password below to enable login from the mobile app.')
                            : __('Set or update your password for mobile app login.')
                    )
                    ->schema([
                        TextInput::make('mobile_password')
                            ->label(__('New Password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->required()
                            ->minLength(8)
                            ->helperText(__('Minimum 8 characters. This password will be used for mobile app login.')),
                        TextInput::make('mobile_password_confirmation')
                            ->label(__('Confirm Password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('mobile_password'),
                    ])
                    ->footerActions([
                        \Filament\Forms\Components\Actions\Action::make('setMobilePassword')
                            ->label($hasPassword ? __('Update Mobile Password') : __('Set Mobile Password'))
                            ->icon($hasPassword ? 'heroicon-o-arrow-path' : 'heroicon-o-device-phone-mobile')
                            ->color($hasPassword ? 'gray' : 'success')
                            ->action(function () {
                                $this->setMobilePassword();
                            }),
                    ])
                    ->footerActionsAlignment(\Filament\Support\Enums\Alignment::End),
            ]);
    }

    public function setMobilePassword(): void
    {
        $data = $this->mobilePasswordForm->getState();

        $user = $this->getUser();
        $user->password = Hash::make($data['mobile_password']);
        $user->save();

        $this->mobilePasswordData = [];

        Notification::make()
            ->title(__('Mobile password set successfully'))
            ->body(__('You can now login to the mobile app using your email and this password.'))
            ->success()
            ->send();
    }

    protected function getPasswordFormComponent(): \Filament\Forms\Components\Component
    {
        return TextInput::make('password')
            ->label(__('New Password'))
            ->password()
            ->revealable()
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): \Filament\Forms\Components\Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('Confirm Password'))
            ->password()
            ->revealable()
            ->required(fn ($get): bool => filled($get('password')))
            ->dehydrated(false);
    }
}
