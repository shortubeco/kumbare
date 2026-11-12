<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasApiTokens, Notifiable, SoftDeletes;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /** customer_no ve birth_date hiçbir zaman toplu atama ile değiştirilemez. */
    protected $fillable = [
        'first_name', 'last_name', 'phone', 'email', 'password',
        'city_id', 'district_id', 'display_name_mode',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'customer_no' => 'integer',
        'birth_date' => 'date',
        'kvkk_consent_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function favoriteBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'favorites')->withPivot('created_at');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /** Yorumlarda görünen ad: varsayılan "Ahmet Y.", tercih edilirse tam ad. */
    public function publicName(): string
    {
        if ($this->display_name_mode === 'full') {
            return $this->fullName();
        }

        $initial = mb_substr(trim($this->last_name), 0, 1);

        return $this->first_name.($initial !== '' ? ' '.mb_strtoupper($initial, 'UTF-8').'.' : '');
    }

    /**
     * Bugün müşterinin doğum günü mü? (Türkiye saatine göre)
     * 29 Şubat doğumlular, artık yıl olmayan yıllarda 28 Şubat'ta doğum günü sayılır.
     */
    public function isBirthday(?CarbonInterface $on = null): bool
    {
        if (! $this->birth_date) {
            return false;
        }

        $on ??= now();
        $month = (int) $this->birth_date->format('n');
        $day = (int) $this->birth_date->format('j');

        if ($month === 2 && $day === 29 && ! $on->isLeapYear()) {
            $day = 28;
        }

        return (int) $on->format('n') === $month && (int) $on->format('j') === $day;
    }
}
