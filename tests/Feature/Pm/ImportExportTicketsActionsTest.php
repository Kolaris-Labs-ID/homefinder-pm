<?php

use App\Filament\Pages\ProjectBoard;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user->assignRole($role);
    auth()->login($this->user);
});

test('project board header actions expose export, import and template download for super admin', function () {
    $component = Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id]);

    $component->assertActionExists('export_tickets');
    $component->assertActionExists('import_tickets');
    $component->assertActionExists('download_template');
});

test('import, export and template actions are hidden without a selected project', function () {
    $component = Livewire::test(ProjectBoard::class);

    $component->assertActionHidden('export_tickets');
    $component->assertActionHidden('import_tickets');
    $component->assertActionHidden('download_template');
});

test('download template action returns the ticket template export', function () {
    $component = Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id]);

    $component->callAction('download_template', data: ['project_id' => $this->project->id])
        ->assertHasNoActionErrors();
});

test('import tickets action creates tickets from an uploaded excel file', function () {
    Storage::fake('local');

    TicketStatus::factory()->create(['project_id' => $this->project->id, 'name' => 'To Do', 'sort_order' => 1]);

    $rowsExport = new class implements FromArray, WithHeadings
    {
        public function headings(): array
        {
            return [
                'Title',
                'Description',
                'Status',
                'Priority',
                'Epic',
                'Assignees Comma Separated Emails',
                'Start Date YYYY-MM-DD',
                'Due Date YYYY-MM-DD',
            ];
        }

        public function array(): array
        {
            return [
                ['Imported via Excel', 'a real description', 'To Do', '', '', '', '', ''],
            ];
        }
    };

    $bytes = Excel::raw($rowsExport, ExcelType::XLSX);
    $file = UploadedFile::fake()->createWithContent('import.xlsx', $bytes);

    $component = Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id]);

    $component->mountAction('import_tickets')
        ->set('mountedActionsData.0.project_id', $this->project->id)
        ->set('mountedActionsData.0.excel_file', [$file])
        ->callMountedAction();

    expect(Ticket::where('project_id', $this->project->id)->where('name', 'Imported via Excel')->exists())->toBeTrue();
});
