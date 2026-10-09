<?php

namespace App\Console\Commands;

use App\Domain\Administration\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'ziwait:make-admin {email} {--name=Super Admin} {--password= : Omit to be prompted}';

    protected $description = 'Create (or promote) a platform super admin. Super admin cannot be granted through the web app.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->secret('Password (min 10 chars, letters and numbers)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->exists ? $user->name : $this->option('name');
        $user->password = $password;
        $user->forceFill(['is_super_admin' => true, 'disabled_at' => null, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        AuditLog::record('admin.created_via_cli', $user, [], null, $user->id);
        $this->info("Super admin ready: {$email}");

        return self::SUCCESS;
    }
}
