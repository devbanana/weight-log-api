<?php

use App\Models\User;

test('user can register with valid data including date of birth and display name', function () {
    $response = $this->postJson('/auth/register', [
        'email' => 'john@example.com',
        'date_of_birth' => '1990-01-15',
        'display_name' => 'John',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201);

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();

    assert($user instanceof User); // PHPStan type assertion
    expect($user->display_name)->toBe('John')
        ->and($user->date_of_birth?->format('Y-m-d'))->toBe('1990-01-15');
});

test('registration fails when date of birth is missing', function () {
    $response = $this->postJson('/auth/register', [
        'email' => 'john@example.com',
        'display_name' => 'John',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date_of_birth']);
});

test('registration fails when display name is missing', function () {
    $response = $this->postJson('/auth/register', [
        'email' => 'john@example.com',
        'date_of_birth' => '1990-01-15',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['display_name']);
});

test('registration fails when user is under 18 years old', function () {
    $underageDate = now()->subYears(17)->format('Y-m-d');

    $response = $this->postJson('/auth/register', [
        'email' => 'young@example.com',
        'date_of_birth' => $underageDate,
        'display_name' => 'Young User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date_of_birth']);
});

test('registration fails with future date of birth', function () {
    $futureDate = now()->addDays(1)->format('Y-m-d');

    $response = $this->postJson('/auth/register', [
        'email' => 'future@example.com',
        'date_of_birth' => $futureDate,
        'display_name' => 'Future User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date_of_birth']);
});

test('registration succeeds when user is exactly 18 years old', function () {
    $exactlyEighteen = now()->subYears(18)->format('Y-m-d');

    $response = $this->postJson('/auth/register', [
        'email' => 'eighteen@example.com',
        'date_of_birth' => $exactlyEighteen,
        'display_name' => 'Eighteen',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201);

    expect(User::where('email', 'eighteen@example.com')->exists())->toBeTrue();
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/auth/register', [
        'email' => 'existing@example.com',
        'date_of_birth' => '1990-01-15',
        'display_name' => 'Duplicate',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('display name is stored instead of name field', function () {
    $this->postJson('/auth/register', [
        'email' => 'display@example.com',
        'date_of_birth' => '1990-01-15',
        'display_name' => 'My Display Name',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'display@example.com')->first();

    $this->assertNotNull($user);
    expect($user->display_name)->toBe('My Display Name');
});

test('date of birth is stored correctly as date', function () {
    $this->postJson('/auth/register', [
        'email' => 'datetest@example.com',
        'date_of_birth' => '1985-06-20',
        'display_name' => 'Date Test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'datetest@example.com')->first();
    assert($user instanceof User); // PHPStan type assertion

    expect($user->date_of_birth)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($user->date_of_birth?->format('Y-m-d'))->toBe('1985-06-20');
});
