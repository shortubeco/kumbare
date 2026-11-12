<?php

namespace App\Domain\Customers;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Müşteri kaydı. Müşteri numarası 1'den başlayarak sıralı ve boşluksuz verilir:
 * sayaç satırı kilitlenir, numara ile müşteri aynı transaction'da yazılır.
 */
class CustomerRegistrar
{
    /**
     * @param  array{first_name: string, last_name: string, phone: string, email: string, password: string,
     *               city_id: int, district_id: int, birth_date: string}  $data
     */
    public function register(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $row = DB::selectOne('SELECT last_value FROM customer_no_counter WHERE id = 1 FOR UPDATE');
            $next = ((int) $row->last_value) + 1;
            DB::update('UPDATE customer_no_counter SET last_value = ? WHERE id = 1', [$next]);

            $customer = new Customer([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
                'city_id' => $data['city_id'],
                'district_id' => $data['district_id'],
            ]);
            $customer->forceFill([
                'customer_no' => $next,
                'birth_date' => $data['birth_date'],
                'kvkk_consent_at' => now(),
                'status' => 'active',
                'display_name_mode' => 'initials',
            ])->save();

            AuditLog::record($customer, 'customer.registered', $customer);

            return $customer;
        });
    }

    /** Hesap silme: soft delete + kişisel verilerin anonimleştirilmesi. Numara ve işlemler kalır. */
    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->tokens()->delete();
            $customer->favoriteBranches()->detach();

            $customer->forceFill([
                'first_name' => 'Silinmiş',
                'last_name' => 'Kullanıcı',
                'phone' => 'deleted-'.$customer->id,
                'email' => 'deleted-'.$customer->id.'@kumbare.invalid',
                'password' => bin2hex(random_bytes(32)),
                'birth_date' => '1900-01-01',
                'status' => 'passive',
            ])->save();

            AuditLog::record($customer, 'customer.deleted', $customer);

            $customer->delete();
        });
    }
}
