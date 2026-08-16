<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The only way an account that can reach /admin comes into existence. There is no
 * registration screen on the panel and there should never be one.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'nacre:admin
                            {--name= : Display name}
                            {--email= : Login email}';

    protected $description = 'Create a Nacre admin panel operator';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Name',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Email',
            required: true,
        );

        // Prompted rather than accepted as an option, so the password never lands
        // in the shell history or the process list.
        $password = password(
            label: 'Password',
            required: true,
        );

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
                'password' => ['required', Password::min(12)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->components->info("Admin {$email} created. Sign in at /admin.");

        return self::SUCCESS;
    }
}
