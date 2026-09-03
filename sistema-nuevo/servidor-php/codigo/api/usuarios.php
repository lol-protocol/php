<?php

declare(strict_types=1);

/** Endpoints de catálogo: /api/users, /api/groups. */

function api_users(): void
{
    $groups = AlmacenDatos::groups();
    $users = array_map(static function (array $u) use ($groups): array {
        $u['country_name'] = $groups['countries'][$u['country']] ?? $u['country'];
        return $u;
    }, AlmacenDatos::users());

    usort($users, fn ($a, $b) => $a['name'] <=> $b['name']);

    echo json_encode($users, JSON_UNESCAPED_UNICODE);
}

function api_groups(): void
{
    echo json_encode(AlmacenDatos::groups(), JSON_UNESCAPED_UNICODE);
}
