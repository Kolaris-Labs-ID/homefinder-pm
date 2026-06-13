<?php

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Database\Seeders\ProjectDocumentPermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(ProjectDocumentPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->project = Project::factory()->create();
    $this->document = ProjectDocument::factory()->for($this->project)->create();
});

it('lets a project member with view permission view the document', function () {
    $user = projectMemberWith($this->project, ['view_project_document']);
    expect($user->can('view', $this->document))->toBeTrue();
});

it('lets a project member with download permission download the document', function () {
    $user = projectMemberWith($this->project, ['download_project_document']);
    expect($user->can('download', $this->document))->toBeTrue();
});

it('denies a project member who lacks the document permission', function () {
    $user = projectMemberWith($this->project, []);
    expect($user->can('view', $this->document))->toBeFalse()
        ->and($user->can('download', $this->document))->toBeFalse();
});

it('denies a non-member even when they hold the document permission (project isolation)', function () {
    $stranger = User::factory()->create();
    $stranger->givePermissionTo('view_project_document');
    $stranger->givePermissionTo('download_project_document');

    expect($stranger->can('view', $this->document))->toBeFalse()
        ->and($stranger->can('download', $this->document))->toBeFalse();
});

it('lets a C-level executive with the permission access any project document without membership', function () {
    Role::firstOrCreate(['name' => 'CEO', 'guard_name' => 'web']);
    $ceo = tap(User::factory()->create())->assignRole('CEO');
    $ceo->givePermissionTo('view_project_document');

    expect($ceo->can('view', $this->document))->toBeTrue();
});

it('lets super_admin do everything via the global gate without explicit document permissions', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $admin = tap(User::factory()->create())->assignRole('super_admin');

    expect($admin->can('view', $this->document))->toBeTrue()
        ->and($admin->can('download', $this->document))->toBeTrue()
        ->and($admin->can('create', ProjectDocument::class))->toBeTrue()
        ->and($admin->can('delete', $this->document))->toBeTrue();
});

it('gates create on the create permission', function () {
    $with = projectMemberWith($this->project, ['create_project_document']);
    $without = projectMemberWith($this->project, []);

    expect($with->can('create', ProjectDocument::class))->toBeTrue()
        ->and($without->can('create', ProjectDocument::class))->toBeFalse();
});

it('gates archive/delete on the delete permission and project membership', function () {
    $member = projectMemberWith($this->project, ['delete_project_document']);
    $nonMember = User::factory()->create();
    $nonMember->givePermissionTo('delete_project_document');

    expect($member->can('delete', $this->document))->toBeTrue()
        ->and($nonMember->can('delete', $this->document))->toBeFalse();
});
