<?php

declare(strict_types=1);

/**
 * Escapa $valor para ponerlo en el HTML de una vista, tanto entre etiquetas como dentro de un atributo
 * (con comillas simples o dobles). Es la unica forma de escapar en las vistas: antes eran 122 llamadas
 * a htmlspecialchars(), y olvidar una es un XSS. Con una sola funcion, revisar que no falte ninguna es
 * buscar las salidas que empiezan con `<?=` y no llaman a e(). Los flags son los que PHP 8.1 aplica por
 * omision; null sale vacio.
 */
function e(string|int|float|null $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE);
}

/** Convierte 'YYYY-MM' a una etiqueta corta en espanol, ej. 'mar 2026'. */
function etiqueta_de_mes(string $ym): string
{
    static $meses = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr',
        '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago',
        '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    [$anio, $mes] = explode('-', $ym);
    return ($meses[$mes] ?? $mes) . ' ' . $anio;
}

/** Formato compacto para espacios angostos, ej. '$12.3K', '$4.2M', '-$4.2M'. */
function dinero_compacto(float $valor): string
{
    $moneda = \App\Config::MONEDA;
    $signo = $valor < 0 ? '-' : '';
    $abs = abs($valor);
    if ($abs >= 1_000_000) {
        return $signo . $moneda . number_format($abs / 1_000_000, 1) . 'M';
    }
    if ($abs >= 1_000) {
        return $signo . $moneda . number_format($abs / 1_000, 1) . 'K';
    }
    return \App\Config::dinero($valor);
}

/**
 * Formatea un monto en su moneda original (no convertida), ej. 'MX$1,234.00'. Una moneda sin decimales
 * (el yen, el won) lo muestra entero: '¥1,234 JPY'. Si un monto de esas trae centavos (se cargo antes de que
 * la aplicacion pidiera enteros), los muestra: redondearlo seria mostrar otra cifra de la que esta guardada.
 */
function dinero_en_moneda(float $monto, string $codigoMoneda): string
{
    $simbolo = \App\Repositories\MonedaRepository::simbolo($codigoMoneda);
    $decimales = \App\Repositories\MonedaRepository::decimalesParaMostrar($monto, $codigoMoneda);
    return ($monto < 0 ? '-' : '') . $simbolo . number_format(abs($monto), $decimales) . ' ' . $codigoMoneda;
}

/**
 * El <input> del monto de los formularios de boleta y de pago, con los mismos limites que aplica el
 * servidor (Validacion::montoEnRango): de un centavo a Validacion::MONTO_MAXIMO, de a centavos. Asi el
 * navegador rechaza un monto fuera de rango antes de enviarlo, igual que maxlength lo hace con los
 * textos. $valor es lo que se muestra de entrada (lo ya escrito, o el monto guardado) y se escapa aca.
 * $codigoMoneda, cuando la moneda ya esta fija (la edicion de una boleta o de un pago), hace que una moneda sin
 * decimales (yen, won) pida enteros. En el alta de un pago no se pasa: la moneda la decide la boleta que elija.
 */
function campo_monto(string|int|float|null $valor = null, ?string $codigoMoneda = null): string
{
    $decimales = $codigoMoneda !== null ? \App\Repositories\MonedaRepository::decimales($codigoMoneda) : 2;
    $sinDecimales = $decimales === 0;
    // El paso es la unidad menor de la moneda: 1 (yen), 0.01 o 0.001 (dinar kuwaiti).
    $minimo = $sinDecimales ? '1' : number_format(1 / (10 ** $decimales), $decimales, '.', '');

    return sprintf(
        '<input type="number" name="monto" id="monto" required min="%s" max="%s" step="%s" value="%s">',
        $minimo,
        // El tope se corta (no se redondea) a los decimales de la moneda: 999999999999.999 en dos decimales es ...99, no 1e12.
        substr(number_format(\App\Validacion::MONTO_MAXIMO, 3, '.', ''), 0, 12 + ($decimales > 0 ? 1 + $decimales : 0)),
        $minimo,
        e($valor)
    );
}

/**
 * La tasa de cambio de una moneda como la guarda la base (cuantos dolares vale una unidad), sin los
 * ceros que sobran: "0.00100000" -> "0.001", "1.09000000" -> "1.09", "100.00000000" -> "100".
 */
function tasa_en_usd(string $tasa): string
{
    return str_contains($tasa, '.') ? rtrim(rtrim($tasa, '0'), '.') : $tasa;
}

