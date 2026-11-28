<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Avisos;
use App\ClienteMenorDeEdad;
use App\Database;
use App\EnvioUnico;
use App\MayoriaDeEdad;
use App\Paginacion;
use App\Peticion;
use App\Repositories\AnonimizacionRepository;
use App\Repositories\AuditoriaRepository;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FunnelRepository;
use App\Repositories\NotaCreditoRepository;
use App\Repositories\PagoRepository;
use App\Repositories\PaisRepository;
use App\Validacion;
use App\View;
use DateTimeImmutable;

final class ClientesController
{
    /** El campo de la casilla del formulario de alta (ver MENSAJE_SIN_RESTRICCIONES). */
    public const CAMPO_SIN_RESTRICCIONES = 'sin_restricciones';

    /**
     * La empresa no atiende a personas privadas de libertad ni interdictas, pero la app no
     * tiene ese dato y no puede verificarlo. Lo que si puede es exigir que quien carga al
     * cliente lo confirme, y dejarlo asentado: sin la casilla marcada no se da el alta, y la
     * entrada de auditoria del cliente dice que se confirmo.
     */
    public const MENSAJE_SIN_RESTRICCIONES = 'Confirmá que la persona no está privada de libertad ni interdicta.';

    /** Lo que queda escrito en la auditoria del alta. */
    public const DECLARACION_AUDITADA = 'Se confirmó que la persona no está privada de libertad ni interdicta.';

    /** El genero tiene que ser uno de los que ofrece el formulario: lo valida la app y lo restringe la base (migracion 004). */
    public static function generoEsValido(string $genero): bool
    {
        return in_array($genero, ClienteRepository::GENEROS, true);
    }

    /** El segmento tiene que ser uno de los que ofrece el formulario: lo valida la app y lo restringe la base (migracion 004). */
    public static function segmentoEsValido(string $segmento): bool
    {
        return in_array($segmento, ClienteRepository::SEGMENTOS, true);
    }

    /**
     * Nadie nacio despues de hoy. Las dos fechas son Y-m-d y ya validadas, asi
     * que alcanza con compararlas como texto. Que ademas sea mayor de edad, segun
     * el pais, se revisa aparte (MayoriaDeEdad::cumplida()). La fecha solo sirve para eso: no se guarda, queda el rango de edad.
     */
    public static function nacimientoNoEsFuturo(string $fechaNacimiento, string $hoy): bool
    {
        return Validacion::fechaNoEsFutura($fechaNacimiento, $hoy);
    }

    /**
     * El idioma es un conjunto abierto (cualquier texto vale), pero el dashboard
     * agrupa por el texto exacto: "ingles", "INGLES" y " Ingles " eran tres
     * filas distintas. Se guarda sin espacios sobrantes y con cada palabra en
     * mayuscula inicial ("Ingles"), como lo carga el seed. Vacio sigue vacio.
     */
    public static function normalizarIdioma(string $idioma): string
    {
        $limpio = trim((string) preg_replace('/\s+/u', ' ', $idioma));

        return mb_convert_case($limpio, MB_CASE_TITLE, 'UTF-8');
    }

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $pagina = Paginacion::pagina();
        $listado = (new ClienteRepository())->buscar($q, $pagina);

