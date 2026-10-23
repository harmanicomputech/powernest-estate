<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ])->validate();

        return User::create([
            'name' => trim($input['name']),
            'email' => Str::lower(trim($input['email'])),
            'phone' => $input['phone'] ?? null,
            'password' => $input['password'],
        ]);
    }
}
