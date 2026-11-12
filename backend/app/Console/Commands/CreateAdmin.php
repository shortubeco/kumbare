<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PanelUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** İlk platform yöneticisi hesabını oluşturur. */
class CreateAdmin extends Command
{
    protected $signature = 'kumbare:create-admin
                            {--email= : E-posta adresi}
                            {--first-name= : Ad}
                            {--last-name= : Soyad}
                            {--password= : Şifre (verilmezse sorulur)}';

    protected $description = 'Platform yöneticisi hesabı oluşturur';

    public function handle(): int
    {
        $data = [
            'email' => $this->option('email') ?? $this->ask('E-posta'),
            'first_name' => $this->option('first-name') ?? $this->ask('Ad'),
            'last_name' => $this->option('last-name') ?? $this->ask('Soyad'),
            'password' => $this->option('password') ?? $this->secret('Şifre (en az 12 karakter)'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (PanelUser::query()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->exists()) {
            $this->error('Bu e-posta adresiyle bir panel kullanıcısı zaten var.');

            return self::FAILURE;
        }

        $admin = PanelUser::query()->create([
            'role' => PanelUser::ROLE_PLATFORM_ADMIN,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'status' => 'active',
        ]);

        AuditLog::record(null, 'admin.created', $admin);
        $this->info("Platform yöneticisi oluşturuldu: {$admin->email}");

        return self::SUCCESS;
    }
}
