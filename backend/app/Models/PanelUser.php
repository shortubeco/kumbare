<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class PanelUser extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasApiTokens, Notifiable;

    public const ROLE_PLATFORM_ADMIN = 'platform_admin';
    public const ROLE_BUSINESS_OWNER = 'business_owner';
    public const ROLE_BRANCH_MANAGER = 'branch_manager';
    public const ROLE_STAFF = 'staff';

    public const PERM_LOOKUP = 'lookup_customer';
    public const PERM_EARN = 'earn';
    public const PERM_REDEEM = 'redeem';
    public const PERM_VIEW_CUSTOMERS = 'view_customers';
    public const PERM_VIEW_REPORTS = 'view_reports';

    public const STAFF_PERMISSIONS = [
        self::PERM_LOOKUP, self::PERM_EARN, self::PERM_REDEEM,
        self::PERM_VIEW_CUSTOMERS, self::PERM_VIEW_REPORTS,
    ];

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = [
        'role', 'business_id', 'branch_id', 'first_name', 'last_name',
        'email', 'phone', 'password', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(StaffPermission::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBranchManager(): bool
    {
        return $this->role === self::ROLE_BRANCH_MANAGER;
    }

    /** Şubeye bağlı (şube yöneticisi veya çalışan) mı? */
    public function worksAtBranch(): bool
    {
        return in_array($this->role, [self::ROLE_BRANCH_MANAGER, self::ROLE_STAFF], true)
            && $this->branch_id !== null;
    }

    /** Şube yöneticisi tüm şube yetkilerine sahiptir; çalışan yalnızca verilenlere. */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive() || ! $this->worksAtBranch()) {
            return false;
        }

        if ($this->isBranchManager()) {
            return true;
        }

        return $this->permissions->contains('permission', $permission);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
