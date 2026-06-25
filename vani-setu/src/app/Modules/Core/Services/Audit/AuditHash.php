<?php

namespace App\Modules\Core\Services\Audit;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class AuditHash
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function preImage(
        ?string $prevHash,
        ?int $actorId,
        string $actorRole,
        string $action,
        ?string $subjectType,
        ?string $subjectId,
        array $payload,
        DateTimeInterface|string $createdAt,
        ?string $chainSegment = 'on_record',
    ): string {
        $createdAt = $createdAt instanceof DateTimeInterface
            ? $createdAt->format(DateTimeInterface::ATOM)
            : CarbonImmutable::parse($createdAt)->format(DateTimeInterface::ATOM);

        $parts = [
            $prevHash,
            $actorId,
            $actorRole,
            $action,
            $subjectType,
            $subjectId,
            self::canonicalJson($payload),
            $createdAt,
        ];

        if ($chainSegment !== null) {
            array_splice($parts, 3, 0, $chainSegment);
        }

        return implode('|', $parts);
    }

    /**
     * @param  mixed  $value
     */
    public static function canonicalJson($value): string
    {
        return json_encode(
            self::sortRecursively($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private static function sortRecursively($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => self::sortRecursively($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursively($item);
        }

        return $value;
    }
}
