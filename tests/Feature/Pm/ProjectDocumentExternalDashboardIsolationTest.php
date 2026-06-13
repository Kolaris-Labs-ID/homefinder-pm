<?php

use App\Livewire\ExternalDashboard;
use App\Models\ExternalAccess;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    // ExternalDashboard::loadWidgetData() uses MySQL DATE_FORMAT(); register a shim so the
    // MySQL-only dashboard can render under the SQLite test database. Test infra only.
    $pdo = DB::connection()->getPdo();
    if (DB::connection()->getDriverName() === 'sqlite') {
        $pdo->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
            $ts = strtotime((string) $date) ?: time();
            $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%i' => 'i', '%s' => 's'];

            return date(strtr($format, $map), $ts);
        }, 2);
    }

    $this->project = Project::factory()->create();
    $this->token = 'ext-token-'.uniqid();
    ExternalAccess::create([
        'project_id' => $this->project->id,
        'access_token' => $this->token,
        'password' => 'secret123',
        'is_active' => true,
    ]);

    session()->put('external_authenticated_'.$this->token, true);
});

it('never renders document filename, path or hash on the external dashboard', function () {
    ProjectDocument::factory()->for($this->project)->create([
        'original_filename' => 'TOPSECRET-merger-contract.pdf',
        'category' => 'contract',
        'file_path' => "project-documents/{$this->project->id}/leak-me.pdf",
        'file_hash' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef0',
    ]);

    Livewire::test(ExternalDashboard::class, ['token' => $this->token])
        ->assertOk()
        ->assertDontSee('TOPSECRET-merger-contract.pdf')
        ->assertDontSee('leak-me.pdf')
        ->assertDontSee('project-documents/')
        ->assertDontSee('deadbeefdeadbeef');
});

it('produces document-free output and never eager-loads documents onto the project', function () {
    ProjectDocument::factory()->for($this->project)->create([
        'original_filename' => 'TOPSECRET-merger-contract.pdf',
    ]);

    $component = Livewire::test(ExternalDashboard::class, ['token' => $this->token])
        ->assertOk()
        ->assertDontSee('TOPSECRET-merger-contract.pdf');

    expect($component->get('project')->relationLoaded('documents'))->toBeFalse()
        ->and($this->project->documents()->count())->toBe(1);
});

it('does not globally eager-load documents on the Project model', function () {
    expect((new Project)->getEagerLoads())->not->toHaveKey('documents');
});