/**
 * Cuantas unidades de la moneda valen un dolar, que es como se dice una cotizacion ("1 USD = 952.38 CLP"):
 * la inversa de la tasa. Con dos decimales desde 100 unidades (CLP, JPY) y con cuatro por debajo (EUR, KWD),
 * donde dos decimales esconderian el cambio. '' si la tasa no es positiva.
 */
function unidades_por_usd(string $tasa): string
{
    $valor = (float) $tasa;
    if ($valor <= 0.0) {
        return '';
    }
    $unidades = 1 / $valor;

    return number_format($unidades, $unidades >= 100 ? 2 : 4);
}

/** % de cambio de anterior a actual, o null si no hay base para comparar. */
function variacion_pct(float $actual, float $anterior): ?float
{
    if ($anterior <= 0.0) {
        return null;
    }
    return ($actual - $anterior) / $anterior * 100;
}

/** @return array{0: string, 1: string} [fondo, texto] segun intensidad, para el heatmap de cohortes. */
function color_celda_cohorte(float $pct): array
{
    return match (true) {
        $pct <= 0 => ['var(--gridline)', 'var(--text-muted)'],
        $pct < 25 => ['var(--seq-250)', 'var(--text-primary)'],
        $pct < 50 => ['var(--seq-350)', '#fff'],
        $pct < 75 => ['var(--seq-450)', '#fff'],
        default => ['var(--seq-600)', '#fff'],
    };
}

/**
 * Badge de comparacion contra una base (periodo anterior, año anterior,
 * etc.), coloreado segun si subir es bueno para esa metrica. $unidad va
 * pegado al numero tal cual (ej. '%' sin espacio, ' pp' con espacio para
 * puntos porcentuales).
 */
function insignia_de_variacion(?float $pct, bool $subirEsBueno = true, string $etiqueta = 'vs. período anterior', string $unidad = '%'): string
{
    if ($pct === null) {
        return '<span class="delta">Sin datos para comparar (' . e($etiqueta) . ')</span>';
    }
    $bueno = $subirEsBueno ? $pct >= 0 : $pct <= 0;
    $clase = $bueno ? 'good' : 'critical';
    $signo = $pct >= 0 ? '+' : '';
    return '<span class="delta ' . $clase . '">' . $signo . number_format($pct, 1) . $unidad . ' ' . e($etiqueta) . '</span>';
}

/** URL actual (?page=...&filtro=...) con un parametro reemplazado, para los links de paginacion. */
function url_con_parametro(string $clave, string|int $valor): string
{
    return url_con_parametros([$clave => $valor]);
}

/**
 * URL actual con varios parametros cambiados a la vez; el que vale null se
 * saca. Para los links de la paginacion por cursor, donde pedir "mas antiguas"
 * es poner un parametro y sacar el otro.
 *
 * @param array<string, string|int|null> $cambios
 */
function url_con_parametros(array $cambios): string
{
    $params = $_GET;
    foreach ($cambios as $clave => $valor) {
        if ($valor === null) {
            unset($params[$clave]);
        } else {
            $params[$clave] = $valor;
        }
    }
    return '?' . http_build_query($params);
}

/** Altura porcentual para una barra, con un piso visible cuando el valor es > 0. */
function altura_en_pct(float $valor, float $max): float
{
    if ($max <= 0 || $valor <= 0) {
        return 0.0;
    }
    return max(2.0, round($valor / $max * 100, 1));
}

/**
 * Mini-grafico de barra individual en SVG (vertical u horizontal segun el
 * $estilo que se le pase: 'height:NN%' o 'width:NN%'). No lleva viewBox a
 * proposito: sin el, las coordenadas del rect son px reales del layout CSS
 * que la contiene (igual que un div con border-radius), asi que el tamano
 * lo sigue resolviendo el flexbox existente sin distorsion.
 */
function barra_svg(string $clase, string $estilo, string $color, string $tooltip, int $radio = 4): string
{
    $tituloEsc = e($tooltip);
    return '<svg class="' . e($clase) . '" style="' . e($estilo) . '"'
        . ' role="img" aria-label="' . $tituloEsc . '" tabindex="0">'
        . '<rect width="100%" height="100%" rx="' . $radio . '" ry="' . $radio . '" fill="' . e($color) . '"></rect>'
        . '<title>' . $tituloEsc . '</title>'
        . '</svg>';
}
