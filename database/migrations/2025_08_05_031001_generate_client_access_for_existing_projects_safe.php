<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;
use App\Models\ExternalAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add a temporary column to track migration-generated records
        Schema::table('external_access', function (Blueprint $table) {
            $table->boolean('migration_generated')->default(false)->after('is_active');
        });
        
        // Get all existing projects that don't have external access
        $projects = Project::whereDoesntHave('externalAccess')->get();
        
        $generatedCredentials = [];
        
        foreach ($projects as $project) {
            // Generate access token and password
            $accessToken = Str::random(32);
            $password = Str::random(8);
            
            // External access record
            ExternalAccess::create([
                'project_id' => $project->id,
                'access_token' => $accessToken,
                'password' => $password,
                'is_active' => true,
                'migration_generated' => true,
            ]);
            
            // Store credentials for logging
            $generatedCredentials[] = [
                'project_name' => $project->name,
                'project_id' => $project->id,
                'url' => url('/external/' . $accessToken),
                'password' => $password,
            ];
        }
        
        // Log all generated credentials
        if (!empty($generatedCredentials)) {
            \Log::info('External access credentials generated for existing projects:', $generatedCredentials);
            
            // Optionally, write to a file for easy access
            $credentialsFile = storage_path('logs/external_credentials_' . date('Y-m-d_H-i-s') . '.json');
            file_put_contents($credentialsFile, json_encode($generatedCredentials, JSON_PRETTY_PRINT));
            
            \Log::info("Credentials also saved to: {$credentialsFile}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove only the records created by this migration
        ExternalAccess::where('migration_generated', true)->delete();
        
        // Remove the temporary column
        Schema::table('external_access', function (Blueprint $table) {
            $table->dropColumn('migration_generated');
        });
    }
};