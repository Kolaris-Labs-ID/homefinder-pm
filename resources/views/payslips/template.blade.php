<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee->user->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 15px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .header .company { font-size: 14px; font-weight: bold; margin-bottom: 5px; }
        .header p { margin: 3px 0; color: #666; }
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 3px 0; }
        .info-table .label { font-weight: bold; width: 150px; }
        
        /* Two-column layout */
        .two-column { display: table; width: 100%; margin-bottom: 15px; }
        .column { display: table-cell; width: 50%; vertical-align: top; }
        .column.left { padding-right: 10px; }
        .column.right { padding-left: 10px; border-left: 1px solid #ddd; }
        
        .section { margin-bottom: 10px; }
        .section-title { font-weight: bold; background: #4a90d9; color: white; padding: 6px 8px; margin-bottom: 5px; font-size: 11px; }
        .section-title.deduction { background: #e74c3c; }
        .section-title.company { background: #27ae60; }
        
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table td { padding: 4px 5px; border-bottom: 1px solid #eee; }
        .detail-table .label { width: 65%; }
        .detail-table .amount { text-align: right; width: 35%; font-family: 'Courier New', monospace; }
        .detail-table .sub-item { padding-left: 15px; color: #666; font-size: 10px; }
        
        .total-row { font-weight: bold; background: #f5f5f5; }
        .net-income { font-size: 13px; font-weight: bold; background: #27ae60; color: white; }
        
        .prorate-info { background: #fff3cd; padding: 8px; margin-bottom: 15px; border: 1px solid #ffc107; border-radius: 4px; }
        .prorate-info .title { font-weight: bold; margin-bottom: 5px; }
        .prorate-info .detail { display: inline-block; margin-right: 20px; }
        
        .bpjs-note { background: #d4edda; padding: 6px 8px; margin-bottom: 10px; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 10px; }
        
        .footer { margin-top: 20px; text-align: center; color: #999; font-size: 9px; border-top: 1px solid #ddd; padding-top: 10px; }
        .signature-area { margin-top: 30px; display: table; width: 100%; }
        .signature-box { display: table-cell; width: 33%; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ config('app.name', 'Company Name') }}</div>
        <h1>SLIP GAJI / PAYSLIP</h1>
        <p>Periode: {{ $batch->period_start_date->format('d M Y') }} - {{ $batch->period_end_date->format('d M Y') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Nama / Name:</td>
            <td>{{ $employee->user->name }}</td>
            <td class="label">NIK:</td>
            <td>{{ $employee->nik ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Jabatan / Position:</td>
            <td>{{ $employee->primaryPosition?->position?->name ?? '-' }}</td>
            <td class="label">NPWP:</td>
            <td>{{ $employee->npwp_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Bayar / Pay Date:</td>
            <td>{{ $batch->pay_date->format('d M Y') }}</td>
            <td class="label">Departemen:</td>
            <td>{{ $employee->primaryPosition?->department?->name ?? '-' }}</td>
        </tr>
    </table>

    @if($record->isProrated())
    <div class="prorate-info">
        <div class="title">Informasi Prorate / Prorate Information</div>
        <div class="detail"><strong>Hari Kerja Bulan Ini:</strong> {{ $record->total_working_days }} hari</div>
        <div class="detail"><strong>Hari Kerja Aktual:</strong> {{ $record->actual_days_worked }} hari</div>
        <div class="detail"><strong>Rasio Prorate:</strong> {{ $record->prorate_percentage }}%</div>
    </div>
    @endif

    @if($record->bpjs_company_paid)
    <div class="bpjs-note">
        <strong>Catatan:</strong> BPJS ditanggung sepenuhnya oleh perusahaan (BPJS is fully paid by the company)
    </div>
    @endif

    <div class="two-column">
        <div class="column left">
            <div class="section">
                <div class="section-title">PENGHASILAN / EARNINGS</div>
                <table class="detail-table">
                    @if($record->isProrated())
                    <tr>
                        <td class="label">Gaji Pokok (Full)</td>
                        <td class="amount">Rp {{ number_format($record->full_basic_salary, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label sub-item">Prorata Hari Kerja ({{ $record->prorate_label }})</td>
                        <td class="amount">Rp {{ number_format($record->prorated_basic_salary, 0, ',', '.') }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="label">Gaji Pokok / Basic Salary</td>
                        <td class="amount">Rp {{ number_format($record->basic_salary, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    @if($record->daily_allowance_total > 0)
                    <tr>
                        <td class="label">Uang Harian ({{ $record->actual_days_worked }} hari)</td>
                        <td class="amount">Rp {{ number_format($record->daily_allowance_total, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    @if($record->total_fixed_allowances > 0)
                    <tr>
                        <td class="label">Tunjangan Tetap</td>
                        <td class="amount">Rp {{ number_format($record->total_fixed_allowances, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    @if(!empty($record->details['allowances_fixed']))
                        @foreach($record->details['allowances_fixed'] as $allowance)
                        <tr>
                            <td class="label sub-item">{{ $allowance['name'] }}</td>
                            <td class="amount">Rp {{ number_format($allowance['amount'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    @endif
                    
                    @if($record->total_variable_allowances > 0)
                    <tr>
                        <td class="label">Tunjangan Tidak Tetap</td>
                        <td class="amount">Rp {{ number_format($record->total_variable_allowances, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    @if(!empty($record->details['allowances_variable']))
                        @foreach($record->details['allowances_variable'] as $allowance)
                        <tr>
                            <td class="label sub-item">{{ $allowance['name'] }}</td>
                            <td class="amount">Rp {{ number_format($allowance['calculated_amount'] ?? $allowance['amount'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    @endif
                    
                    <tr class="total-row">
                        <td class="label">Total Penghasilan Kotor</td>
                        <td class="amount">Rp {{ number_format($record->gross_income, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="column right">
            <div class="section">
                <div class="section-title deduction">POTONGAN / DEDUCTIONS</div>
                <table class="detail-table">
                    @if(!$record->bpjs_company_paid)
                    <tr>
                        <td class="label">BPJS Kesehatan</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_kesehatan_employee, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">BPJS JHT</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_jht_employee, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">BPJS JP</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_jp_employee, 0, ',', '.') }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="label sub-item">BPJS (Ditanggung Perusahaan)</td>
                        <td class="amount">Rp 0</td>
                    </tr>
                    @endif
                    
                    <tr>
                        <td class="label">PPh 21</td>
                        <td class="amount">Rp {{ number_format($record->pph21_amount, 0, ',', '.') }}</td>
                    </tr>
                    
                    @if($record->other_deductions > 0)
                    <tr>
                        <td class="label">Potongan Lainnya</td>
                        <td class="amount">Rp {{ number_format($record->other_deductions, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    @if(!empty($record->details['deductions']))
                        @foreach($record->details['deductions'] as $deduction)
                        <tr>
                            <td class="label sub-item">{{ $deduction['name'] }}</td>
                            <td class="amount">Rp {{ number_format($deduction['amount'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    @endif
                    
                    <tr class="total-row">
                        <td class="label">Total Potongan</td>
                        <td class="amount">Rp {{ number_format($record->total_deductions, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <div class="section-title company">KONTRIBUSI PERUSAHAAN</div>
                <table class="detail-table">
                    <tr>
                        <td class="label">BPJS Kesehatan</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_kesehatan_company, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">BPJS JHT</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_jht_company, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">BPJS JP</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_jp_company, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">BPJS JKK</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_jkk_company, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">BPJS JKM</td>
                        <td class="amount">Rp {{ number_format($record->bpjs_jkm_company, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="section">
        <table class="detail-table">
            <tr class="net-income">
                <td class="label" style="padding: 10px;">GAJI BERSIH / NET INCOME</td>
                <td class="amount" style="padding: 10px; font-size: 14px;">Rp {{ number_format($record->net_income, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="signature-area">
        <div class="signature-box">
            <div class="signature-line">Disiapkan Oleh / Prepared By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Disetujui Oleh / Approved By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Diterima Oleh / Received By</div>
        </div>
    </div>

    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis dan sah tanpa tanda tangan.</p>
        <p>This document is automatically generated and valid without signature.</p>
        <p>Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    </div>
</body>
</html>
