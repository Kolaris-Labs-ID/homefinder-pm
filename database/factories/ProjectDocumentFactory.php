<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
{
    protected $model = ProjectDocument::class;

    public function definition(): array
    {
        $name = fake()->slug(2).'.pdf';

        return [
            'project_id' => Project::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'private',
            'file_path' => 'project-documents/1/'.Str::uuid()->toString().'.pdf',
            'original_filename' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 1048576),
            'file_hash' => hash('sha256', $name),
            'category' => fake()->randomElement(ProjectDocument::CATEGORIES),
            'description' => fake()->optional()->sentence(),
            'uploaded_at' => now(),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'archived_at' => now(),
            'archived_by' => User::factory(),
            'archive_reason' => 'superseded',
        ]);
    }
}
