<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Project;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:check-overdue 
                            {--days=3 : Number of days before due date to send reminder}
                            {--overdue-only : Only check for overdue tickets, not nearing overdue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue and nearing overdue tickets and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Checking for overdue and nearing overdue tickets...');
        
        $days = (int) $this->option('days');
        $overdueOnly = $this->option('overdue-only');
        
        $notifiedCount = 0;
        
        // Check overdue tickets
        $this->info('Checking for overdue tickets...');
        $overdueTickets = Ticket::with(['assignees', 'project', 'status', 'creator'])
            ->overdue()
            ->get();
        
        $this->info("Found {$overdueTickets->count()} overdue ticket(s)");
        
        foreach ($overdueTickets as $ticket) {
            try {
                $notificationService->notifyTicketOverdue($ticket);
                $notifiedCount++;
                $this->line("  ✓ Notified for overdue ticket: {$ticket->uuid} - {$ticket->name}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to notify for ticket {$ticket->uuid}: " . $e->getMessage());
                Log::error('Failed to send overdue notification', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Check nearing overdue tickets
        if (!$overdueOnly) {
            $this->info("Checking for tickets nearing overdue (within {$days} days)...");
            $nearingOverdueTickets = Ticket::with(['assignees', 'project', 'status', 'creator'])
                ->nearingOverdue($days)
                ->get();
            
            $this->info("Found {$nearingOverdueTickets->count()} ticket(s) nearing overdue");
            
            foreach ($nearingOverdueTickets as $ticket) {
                try {
                    $daysUntilDue = $ticket->getDaysUntilDue();
                    $notificationService->notifyTicketNearingOverdue($ticket, $daysUntilDue);
                    $notifiedCount++;
                    $this->line("  ✓ Notified for nearing overdue ticket: {$ticket->uuid} - {$ticket->name} ({$daysUntilDue} days remaining)");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to notify for ticket {$ticket->uuid}: " . $e->getMessage());
                    Log::error('Failed to send nearing overdue notification', [
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        // Check overdue projects
        $this->info('Checking for overdue projects...');
        $overdueProjects = Project::with(['members'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', Carbon::today())
            ->get();
        
        $this->info("Found {$overdueProjects->count()} overdue project(s)");
        
        foreach ($overdueProjects as $project) {
            try {
                $notificationService->notifyProjectOverdue($project);
                $notifiedCount++;
                $this->line("  ✓ Notified for overdue project: {$project->name}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to notify for project {$project->name}: " . $e->getMessage());
                Log::error('Failed to send project overdue notification', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Check projects nearing overdue
        if (!$overdueOnly) {
            $this->info("Checking for projects nearing overdue (within {$days} days)...");
            $today = Carbon::today();
            $warningDate = $today->copy()->addDays($days);
            
            $nearingOverdueProjects = Project::with(['members'])
                ->whereNotNull('end_date')
                ->whereDate('end_date', '>=', $today)
                ->whereDate('end_date', '<=', $warningDate)
                ->get();
            
            $this->info("Found {$nearingOverdueProjects->count()} project(s) nearing overdue");
            
            foreach ($nearingOverdueProjects as $project) {
                try {
                    $daysUntilDue = $project->remaining_days;
                    $notificationService->notifyProjectNearingOverdue($project, $daysUntilDue);
                    $notifiedCount++;
                    $this->line("  ✓ Notified for nearing overdue project: {$project->name} ({$daysUntilDue} days remaining)");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to notify for project {$project->name}: " . $e->getMessage());
                    Log::error('Failed to send project nearing overdue notification', [
                        'project_id' => $project->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        $this->info("✅ Completed! Sent {$notifiedCount} notification(s).");
        
        return Command::SUCCESS;
    }
}

