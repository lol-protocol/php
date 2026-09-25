<?php
/**
 * Page shell shared by both sites. Each view renders its body into $content
 * and sets $title; the per-site layout (genealogy/_layout.php,
 * pos/_layout.php) adds $sitio and $tiposBusqueda and includes this file.
 */
$lang = get_locale() === 'eng' ? 'en' : 'es';
$qActual = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
$tActual = is_string($_GET['t'] ?? null) ? $_GET['t'] : (string)array_key_first($tiposBusqueda);
?><!DOCTYPE html>
<html lang="<?= esc($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> · <?= esc($sitio) ?></title>
    <style>
        :root { --fg: #1c1c1e; --muted: #6b6b70; --bg: #fff; --line: #e3e3e6; --accent: #2457c5; --soft: #f5f6f8; }
        @media (prefers-color-scheme: dark) {
            :root { --fg: #ececef; --muted: #9a9aa2; --bg: #141416; --line: #2c2c31; --accent: #8fb0ff; --soft: #1d1d21; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--fg); background: var(--bg); }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        header { border-bottom: 1px solid var(--line); background: var(--soft); }
        .barra { max-width: 60rem; margin: 0 auto; padding: .75rem 1rem; display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; align-items: center; }
        .marca { font-weight: 700; color: var(--fg); }
        .barra form { display: flex; gap: .4rem; flex: 1 1 18rem; }
        .barra input, .barra select, .barra button { font: inherit; padding: .35rem .55rem; border: 1px solid var(--line); border-radius: .4rem; background: var(--bg); color: var(--fg); }
        .barra input { flex: 1; min-width: 0; }
        main { max-width: 60rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        h1 { margin: 0 0 .25rem; font-size: 1.75rem; line-height: 1.2; }
        h2 { margin: 2rem 0 .75rem; font-size: 1.15rem; }
        .sub { color: var(--muted); margin: 0 0 1.25rem; }
        nav.acciones { display: flex; flex-wrap: wrap; gap: .5rem; margin: 0 0 1.5rem; }
        nav.acciones a { padding: .25rem .7rem; border: 1px solid var(--line); border-radius: 999px; font-size: .9rem; }
        nav.acciones a[aria-current] { background: var(--accent); border-color: var(--accent); color: var(--bg); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .45rem .5rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: .85rem; color: var(--muted); font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        ul.lista { list-style: none; padding: 0; margin: 0; }
        ul.lista li { padding: .4rem 0; border-bottom: 1px solid var(--line); }
        .meta { color: var(--muted); font-size: .9rem; }
        .vacio { color: var(--muted); font-style: italic; }
        dl.ficha { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1.25rem; margin: 0; }
        dl.ficha dt { color: var(--muted); }
        dl.ficha dd { margin: 0; }
        .aviso { padding: .75rem 1rem; border: 1px solid var(--line); border-radius: .5rem; background: var(--soft); }
        .muestra { display: inline-block; width: 1em; height: 1em; border: 1px solid var(--line); border-radius: .2em; vertical-align: -.15em; }
        ul.arbol, ul.arbol ul { list-style: none; margin: 0; padding-left: 1.25rem; border-left: 1px solid var(--line); }
        ul.arbol { padding-left: 0; border: 0; }
        ul.arbol li { padding: .2rem 0; }
    </style>
</head>
<body>
<header>
    <div class="barra">
        <a class="marca" href="/"><?= esc($sitio) ?></a>
        <form action="/" method="get" role="search">
            <select name="t" aria-label="Tipo">
<?php foreach ($tiposBusqueda as $digitos => $etiqueta): ?>
                <option value="<?= esc($digitos) ?>"<?= (string)$digitos === $tActual ? ' selected' : '' ?>><?= esc($etiqueta) ?></option>
<?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= esc($qActual) ?>" placeholder="Buscar…" aria-label="Buscar">
            <button type="submit">Buscar</button>
        </form>
        <a href="<?= esc(cuenta()) ?>">Mi cuenta</a>
    </div>
</header>
<main>
<?= $content ?>
</main>
</body>
</html>
