<?php

namespace App\Domain\Loyalty;

use App\Domain\DomainException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PanelUser;

/** Personel işlemleri için ortak yetki ve kapsam kontrolleri. */
class StaffGate
{
    /** Personelin şubesini döner; şube çalışır durumda değilse hata verir. */
    public function branchFor(PanelUser $staff, string ...$anyOfPermissions): Branch
    {
        if (! $staff->isActive() || ! $staff->worksAtBranch()) {
            throw DomainException::forbidden('Bu işlem yalnızca şube yöneticisi ve çalışanları içindir.');
        }

        if ($anyOfPermissions) {
            $allowed = false;
            foreach ($anyOfPermissions as $perm) {
                if ($staff->hasPermission($perm)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                throw DomainException::forbidden();
            }
        }

        $branch = $staff->branch()->with('business')->first();
        if (! $branch || ! $branch->isOperational()) {
            throw new DomainException('Şube aktif değil; işlem yapılamaz.', 'branch_inactive', 422);
        }

        return $branch;
    }

    public function requireManager(PanelUser $staff): Branch
    {
        if (! $staff->isBranchManager()) {
            throw DomainException::forbidden('Bu işlemi yalnızca şube yöneticisi yapabilir.');
        }

        return $this->branchFor($staff);
    }

    /** Müşteri numarasıyla aktif müşteriyi bulur. */
    public function findCustomer(int|string $customerNo): Customer
    {
        if (! ctype_digit((string) $customerNo)) {
            throw DomainException::notFound('Müşteri bulunamadı.');
        }

        $customer = Customer::query()->where('customer_no', (int) $customerNo)->first();

        if (! $customer) {
            throw DomainException::notFound('Müşteri bulunamadı.');
        }

        if ($customer->status !== 'active') {
            throw new DomainException('Müşteri hesabı pasif durumda.', 'customer_inactive', 422);
        }

        return $customer;
    }
}
