<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee->user->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 5px 0; color: #666; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px 0; }
        .section { margin-bottom: 15px; }
        .section-title { font-weight: bold; background: #f0f0f0; padding: 8px; margin-bottom: 5px; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table td { padding: 5px; border-bottom: 1px solid #eee; }
        .detail-table .label { width: 60%; }
        .detail-table .amount { text-align: right; width: 40%; }
        .total-row { font-weight: bold; background: #f9f9f9; }
        .net-income { font-size: 14px; font-weight: bold; background: #e8f5e9; }
        .footer { margin-top: 30px; text-align: center; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SLIP GAJI / PAYSLIP</h1>
        <p>Periode: {{ $batch->period_start_date->format('d M Y') }} - {{ $batch->period_end_date->format('d M Y') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Nama / Name:</strong> {{ $employee->user->name }}</td>
            <td><strong>NIK:</strong> {{ $employee->nik ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal Bayar / Pay Date:</strong> {{ $batch->pay_date->format('d M Y') }}</td>
            <td><strong>NPWP:</strong> {{ $employee->npwp_number ?? '-' }}</td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">PENDAPATAN / EARNINGS</div>
        <table class="detail-table">
            <tr>
                <td class="label">Gaji Pokok / Basic Salary</td>
                <td class="amount">Rp {{ number_format($record->basic_salary, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Tunjangan Tetap / Fixed Allowances</td>
                <td class="amount">Rp {{ number_format($record->total_fixed_allowances, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Tunjangan Tidak Tetap / Variable Allowances</td>
                <td class="amount">Rp {{ number_format($record->total_variable_allowances, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Total Pendapatan Kotor / Gross Income</td>
                <td class="amount">Rp {{ number_format($record->gross_income, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">POTONGAN / DEDUCTIONS</div>
        <table class="detail-table">
            <tr>
                <td class="label">BPJS Kesehatan (Karyawan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_kesehatan_employee, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">BPJS JHT (Karyawan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_jht_employee, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">BPJS JP (Karyawan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_jp_employee, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">PPh 21</td>
                <td class="amount">Rp {{ number_format($record->pph21_amount, 0, ',', '.') }}</td>
            </tr>
            @if($record->other_deductions > 0)
            <tr>
                <td class="label">Potongan Lainnya / Other Deductions</td>
                <td class="amount">Rp {{ number_format($record->other_deductions, 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="label">Total Potongan / Total Deductions</td>
                <td class="amount">Rp {{ number_format($record->total_deductions, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">KONTRIBUSI PERUSAHAAN / COMPANY CONTRIBUTIONS</div>
        <table class="detail-table">
            <tr>
                <td class="label">BPJS Kesehatan (Perusahaan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_kesehatan_company, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">BPJS JHT (Perusahaan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_jht_company, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">BPJS JP (Perusahaan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_jp_company, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">BPJS JKK (Perusahaan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_jkk_company, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">BPJS JKM (Perusahaan)</td>
                <td class="amount">Rp {{ number_format($record->bpjs_jkm_company, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="detail-table">
            <tr class="net-income">
                <td class="label">GAJI BERSIH / NET INCOME</td>
                <td class="amount">Rp {{ number_format($record->net_income, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis dan sah tanpa tanda tangan.</p>
        <p>This document is automatically generated and valid without signature.</p>
    </div>
</body>
</html>
