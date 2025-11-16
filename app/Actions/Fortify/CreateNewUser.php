<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'date_of_birth' => [
                'required',
                'date',
                'before:today',
                'before_or_equal:'.now()->subYears(18)->format('Y-m-d'),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ], [
            'date_of_birth.before_or_equal' => 'You must be at least 18 years old to use this application.',
        ])->validate();

        return User::create([
            'email' => $input['email'],
            'date_of_birth' => $input['date_of_birth'],
            'display_name' => $input['display_name'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
