<x-filament-panels::page>
    {{-- Date Range Filter --}}
    <div class="mb-6">
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>
    </div>

    {{-- Stats Overview Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Headcount --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-primary-100 dark:bg-primary-900">
                    <x-heroicon-o-users class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Headcount</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalHeadcount() }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Turnover Rate --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-danger-100 dark:bg-danger-900">
                    <x-heroicon-o-arrow-trending-down class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Turnover Rate</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->getTurnoverRate(), 1) }}%</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Attendance Rate --}}
        @php $attendanceKPIs = $this->getAttendanceKPIs(); @endphp
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-success-100 dark:bg-success-900">
                    <x-heroicon-o-check-badge class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Attendance Rate</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($attendanceKPIs['attendance_rate'], 1) }}%</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Avg Working Hours --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-full bg-info-100 dark:bg-info-900">
                    <x-heroicon-o-clock class="w-6 h-6 text-info-600 dark:text-info-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg Working Hours</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($attendanceKPIs['average_working_hours'], 1) }}h</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Headcount Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Headcount by Department --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-building-office class="w-5 h-5" />
                    Headcount by Department
                </div>
            </x-slot>
            @php $departmentData = $this->getHeadcountByDepartment(); @endphp
            @if(count($departmentData) > 0)
                <div class="space-y-3">
                    @foreach($departmentData as $dept)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300">{{ $dept['department_name'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $dept['count'] }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                @php $maxCount = max(array_column($departmentData, 'count')); @endphp
                                <div class="bg-primary-600 h-2 rounded-full" style="width: {{ $maxCount > 0 ? ($dept['count'] / $maxCount) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No department data available</p>
            @endif
        </x-filament::section>

        {{-- Headcount by Employment Status --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-briefcase class="w-5 h-5" />
                    Headcount by Employment Status
                </div>
            </x-slot>
            @php $statusData = $this->getHeadcountByEmploymentStatus(); @endphp
            @if(count($statusData) > 0)
                <div class="space-y-3">
                    @foreach($statusData as $status)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700 dark:text-gray-300">{{ $this->getEmploymentStatusLabel($status['employment_status']) }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $status['count'] }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                @php $maxStatusCount = max(array_column($statusData, 'count')); @endphp
                                <div class="bg-success-600 h-2 rounded-full" style="width: {{ $maxStatusCount > 0 ? ($status['count'] / $maxStatusCount) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No employment status data available</p>
            @endif
        </x-filament::section>
    </div>

    {{-- Age and Tenure Distribution --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Age Distribution --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-cake class="w-5 h-5" />
                    Age Distribution
                </div>
            </x-slot>
            @php
                $ageData = $this->getAgeDistribution();
                $ageLabels = $this->getAgeDistributionLabels();
                $maxAge = max($ageData) ?: 1;
            @endphp
            <div class="space-y-3">
                @foreach($ageData as $key => $count)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700 dark:text-gray-300">{{ $ageLabels[$key] ?? $key }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-warning-600 h-2 rounded-full" style="width: {{ ($count / $maxAge) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Tenure Distribution --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-5 h-5" />
                    Tenure Distribution
                </div>
            </x-slot>
            @php
                $tenureData = $this->getTenureDistribution();
                $tenureLabels = $this->getTenureDistributionLabels();
                $maxTenure = max($tenureData) ?: 1;
            @endphp
            <div class="space-y-3">
                @foreach($tenureData as $key => $count)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700 dark:text-gray-300">{{ $tenureLabels[$key] ?? $key }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-info-600 h-2 rounded-full" style="width: {{ ($count / $maxTenure) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    {{-- Employee Leave Usage --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-users class="w-5 h-5" />
                    Employee Leave Usage ({{ $this->leaveYear }})
                </div>
            </x-slot>
            @php $employeeLeaveData = $this->getEmployeeLeaveUsageDetails(); @endphp
            @if(count($employeeLeaveData) > 0)
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white dark:bg-gray-900 z-10">
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 text-gray-600 dark:text-gray-400">Name</th>
                                <th class="text-left py-2 text-gray-600 dark:text-gray-400">Leave Type</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Available Quota</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Used</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Remaining Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employeeLeaveData as $data)
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="py-2 text-gray-900 dark:text-white font-medium">{{ $data['employee_name'] }}</td>
                                    <td class="py-2 text-gray-900 dark:text-white">{{ $data['leave_type_name'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $data['available_quota'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $data['used_quota'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $data['remaining_balance'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No employee leave data available</p>
            @endif
        </x-filament::section>
    </div>

    {{-- Unpaid Leave Stat Card --}}
    @php $unpaidStats = $this->getUnpaidLeaveStats(); @endphp
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                    Unpaid Leave ({{ $this->leaveYear }})
                </div>
            </x-slot>
            @if($unpaidStats['employee_count'] > 0)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ $unpaidStats['employee_count'] }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $unpaidStats['employee_count'] === 1 ? 'employee' : 'employees' }} took unpaid leave
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ $unpaidStats['total_days'] }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            total unpaid days
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ $unpaidStats['request_count'] }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            approved {{ $unpaidStats['request_count'] === 1 ? 'request' : 'requests' }}
                        </div>
                    </div>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No unpaid leave taken this year.</p>
            @endif
        </x-filament::section>
    </div>

    {{-- Leave Usage and Attendance KPIs --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Leave Usage Summary --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar class="w-5 h-5" />
                    Leave Usage Summary ({{ $this->leaveYear }})
                </div>
            </x-slot>
            @php $leaveData = $this->getLeaveUsageSummary(); @endphp
            @if(count($leaveData) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 text-gray-600 dark:text-gray-400">Leave Type</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Used</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Available</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leaveData as $leave)
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 text-gray-900 dark:text-white">{{ $leave['leave_type_name'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $leave['total_used'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $leave['total_available'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">{{ $leave['total_remaining'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No leave data available</p>
            @endif
        </x-filament::section>

        {{-- Attendance KPIs --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-finger-print class="w-5 h-5" />
                    Attendance KPIs
                </div>
            </x-slot>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Attendance Rate</p>
                    <p class="text-xl font-bold text-success-600 dark:text-success-400">{{ number_format($attendanceKPIs['attendance_rate'], 1) }}%</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Late Percentage</p>
                    <p class="text-xl font-bold text-warning-600 dark:text-warning-400">{{ number_format($attendanceKPIs['late_percentage'], 1) }}%</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Avg Working Hours</p>
                    <p class="text-xl font-bold text-info-600 dark:text-info-400">{{ number_format($attendanceKPIs['average_working_hours'], 1) }}h</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Records</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($attendanceKPIs['total_records']) }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Recruitment Metrics --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-user-plus class="w-5 h-5" />
                Recruitment Metrics
            </div>
        </x-slot>
        @php $recruitmentMetrics = $this->getRecruitmentMetrics(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Jobs Created</p>
                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $recruitmentMetrics['job_postings_created'] }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Jobs Published</p>
                <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $recruitmentMetrics['job_postings_published'] }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Applicants</p>
                <p class="text-2xl font-bold text-info-600 dark:text-info-400">{{ $recruitmentMetrics['total_applicants'] }}</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Hired</p>
                <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $recruitmentMetrics['hired_count'] }}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Avg Time to Hire</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $recruitmentMetrics['average_time_to_hire_days'] }} days</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Conversion Rate</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($recruitmentMetrics['conversion_rate'], 1) }}%</p>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Jobs Closed</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $recruitmentMetrics['job_postings_closed'] }}</p>
            </div>
        </div>
    </x-filament::section>

    {{-- ─────────────────────────────────────────────────────────────
         Working Hours Analytics
         ───────────────────────────────────────────────────────────── --}}

    {{-- Section divider --}}
    <div class="my-2">
        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
            <span class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Working Hours Analytics</span>
            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
        </div>
    </div>

    {{-- Employee Filter --}}
    <div class="mb-4">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-funnel class="w-5 h-5" />
                    Filter by Employee
                </div>
            </x-slot>
            <div class="flex items-center gap-4">
                <div class="flex-1 max-w-xs">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee</label>
                    <select
                        wire:model.live="selectedEmployeeId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">— All Employees —</option>
                        @foreach($this->getEmployeeList() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($selectedEmployeeId)
                    <div class="mt-5">
                        <button
                            wire:click="$set('selectedEmployeeId', null)"
                            class="text-sm text-danger-600 dark:text-danger-400 hover:underline"
                        >
                            Clear filter
                        </button>
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>

    {{-- Weekly Working Hours --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-clock class="w-5 h-5" />
                    Weekly Working Hours
                    @if($selectedEmployeeId)
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(filtered by employee)</span>
                    @endif
                </div>
            </x-slot>
            @php $weeklyData = $this->getWeeklyWorkingHoursData(); @endphp
            @if(count($weeklyData) > 0)
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white dark:bg-gray-900 z-10">
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">Week</th>
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">Employee</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Total Hours</th>
                                <th class="py-2 pl-4 w-48 text-gray-600 dark:text-gray-400"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $weeklyMax = max(array_column($weeklyData, 'total_hours')) ?: 1;
                            @endphp
                            @foreach($weeklyData as $row)
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['week_label'] }}</td>
                                    <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                    <td class="py-2 text-right font-semibold
                                        @if($row['total_hours'] >= 40) text-success-600 dark:text-success-400
                                        @elseif($row['total_hours'] >= 30) text-warning-600 dark:text-warning-400
                                        @else text-danger-600 dark:text-danger-400
                                        @endif
                                    ">{{ number_format($row['total_hours'], 1) }}h</td>
                                    <td class="py-2 pl-4">
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-info-500 h-2 rounded-full" style="width: {{ min(($row['total_hours'] / $weeklyMax) * 100, 100) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-600">
                    <x-heroicon-o-clock class="w-10 h-10 mb-2" />
                    <p class="text-sm">No working hours data for the selected period.</p>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Monthly Working Hours --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-5 h-5" />
                    Monthly Working Hours Comparison
                    @if($selectedEmployeeId)
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(filtered by employee)</span>
                    @endif
                </div>
            </x-slot>
            @php $monthlyData = $this->getMonthlyWorkingHoursData(); @endphp
            @if(count($monthlyData) > 0)
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white dark:bg-gray-900 z-10">
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">Month</th>
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">Employee</th>
                                <th class="text-right py-2 text-gray-600 dark:text-gray-400">Total Hours</th>
                                <th class="py-2 pl-4 w-48 text-gray-600 dark:text-gray-400"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $monthlyMax = max(array_column($monthlyData, 'total_hours')) ?: 1;
                                // Group by month for subtle section shading
                                $currentMonth = null;
                            @endphp
                            @foreach($monthlyData as $row)
                                @php
                                    $isNewMonth = $currentMonth !== $row['month_label'];
                                    $currentMonth = $row['month_label'];
                                @endphp
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $isNewMonth ? 'border-t-2 border-t-gray-300 dark:border-t-gray-600' : '' }}">
                                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300 whitespace-nowrap font-medium">
                                        {{ $isNewMonth ? $row['month_label'] : '' }}
                                    </td>
                                    <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                    <td class="py-2 text-right font-semibold text-primary-600 dark:text-primary-400">
                                        {{ number_format($row['total_hours'], 1) }}h
                                    </td>
                                    <td class="py-2 pl-4">
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-primary-500 h-2 rounded-full" style="width: {{ min(($row['total_hours'] / $monthlyMax) * 100, 100) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-600">
                    <x-heroicon-o-calendar-days class="w-10 h-10 mb-2" />
                    <p class="text-sm">No monthly data available for the selected period.</p>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Recent Missing Checkouts --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500" />
                    Recent Missing Checkouts
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 dark:bg-warning-900 text-warning-700 dark:text-warning-300">
                        Last 20 records
                    </span>
                </div>
            </x-slot>
            @php $missingData = $this->getMissingCheckoutRecords(); @endphp
            @if(count($missingData) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">#</th>
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">Employee</th>
                                <th class="text-left py-2 pr-4 text-gray-600 dark:text-gray-400">Date</th>
                                <th class="text-left py-2 text-gray-600 dark:text-gray-400">Check-in Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($missingData as $index => $row)
                                <tr class="border-b dark:border-gray-700 hover:bg-warning-50 dark:hover:bg-warning-900/20">
                                    <td class="py-2 pr-4 text-gray-400 dark:text-gray-600 text-xs">{{ $index + 1 }}</td>
                                    <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                        {{ \Carbon\Carbon::parse($row['date'])->format('D, d M Y') }}
                                    </td>
                                    <td class="py-2 text-gray-700 dark:text-gray-300">
                                        @if($row['check_in_time'])
                                            {{ \Carbon\Carbon::parse($row['check_in_time'])->format('H:i') }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-10 text-success-500">
                    <x-heroicon-o-check-circle class="w-10 h-10 mb-2" />
                    <p class="text-sm font-medium">Great! No missing checkouts found.</p>
                </div>
            @endif
        </x-filament::section>
    </div>

</x-filament-panels::page>
