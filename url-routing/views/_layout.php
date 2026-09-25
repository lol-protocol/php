<?php
/**
 * Shared placeholder scaffold: renders $title plus whatever the controller
 * passed in $data. Each view sets $title and includes this file; replace a
 * view's include with real markup once its resource is backed by data.
 */
$lang = get_locale() === 'eng' ? 'en' : 'es';

$format = static function (mixed $value): string {
    if (!is_array($value)) {
        return (string)$value;
    }
    if ($value === []) {
        return '—';
    }
    return implode(' / ', array_map(
        static fn($v) => is_scalar($v) ? (string)$v : (string)json_encode($v),
        $value
    ));
};
?><!DOCTYPE html>
<html lang="<?= esc($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>
</head>
<body>
<main>
    <h1><?= esc($title) ?></h1>
<?php if ($data === []): ?>
    <p>Sin datos todavía.</p>
<?php else: ?>
    <dl>
<?php foreach ($data as $key => $value): ?>
        <dt><?= esc($key) ?></dt>
        <dd><?= esc($format($value)) ?></dd>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
</main>
</body>
</html>