        View::render('clientes/listado', [
            'q' => $q,
            'pagina' => $listado['pagina'],
            'clientes' => $listado['filas'],
            'totalClientes' => $listado['total'],
            'totalPaginas' => $listado['totalPaginas'],
            'activePage' => 'clientes',
            'titulo' => 'Clientes',
        ]);
    }

    public function ficha(): void
    {
        $id = Peticion::id();
        $cliente = (new ClienteRepository())->porId($id);
        if (Peticion::abortarSiNoExiste($cliente, 'Cliente no encontrado.')) {
            return;
        }

        View::render('clientes/ficha', [
            'cliente' => $cliente,
            'boletas' => (new BoletaRepository())->porCliente($id),
            'pagos' => (new PagoRepository())->porCliente($id),
            'notasCredito' => (new NotaCreditoRepository())->porCliente($id),
            'viajeFunnel' => (new FunnelRepository())->viajeDeCliente($id),
            'deshacibleHasta' => (new AnonimizacionRepository())->deshacibleHasta($id),
            'avisos' => [
                ...Avisos::confirmaciones('cliente'),
                ...(self::idEnLaUrl('sin_restaurar') === null ? [] : [Avisos::atencion(
                    'No se pudo deshacer la anonimización: el plazo venció, o el email original ya lo usa otro cliente.'
                )]),
            ],
            'activePage' => 'clientes',
            'titulo' => $cliente['nombre'],
        ]);
    }

    /**
     * Anonimizar a un cliente (migracion 022): confirmar escribiendo su email. Se borran nombre, email y ciudad (y los del funnel) y se
     * puede deshacer durante AnonimizacionRepository::PLAZO_DIAS dias. La auditoria dice que se hizo, sin datos personales.
     */
    public function anonimizar(): void
    {
        $id = Peticion::id();
        $cliente = (new ClienteRepository())->porId($id);
        if (Peticion::abortarSiNoExiste($cliente, 'Cliente no encontrado.')) {
            return;
        }
        if ($cliente['anonimizado_en'] !== null) {
            Peticion::redirigir('?page=cliente&id=' . $id);
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $escrito = trim((string) ($_POST['confirmar_email'] ?? ''));
            if (mb_strtolower($escrito) !== mb_strtolower((string) $cliente['email'])) {
                $error = 'El email que escribiste no es el del cliente: no se anonimizó.';
            } else {
                Database::transaccion(static function () use ($id): void {
                    if ((new AnonimizacionRepository())->anonimizar($id)) {
                        AuditoriaRepository::auditar('anonimizar', 'cliente', $id, sprintf(
                            'Cliente #%d anonimizado (nombre, email y ciudad); se puede deshacer durante %d días.',
                            $id,
                            AnonimizacionRepository::PLAZO_DIAS
                        ));
                    }
                });
                Peticion::redirigir('?page=cliente&id=' . $id . '&anonimizado=' . $id);
            }
        }

        View::render('clientes/anonimizar', [
            'cliente' => $cliente,
            'plazoDias' => AnonimizacionRepository::PLAZO_DIAS,
            'error' => $error,
            'activePage' => 'clientes',
            'titulo' => 'Anonimizar cliente',
        ]);
    }

    /** El id entero positivo del parametro $nombre de la URL, o null: lo que no es un id se ignora, igual que en Avisos. */
    private static function idEnLaUrl(string $nombre): ?int
    {
        $id = filter_var(trim((string) ($_GET[$nombre] ?? '')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);

        return $id === false ? null : $id;
    }

    public function deshacerAnonimizacion(): void
    {
        $id = Peticion::id();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Peticion::redirigir('?page=cliente&id=' . $id);
        }

        try {
            $hecho = Database::transaccion(static function () use ($id): bool {
                if (!(new AnonimizacionRepository())->deshacer($id)) {
                    return false;
                }
                AuditoriaRepository::auditar('deshacer', 'cliente', $id, "Cliente #{$id}: se deshizo la anonimización.");

                return true;
            });
        } catch (\PDOException) {
            // El email original lo tomo otro cliente mientras tanto (unico): no se cambia nada.
            Peticion::redirigir('?page=cliente&id=' . $id . '&sin_restaurar=' . $id);
        }
        Peticion::redirigir('?page=cliente&id=' . $id . ($hecho ? '&restaurado=' : '&sin_restaurar=') . $id);
    }

    public function nuevo(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = EnvioUnico::tokenRecibido();
            $reenvio = $token === null ? null : EnvioUnico::redireccionPrevia($token);
            if ($reenvio !== null) {
                // Mismo formulario enviado otra vez (doble clic): el cliente ya
                // se creo, se redirige igual que la primera vez.
                Peticion::redirigir($reenvio);
            }

            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $paisCodigo = (string) ($_POST['pais_codigo'] ?? '');
            $ciudad = trim((string) ($_POST['ciudad'] ?? ''));
            $idioma = self::normalizarIdioma((string) ($_POST['idioma'] ?? ''));
            $genero = trim((string) ($_POST['genero'] ?? ''));
            $fechaNacimiento = (string) ($_POST['fecha_nacimiento'] ?? '');
            $segmento = trim((string) ($_POST['segmento'] ?? ''));
            $sinRestricciones = ($_POST[self::CAMPO_SIN_RESTRICCIONES] ?? '') === '1';
            // Vacio = no eligio: se usa el valor por defecto. Cualquier otro texto tiene que estar en la lista.
            $genero = $genero === '' ? 'No especifica' : $genero;
            $segmento = $segmento === '' ? 'general' : $segmento;
            $idioma = $idioma === '' ? 'Espanol' : $idioma;

            if ($token === null) {
                $error = EnvioUnico::MENSAJE_SIN_TOKEN;
            } elseif (Validacion::faltanCampos([$nombre, $email, $paisCodigo, $ciudad, $fechaNacimiento])) {
                $error = 'Completá todos los campos obligatorios.';
            } elseif (!Validacion::emailEsValido($email)) {
                $error = 'El email no es válido.';
            } elseif (($largo = Validacion::primerTextoLargo([
                ['El nombre', $nombre, Validacion::MAX_NOMBRE],
                ['El email', $email, Validacion::MAX_EMAIL],
                ['La ciudad', $ciudad, Validacion::MAX_CIUDAD],
                ['El idioma', $idioma, Validacion::MAX_IDIOMA],
            ])) !== null) {
                $error = $largo;
            } elseif (($pais = (new PaisRepository())->mayoriaDeEdad($paisCodigo)) === null) {
                // Antes un pais que no existe llegaba hasta la base y volvia como "No se pudo crear el cliente."
                // Va antes que la edad: cuantos anios se piden depende del pais.
                $error = 'Elegí un país válido.';
            } elseif (!Validacion::fechaEsValida($fechaNacimiento)) {
                $error = 'La fecha de nacimiento no es válida.';
            } elseif (!self::nacimientoNoEsFuturo($fechaNacimiento, date('Y-m-d'))) {
                $error = 'La fecha de nacimiento no puede ser posterior a hoy.';
            } elseif (!MayoriaDeEdad::cumplida($fechaNacimiento, new DateTimeImmutable('today'), $pais['mayoria_de_edad'])) {
                $error = MayoriaDeEdad::mensaje($pais['mayoria_de_edad'], $pais['nombre']);
            } elseif (!$sinRestricciones) {
                $error = self::MENSAJE_SIN_RESTRICCIONES;
            } elseif (!self::generoEsValido($genero)) {
                $error = 'Elegí un género válido.';
            } elseif (!self::segmentoEsValido($segmento)) {
                $error = 'Elegí un segmento válido.';
            } else {
                try {
                    $destino = EnvioUnico::ejecutar($token, static function () use ($nombre, $email, $segmento, $paisCodigo, $ciudad, $idioma, $genero, $fechaNacimiento): string {
                        $id = (new ClienteRepository())->crear([
                            'nombre' => $nombre,
                            'email' => $email,
                            'segmento' => $segmento,
                            'fecha_alta' => date('Y-m-d'),
                            'pais_codigo' => $paisCodigo,
                            'ciudad' => $ciudad,
                            'idioma' => $idioma,
                            'genero' => $genero,
                            'fecha_nacimiento' => $fechaNacimiento,
                        ]);
                        AuditoriaRepository::auditar('crear', 'cliente', $id, "Cliente #{$id}: {$nombre} ({$email}). " . self::DECLARACION_AUDITADA);

                        return '?page=cliente&id=' . $id . '&creado=1';
                    });
                    Peticion::redirigir($destino);
                } catch (ClienteMenorDeEdad $e) {
                    // La validacion de arriba ya lo habria dicho: esto es por si el repositorio y el controlador se desfasan.
                    $error = $e->getMessage();
                } catch (\PDOException $e) {
                    $error = Validacion::mensajeDeConflicto($e, 'cliente');
                }
            }
        }

        $paises = new PaisRepository();

        View::render('clientes/alta', [
            'paises' => $paises->listado(),
            'idiomas' => (new ClienteRepository())->idiomasEnUso(),
            'generos' => ClienteRepository::GENEROS,
            'segmentos' => ClienteRepository::SEGMENTOS,
            // El selector de fecha no sabe todavia el pais: topa en la edad mas baja, y el servidor exige la del pais elegido.
            'nacimientoMasReciente' => MayoriaDeEdad::nacimientoMasReciente(new DateTimeImmutable('today'), $paises->menorMayoriaDeEdad()),
            'mayoriaDeEdadPorPais' => $paises->conMayoriaDeEdadDistinta(),
            'error' => $error,
            'activePage' => 'clientes',
            'titulo' => 'Nuevo cliente',
        ]);
    }
}
