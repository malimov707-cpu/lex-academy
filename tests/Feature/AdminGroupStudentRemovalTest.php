<?php

use App\Enums\UserRole;
use App\Models\EducationGroup;
use App\Models\User;

test('super admin can view admin groups page', function (): void {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($admin)->get(route('admin.groups.index'));

    $response->assertOk();
    $response->assertSee('Guruhlar boshqaruvi');
});

test('super admin can remove a student from an education group', function (): void {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $student = User::factory()->create(['role' => UserRole::Student]);
    $group = EducationGroup::factory()->create();

    $group->members()->attach($student->id);
    expect($group->members()->count())->toBe(1);

    $response = $this->actingAs($admin)->delete(route('admin.groups.members.destroy', [$group, $student]));

    $response->assertRedirect();
    expect($group->fresh()->members()->count())->toBe(0);
});
