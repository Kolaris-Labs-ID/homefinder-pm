<?php

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeEmergencyContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
});

// --- Model & Relationship Tests ---

test('can create an emergency contact for an employee', function () {
    $contact = EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Jane Doe',
        'phone' => '08123456789',
        'relationship' => 'Sister',
        'is_primary' => true,
    ]);

    expect($contact)->toBeInstanceOf(EmployeeEmergencyContact::class)
        ->and($contact->name)->toBe('Jane Doe')
        ->and($contact->phone)->toBe('08123456789')
        ->and($contact->relationship)->toBe('Sister')
        ->and($contact->is_primary)->toBeTrue();
});

test('emergency contact belongs to employee', function () {
    $contact = EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'John Doe',
    ]);

    expect($contact->employee->id)->toBe($this->employee->id);
});

test('employee has many emergency contacts', function () {
    EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Contact One',
        'relationship' => 'Mother',
    ]);
    EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Contact Two',
        'relationship' => 'Father',
    ]);

    $this->employee->refresh();

    expect($this->employee->emergencyContacts)->toHaveCount(2);
});

test('phone and relationship are optional', function () {
    $contact = EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Minimal Contact',
    ]);

    $contact->refresh();

    expect($contact->phone)->toBeNull()
        ->and($contact->relationship)->toBeNull()
        ->and($contact->is_primary)->toBeFalse();
});

test('deleting employee cascades to emergency contacts', function () {
    EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Will Be Deleted',
    ]);

    expect(EmployeeEmergencyContact::where('employee_id', $this->employee->id)->count())->toBe(1);

    $this->employee->delete();

    expect(EmployeeEmergencyContact::where('employee_id', $this->employee->id)->count())->toBe(0);
});

// --- API Profile Tests ---

test('profile API includes emergency contacts', function () {
    EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'API Contact',
        'phone' => '08111111111',
        'relationship' => 'Brother',
        'is_primary' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/hris/profile');

    $response->assertOk()
        ->assertJsonPath('employee.emergency_contacts.0.name', 'API Contact')
        ->assertJsonPath('employee.emergency_contacts.0.phone', '08111111111')
        ->assertJsonPath('employee.emergency_contacts.0.relationship', 'Brother')
        ->assertJsonPath('employee.emergency_contacts.0.is_primary', true);
});

test('profile API returns empty array when no emergency contacts', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/hris/profile');

    $response->assertOk()
        ->assertJsonPath('employee.emergency_contacts', []);
});

test('profile API returns multiple emergency contacts', function () {
    EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Primary Contact',
        'relationship' => 'Spouse',
        'is_primary' => true,
    ]);
    EmployeeEmergencyContact::create([
        'employee_id' => $this->employee->id,
        'name' => 'Secondary Contact',
        'relationship' => 'Teman',
        'is_primary' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/hris/profile');

    $response->assertOk()
        ->assertJsonCount(2, 'employee.emergency_contacts');
});
