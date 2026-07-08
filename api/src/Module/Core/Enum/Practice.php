<?php

declare(strict_types=1);

namespace App\Module\Core\Enum;

/**
 * Les practices du cabinet — vocabulaire partagé par tous les modules (shared kernel).
 */
enum Practice: string
{
    case AuditSsi = 'audit_ssi';
    case Cyberdefense = 'cyberdefense';
    case Grc = 'grc';
    case IdentiteNumerique = 'identite_numerique';
    case SecuriteOperationnelle = 'securite_operationnelle';
    case Formation = 'formation';

    public function label(): string
    {
        return match ($this) {
            self::AuditSsi => 'Audit SSI',
            self::Cyberdefense => 'Cyberdéfense',
            self::Grc => 'GRC',
            self::IdentiteNumerique => 'Identité Numérique',
            self::SecuriteOperationnelle => 'Sécurité Opérationnelle',
            self::Formation => 'Formation',
        };
    }
}
