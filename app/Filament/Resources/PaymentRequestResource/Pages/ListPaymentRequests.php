<?php

namespace App\Filament\Resources\PaymentRequestResource\Pages;

use App\Filament\Resources\PaymentRequestResource;
use App\Models\Hris\FinanceAttachment;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListPaymentRequests extends ListRecords
{
    protected static string $resource = PaymentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createManual')
                ->label('New Manual PR')
                ->icon('heroicon-o-plus')
                ->visible(fn () => PaymentRequestResource::financeCan('payment_requests.create'))
                ->slideOver()
                ->modalHeading('Create Manual Payment Request')
                ->modalSubmitActionLabel('Create')
                ->form([
                    Forms\Components\Section::make('Payee & amount')->columns(2)->schema([
                        Forms\Components\TextInput::make('payee_name')->required(),
                        Forms\Components\TextInput::make('bank_name'),
                        Forms\Components\TextInput::make('bank_account_number'),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()->required()->rules(['gt:0'])->prefix('IDR'),
                        Forms\Components\Textarea::make('description')->columnSpanFull(),
                        Forms\Components\DatePicker::make('due_date'),
                    ]),
                    Forms\Components\Section::make('Withholding')->columns(2)->schema([
                        Forms\Components\Toggle::make('withholding_required')->columnSpanFull(),
                        Forms\Components\TextInput::make('withholding_type'),
                        Forms\Components\Textarea::make('tax_notes')->columnSpanFull(),
                    ]),
                    Forms\Components\Section::make('Currency')->columns(2)->schema([
                        Forms\Components\TextInput::make('currency')->default('IDR')->required()->maxLength(3),
                        Forms\Components\TextInput::make('original_amount')->numeric(),
                        Forms\Components\TextInput::make('fx_rate')->numeric(),
                    ]),
                    Forms\Components\Section::make('Document')->schema([
                        Forms\Components\FileUpload::make('invoice')
                            ->label('Invoice / supporting document')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->storeFiles(false),
                    ]),
                ])
                ->action(function (array $data) {
                    try {
                        $invoice = $data['invoice'] ?? null;
                        unset($data['invoice']);

                        DB::transaction(function () use ($data, $invoice) {
                            $pr = app(PaymentRequestService::class)->createManual(auth()->user(), $data);

                            if ($invoice) {
                                app(FinanceAttachmentService::class)->store(
                                    $pr, $invoice, FinanceAttachment::ROLE_SUPPORTING_DOCUMENT, auth()->user()
                                );
                            }
                        });

                        Notification::make()->title('Manual payment request created')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Create failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
