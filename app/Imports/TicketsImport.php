<?php

namespace App\Imports;

use App\Models\Ticket;
use App\Models\Project;
use App\Models\User;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use App\Models\Epic;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TicketsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $project;
    protected $importedCount = 0;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function model(array $row)
    {
        // Skip empty rows or rows with empty title
        if (empty($row['title']) || trim($row['title']) === '') {
            return null;
        }

        // Skip sample data rows (but allow real tickets with similar names)
        if (trim($row['title']) === 'Sample Ticket Title' && 
            trim($row['description'] ?? '') === 'Sample description for the ticket') {
            return null;
        }

        // Find status
        $status = $this->project->ticketStatuses()
            ->where('name', trim($row['status']))
            ->first();

        if (!$status) {
            // Instead of throwing exception, skip this row
            return null;
        }

        // Find priority (optional)
        $priority = null;
        if (!empty($row['priority']) && trim($row['priority']) !== '') {
            $priority = TicketPriority::where('name', trim($row['priority']))->first();
        }

        // Find epic (optional)
        $epic = null;
        if (!empty($row['epic']) && trim($row['epic']) !== '') {
            $epic = $this->project->epics()->where('name', trim($row['epic']))->first();
        }

        // Parse due date
        $dueDate = null;
        if (!empty($row['due_date_yyyy_mm_dd']) && trim($row['due_date_yyyy_mm_dd']) !== '') {
            try {
                // Try multiple date formats
                $dateString = trim($row['due_date_yyyy_mm_dd']);
                
                // Handle Excel serial numbers (numeric dates)
                if (is_numeric($dateString)) {
                    $dueDate = Carbon::createFromFormat('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString)->format('Y-m-d'));
                } else {
                    // Try common date formats
                    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'];
                    foreach ($formats as $format) {
                        try {
                            $dueDate = Carbon::createFromFormat($format, $dateString);
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    // If no format worked, try Carbon's flexible parsing
                    if (!$dueDate) {
                        $dueDate = Carbon::parse($dateString);
                    }
                }
            } catch (\Exception $e) {
                // Invalid date format, leave as null
            }
        }
        
        // Parse start date (same logic)
        $startDate = null;
        if (!empty($row['start_date_yyyy_mm_dd']) && trim($row['start_date_yyyy_mm_dd']) !== '') {
            try {
                $dateString = trim($row['start_date_yyyy_mm_dd']);
                
                if (is_numeric($dateString)) {
                    $startDate = Carbon::createFromFormat('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString)->format('Y-m-d'));
                } else {
                    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'];
                    foreach ($formats as $format) {
                        try {
                            $startDate = Carbon::createFromFormat($format, $dateString);
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    if (!$startDate) {
                        $startDate = Carbon::parse($dateString);
                    }
                }
            } catch (\Exception $e) {
                // Invalid date format, leave as null
            }
        }

        // Create ticket
        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $status->id,
            'priority_id' => $priority?->id,
            'epic_id' => $epic?->id,
            'name' => trim($row['title']),
            'description' => trim($row['description'] ?? ''),
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'created_by' => auth()->id(),
        ]);

        // Assign users if provided
        if (!empty($row['assignees_comma_separated_emails']) && trim($row['assignees_comma_separated_emails']) !== '') {
            $emails = array_map('trim', explode(',', $row['assignees_comma_separated_emails']));
            $userIds = [];

            foreach ($emails as $email) {
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $user = User::where('email', $email)->first();
                    if ($user && $this->project->members()->where('user_id', $user->id)->exists()) {
                        $userIds[] = $user->id;
                    }
                }
            }

            if (!empty($userIds)) {
                $ticket->assignees()->sync($userIds);
            }
        }

        $this->importedCount++;
        return $ticket;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => [
                'required',
                'string',
                Rule::exists('ticket_statuses', 'name')->where('project_id', $this->project->id)
            ],
            'priority' => 'nullable|string|exists:ticket_priorities,name',
            'epic' => [
                'nullable',
                'string',
                Rule::exists('epics', 'name')->where('project_id', $this->project->id)
            ],
            'assignees_comma_separated_emails' => 'nullable|string',
            // More flexible date validation - accepts various date formats
            'start_date_yyyy_mm_dd' => 'nullable|date',
            'due_date_yyyy_mm_dd' => 'nullable|date',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}