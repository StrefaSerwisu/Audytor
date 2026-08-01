<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case GlobalAdmin = 'global_admin';
    case TechnicalLead = 'technical_lead';
    case Auditor = 'auditor';
    case Sales = 'sales';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::GlobalAdmin => 'Administrator Global IT',
            self::TechnicalLead => 'Lider techniczny',
            self::Auditor => 'Audytor',
            self::Sales => 'Sales',
            self::Client => 'Klient',
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::GlobalAdmin,
            self::TechnicalLead,
        ], true);
    }

    public function isInternal(): bool
    {
        return ! $this->isClient();
    }

    public function isClient(): bool
    {
        return $this === self::Client;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }
}
