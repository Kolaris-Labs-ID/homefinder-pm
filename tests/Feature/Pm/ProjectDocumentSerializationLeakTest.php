<?php

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('hides file_path, file_hash and disk from array serialization', function () {
    $document = ProjectDocument::factory()->for(Project::factory())->create();

    $array = $document->toArray();

    expect($array)->not->toHaveKey('file_path')
        ->and($array)->not->toHaveKey('file_hash')
        ->and($array)->not->toHaveKey('disk');
});

it('exposes a project->documents relationship', function () {
    $project = Project::factory()->create();
    ProjectDocument::factory()->for($project)->create();

    expect($project->documents)->toHaveCount(1)
        ->and($project->documents->first())->toBeInstanceOf(ProjectDocument::class);
});

it('does not expose documents or a document count in the PM project API', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create();
    $project->members()->attach($user->id);
    ProjectDocument::factory()->for($project)->create();

    Sanctum::actingAs($user);

    $data = $this->getJson("/api/pm/projects/{$project->id}")->assertOk()->json('data');

    expect($data)->not->toHaveKey('documents')
        ->and($data)->not->toHaveKey('documents_count')
        ->and($data)->not->toHaveKey('file_path')
        ->and($data)->not->toHaveKey('file_hash');
});
