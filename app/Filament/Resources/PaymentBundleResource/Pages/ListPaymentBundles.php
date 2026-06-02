<?php

namespace App\Filament\Resources\PaymentBundleResource\Pages;

use App\Filament\Resources\PaymentBundleResource;
use App\Models\Hris\PaymentBundle;
use App\Services\Hris\PaymentBundleService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPaymentBundles extends ListRecords
{
    protected static string $resource = PaymentBundleResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'draft' => Tab::make('Payment Bundles')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PaymentBundle::STATUS_DRAFT))
                ->badge(fn () => PaymentBundle::where('status', PaymentBundle::STATUS_DRAFT)->count()),

            'pending_signatures' => Tab::make('Pending Signatures')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    PaymentBundle::STATUS_SUBMITTED_FOR_SIGNATURE,
                    PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
                    PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2,
                    PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE,
                ]))
                ->badge(fn () => PaymentBundle::whereIn('status', [
                    PaymentBundle::STATUS_SUBMITTED_FOR_SIGNATURE,
                    PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
                    PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2,
                    PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE,
                ])->count()),

            'final_payment_documents' => Tab::make('Final Payment Documents')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereNotNull('signed_pdf_attachment_id'))
                ->badge(fn () => PaymentBundle::whereNotNull('signed_pdf_attachment_id')->count()),

            'authorized_for_payment' => Tab::make('Authorized for Payment')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT))
                ->badge(fn () => PaymentBundle::where('status', PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT)->count()),

            'payment_recording' => Tab::make('Payment Recording')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PaymentBundle::STATUS_PAYMENT_RECORDING))
                ->badge(fn () => PaymentBundle::where('status', PaymentBundle::STATUS_PAYMENT_RECORDING)->count()),

            'paid' => Tab::make('Paid Documents')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PaymentBundle::STATUS_PAID))
                ->badge(fn () => PaymentBundle::where('status', PaymentBundle::STATUS_PAID)->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        if (! PaymentBundleResource::financeCan('payment_bundles.create')) {
            return [];
        }

        return [
            Actions\Action::make('create_bundle')
                ->label('New Bundle')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('title')
                        ->label('Bundle Title'),
                    Forms\Components\DatePicker::make('period_start')
                        ->label('Period Start'),
                    Forms\Components\DatePicker::make('period_end')
                        ->label('Period End'),
                ])
                ->action(function (array $data) {
                    PaymentBundleResource::runStatic(
                        fn () => app(PaymentBundleService::class)->create(auth()->user(), $data),
                        'Payment bundle created'
                    );
                }),
        ];
    }
}
