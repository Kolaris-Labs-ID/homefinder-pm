<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\TrainingRecordApprovalStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingRecordController extends Controller
{
    public function __construct(
        protected TrainingRecordApprovalStatusService $approvalStatusService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $this->authorize('viewAny', TrainingRecord::class);

        $records = TrainingRecord::query()
            ->where('employee_id', $employee->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $records->map(fn (TrainingRecord $record) => $this->transformRecord($record)),
        ]);
    }

    public function show(Request $request, TrainingRecord $trainingRecord): JsonResponse
    {
        $this->authorize('view', $trainingRecord);

        return response()->json([
            'data' => $this->transformRecord($trainingRecord),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $this->authorize('create', TrainingRecord::class);

        $data = $request->validate([
            'training_name' => ['required', 'string', 'max:255'],
            'training_type' => ['required', Rule::in(TrainingRecord::getTrainingTypes())],
            'status' => ['required', Rule::in(TrainingRecord::getStatuses())],
            'provider' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'completion_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:completion_date'],
            'certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $certificatePath = null;
        if ($request->hasFile('certificate')) {
            $certificatePath = $request->file('certificate')->store(
                "training-certificates/{$employee->id}",
                'private'
            );
        }

        $record = TrainingRecord::create([
            'employee_id' => $employee->id,
            'training_name' => $data['training_name'],
            'training_type' => $data['training_type'],
            'status' => $data['status'],
            'provider' => $data['provider'] ?? null,
            'description' => $data['description'] ?? null,
            'completion_date' => $data['completion_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'certificate_file_path' => $certificatePath,
        ]);

        app(ApprovalWorkflowService::class)->initializeWorkflow($record, ApprovalWorkflow::MODULE_TRAINING);

        return response()->json([
            'message' => 'Training record submitted successfully.',
            'data' => $this->transformRecord($record->fresh()),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformRecord(TrainingRecord $record): array
    {
        $approval = $this->approvalStatusService->forRecord($record);

        return [
            'id' => $record->id,
            'training_name' => $record->training_name,
            'provider' => $record->provider,
            'description' => $record->description,
            'completion_date' => $record->completion_date?->toDateString(),
            'expiry_date' => $record->expiry_date?->toDateString(),
            'status' => $record->status,
            'training_type' => $record->training_type,
            'has_certificate' => $record->certificate_file_path !== null,
            'created_at' => $record->created_at?->toIso8601String(),
            'pgp_points' => $record->pgp_points,
            'approved_at' => $record->approved_at?->toIso8601String(),
            'is_approved_for_pgp' => $record->isApprovedForPgp(),
            'approval_status' => $approval['approval_status'],
            'rejection_reason' => $approval['rejection_reason'],
        ];
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = optional($request->user())->employee;

        abort_if(! $employee, 404, 'Employee profile not found.');

        return $employee;
    }
}
