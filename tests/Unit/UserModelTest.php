<?php

use App\Models\User;

test('age accessor calculates age from date of birth', function () {
    $dateOfBirth = now()->subYears(25)->subMonths(6);

    $user = User::factory()->make([
        'date_of_birth' => $dateOfBirth,
    ]);

    expect($user->age)->toBe(25);
});

test('age accessor returns null when date of birth is not set', function () {
    $user = User::factory()->make([
        'date_of_birth' => null,
    ]);

    expect($user->age)->toBeNull();
});

test('age accessor calculates correctly for users just turning 18', function () {
    $dateOfBirth = now()->subYears(18)->subDays(1);

    $user = User::factory()->make([
        'date_of_birth' => $dateOfBirth,
    ]);

    expect($user->age)->toBe(18);
});

test('age accessor calculates correctly for users about to turn 18', function () {
    $dateOfBirth = now()->subYears(18)->addDays(1);

    $user = User::factory()->make([
        'date_of_birth' => $dateOfBirth,
    ]);

    expect($user->age)->toBe(17);
});

test('hasCompletedProfile returns false when profile_completed_at is null', function () {
    $user = User::factory()->make([
        'profile_completed_at' => null,
    ]);

    expect($user->hasCompletedProfile())->toBeFalse();
});

test('hasCompletedProfile returns true when profile_completed_at is set', function () {
    $user = User::factory()->make([
        'profile_completed_at' => now(),
    ]);

    expect($user->hasCompletedProfile())->toBeTrue();
});

test('date_of_birth is cast to Carbon instance', function () {
    $user = User::factory()->make([
        'date_of_birth' => '1990-05-15',
    ]);

    expect($user->date_of_birth)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('display_name is fillable', function () {
    $user = User::factory()->make([
        'display_name' => 'Test Display Name',
    ]);

    expect($user->display_name)->toBe('Test Display Name');
});

test('date_of_birth is fillable', function () {
    $user = User::factory()->make([
        'date_of_birth' => '1985-12-25',
    ]);

    $this->assertNotNull($user->date_of_birth);
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1985-12-25');
});
