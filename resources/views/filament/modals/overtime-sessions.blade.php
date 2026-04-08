<div class="space-y-3">
    @if(!empty($payment->sessions_breakdown))
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-1 pr-3">Task</th>
                    <th class="text-left py-1 pr-3">Date</th>
                    <th class="text-right py-1 pr-3">Duration</th>
                    <th class="text-right py-1">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->sessions_breakdown as $session)
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3">{{ $session['task_name'] ?? '-' }}</td>
                    <td class="py-1 pr-3">
                        {{ isset($session['start_time']) ? \Carbon\Carbon::parse($session['start_time'])->format('d M Y') : '-' }}
                    </td>
                    <td class="py-1 pr-3 text-right">
                        {{ isset($session['duration_minutes']) ? number_format($session['duration_minutes'] / 60, 1) . 'h' : '-' }}
                    </td>
                    <td class="py-1 text-right">
                        Rp {{ number_format($session['pay'] ?? 0, 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <td colspan="3" class="py-2 pr-3">Total</td>
                    <td class="py-2 text-right">Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <p class="text-gray-500 text-sm">No session breakdown available.</p>
    @endif
</div>
