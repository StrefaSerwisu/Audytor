<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nip',
        'address',
        'contact_name',
        'contact_email',
        'contact_phone',
        'account_manager_id',
        'status',
        'notes',
    ];

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ClientLocation::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function salesQualifications(): HasMany
    {
        return $this->hasMany(SalesQualification::class);
    }
}
