<?php

declare(strict_types=1);

/**
 * Acceso de solo lectura a los datos semilla (JSON) generados en new-system/data/.
 */
final class DataStore
{
    private static ?array $users = null;
    private static ?array $actions = null;
    private static ?array $groups = null;

    private static function dataDir(): string
    {
        return __DIR__ . '/../../data';
    }

    private static function readJson(string $file): array
    {
        $path = self::dataDir() . '/' . $file;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("No se pudo leer $path. ¿Corriste generate_seed_data.php?");
        }
        return json_decode($contents, true);
    }

    public static function users(): array
    {
        return self::$users ??= self::readJson('users.json');
    }

    public static function userById(string $id): ?array
    {
        foreach (self::users() as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    public static function actionsByUser(string $userId): array
    {
        $actions = self::$actions ??= self::readJson('actions.json');
        $result = array_values(array_filter($actions, fn ($a) => $a['user_id'] === $userId));
        usort($result, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        return $result;
    }

    public static function groups(): array
    {
        return self::$groups ??= self::readJson('country_groups.json');
    }
}
