import com.sun.net.httpserver.HttpServer;

import java.io.IOException;
import java.net.InetSocketAddress;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Motor de estadisticas del backoffice: agrega duracion/monto (en USD) de acciones de
 * usuario por "universo" de comparacion (paises, rango de edad, genero). Sin
 * dependencias externas (solo JDK) para poder compilar/ejecutar sin acceso a red.
 * El CSV se recarga solo si cambia (ver CargadorAcciones.iniciarWatcher).
 *
 * Uso: javac *.java && java ServicioEstadisticas [ruta-a-acciones-planas.csv] [puerto]
 */
public class ServicioEstadisticas {

    private static final long INTERVALO_WATCHER_SEGUNDOS = 5;

    public static void main(String[] args) throws IOException {
        Path csvPath = resolveCsvPath(args.length > 0 ? args[0] : null);
        int port = args.length > 1 ? Integer.parseInt(args[1]) : 8081;

        List<Accion> acciones = CargadorAcciones.loadActions(csvPath);
        System.out.println("Cargadas " + acciones.size() + " acciones desde " + csvPath);

        AtomicReference<List<Accion>> accionesRef = new AtomicReference<>(acciones);
        CargadorAcciones.iniciarWatcher(csvPath, accionesRef, INTERVALO_WATCHER_SEGUNDOS);

        HttpServer server = HttpServer.create(new InetSocketAddress(port), 0);
        server.createContext("/stats", new ManejadorEstadisticas(accionesRef));
        server.setExecutor(Executors.newFixedThreadPool(8));
        server.start();
        System.out.println("Stats service escuchando en http://localhost:" + port + "/stats");
    }

    private static Path resolveCsvPath(String override) {
        if (override != null) {
            return Path.of(override);
        }
        String[] candidates = {"datos/acciones-planas.csv", "../datos/acciones-planas.csv", "../../datos/acciones-planas.csv"};
        for (String c : candidates) {
            Path p = Path.of(c);
            if (Files.exists(p)) {
                return p;
            }
        }
        throw new IllegalStateException(
                "No se encontro acciones-planas.csv. Ejecuta el servicio desde sistema-nuevo/ o "
                + "sistema-nuevo/servicio-estadisticas-java/, o pasa la ruta como primer argumento.");
    }
}
