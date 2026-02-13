<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientVisitController extends Controller
{
    /**
     * Get all visits for the authenticated employee.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $query = $employee->clientVisits()->latest('visit_date');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('from')) {
            $query->whereDate('visit_date', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('visit_date', '<=', $request->to);
        }

        $visits = $query->get()->map(fn (ClientVisit $visit) => [
            'id' => $visit->id,
            'client_name' => $visit->client_name,
            'client_address' => $visit->client_address,
            'purpose' => $visit->purpose,
            'purpose_label' => $visit->purpose_label,
            'purpose_detail' => $visit->purpose_detail,
            'visit_date' => $visit->visit_date->format('Y-m-d'),
            'planned_start_time' => $visit->planned_start_time,
            'planned_end_time' => $visit->planned_end_time,
            'actual_check_in' => $visit->actual_check_in,
            'actual_check_out' => $visit->actual_check_out,
            'status' => $visit->status,
            'status_label' => $visit->status_label,
            'notes' => $visit->notes,
            'visit_report' => $visit->visit_report,
            'has_photo' => !empty($visit->photo_path),
            'created_at' => $visit->created_at,
        ]);

        return response()->json([
            'data' => $visits,
        ]);
    }

    /**
     * Get visit purposes for dropdown.
     */
    public function purposes(): JsonResponse
    {
        $purposes = collect(ClientVisit::getPurposeLabels())->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return response()->json([
            'data' => $purposes,
        ]);
    }

    /**
     * Create a new visit.
     */
    public function store(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'purpose' => ['required', Rule::in(ClientVisit::getPurposes())],
            'purpose_detail' => ['nullable', 'string', 'max:255'],
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_end_time' => ['nullable', 'date_format:H:i', 'after:planned_start_time'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $visit = ClientVisit::create([
            'employee_id' => $employee->id,
            ...$data,
            'status' => ClientVisit::STATUS_PLANNED,
        ]);

        return response()->json([
            'message' => 'Visit scheduled successfully.',
            'data' => $visit,
        ], 201);
    }

    /**
     * Check in to a visit.
     */
    public function checkIn(Request $request, ClientVisit $clientVisit): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($clientVisit->employee_id !== $employee->id, 403, 'You cannot check in to this visit.');
        abort_if(!$clientVisit->canCheckIn(), 422, 'This visit cannot be checked in.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store(
                "visit-photos/{$employee->id}",
                'private'
            );
        }

        $clientVisit->update([
            'status' => ClientVisit::STATUS_IN_PROGRESS,
            'actual_check_in' => now(),
            'check_in_latitude' => $data['latitude'] ?? null,
            'check_in_longitude' => $data['longitude'] ?? null,
            'photo_path' => $photoPath ?? $clientVisit->photo_path,
        ]);

        // Create an attendance log entry for this visit
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'client_visit_id' => $clientVisit->id,
            'date' => $clientVisit->visit_date,
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => now(),
            'check_in_latitude' => $data['latitude'] ?? null,
            'check_in_longitude' => $data['longitude'] ?? null,
            'check_in_address' => $clientVisit->client_address,
            'source' => AttendanceLog::SOURCE_VISIT,
            'status' => AttendanceLog::STATUS_VISIT,
            'metadata' => [
                'client_name' => $clientVisit->client_name,
                'purpose' => $clientVisit->purpose,
                'purpose_label' => $clientVisit->purpose_label,
            ],
        ]);

        return response()->json([
            'message' => 'Checked in to visit successfully.',
            'data' => $clientVisit->fresh(),
        ]);
    }

    /**
     * Check out from a visit.
     */
    public function checkOut(Request $request, ClientVisit $clientVisit): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($clientVisit->employee_id !== $employee->id, 403, 'You cannot check out from this visit.');
        abort_if(!$clientVisit->canCheckOut(), 422, 'This visit cannot be checked out.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'visit_report' => ['nullable', 'string', 'max:2000'],
        ]);

        $clientVisit->update([
            'status' => ClientVisit::STATUS_COMPLETED,
            'actual_check_out' => now(),
            'check_out_latitude' => $data['latitude'] ?? null,
            'check_out_longitude' => $data['longitude'] ?? null,
            'visit_report' => $data['visit_report'] ?? null,
        ]);

        // Update the corresponding attendance log entry
        $attendanceLog = AttendanceLog::where('client_visit_id', $clientVisit->id)->first();
        if ($attendanceLog) {
            $attendanceLog->update([
                'check_out_time' => now(),
                'check_out_latitude' => $data['latitude'] ?? null,
                'check_out_longitude' => $data['longitude'] ?? null,
                'check_out_address' => $clientVisit->client_address,
            ]);
        }

        return response()->json([
            'message' => 'Checked out from visit successfully.',
            'data' => $clientVisit->fresh(),
        ]);
    }

    /**
     * Cancel a visit.
     */
    public function cancel(Request $request, ClientVisit $clientVisit): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($clientVisit->employee_id !== $employee->id, 403, 'You cannot cancel this visit.');
        abort_if(!$clientVisit->canCancel(), 422, 'This visit cannot be cancelled.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Delete photo if exists
        if ($clientVisit->photo_path) {
            Storage::disk('private')->delete($clientVisit->photo_path);
        }

        $clientVisit->update([
            'status' => ClientVisit::STATUS_CANCELLED,
            'cancellation_reason' => $data['reason'] ?? null,
            'photo_path' => null,
        ]);

        // Remove the corresponding attendance log entry if exists
        AttendanceLog::where('client_visit_id', $clientVisit->id)->delete();

        return response()->json([
            'message' => 'Visit cancelled.',
        ]);
    }

    /**
     * Get a single visit detail.
     */
    public function show(Request $request, ClientVisit $clientVisit): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($clientVisit->employee_id !== $employee->id, 403, 'You cannot view this visit.');

        return response()->json([
            'data' => [
                'id' => $clientVisit->id,
                'client_name' => $clientVisit->client_name,
                'client_address' => $clientVisit->client_address,
                'latitude' => $clientVisit->latitude,
                'longitude' => $clientVisit->longitude,
                'purpose' => $clientVisit->purpose,
                'purpose_label' => $clientVisit->purpose_label,
                'purpose_detail' => $clientVisit->purpose_detail,
                'visit_date' => $clientVisit->visit_date->format('Y-m-d'),
                'planned_start_time' => $clientVisit->planned_start_time,
                'planned_end_time' => $clientVisit->planned_end_time,
                'actual_check_in' => $clientVisit->actual_check_in,
                'actual_check_out' => $clientVisit->actual_check_out,
                'check_in_location' => $clientVisit->check_in_latitude ? [
                    'latitude' => $clientVisit->check_in_latitude,
                    'longitude' => $clientVisit->check_in_longitude,
                ] : null,
                'check_out_location' => $clientVisit->check_out_latitude ? [
                    'latitude' => $clientVisit->check_out_latitude,
                    'longitude' => $clientVisit->check_out_longitude,
                ] : null,
                'status' => $clientVisit->status,
                'status_label' => $clientVisit->status_label,
                'notes' => $clientVisit->notes,
                'visit_report' => $clientVisit->visit_report,
                'has_photo' => !empty($clientVisit->photo_path),
                'cancellation_reason' => $clientVisit->cancellation_reason,
                'created_at' => $clientVisit->created_at,
            ],
        ]);
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = optional($request->user())->employee;

        abort_if(!$employee, 404, 'Employee profile not found.');

        return $employee;
    }
}
