import java.io.BufferedReader;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.attribute.FileTime;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Lee acciones-planas.csv y, una vez arrancado el watcher, recarga sola en caliente
 * cuando el archivo cambia (mtime) -- así no hace falta reiniciar el servicio a mano
 * cada vez que se regeneran los datos semilla.
 */
final class CargadorAcciones {

    private CargadorAcciones() {
    }

    static List<Accion> loadActions(Path csvPath) throws IOException {
        List<Accion> actions = new ArrayList<>();
        try (BufferedReader reader = Files.newBufferedReader(csvPath, StandardCharsets.UTF_8)) {
            reader.readLine(); // descarta encabezado
            String line;
            while ((line = reader.readLine()) != null) {
                if (line.isBlank()) {
                    continue;
                }
                // user_id,type,duration_ms,amount_usd,country,age,gender,timestamp
                String[] f = line.split(",", -1);
                Double amount = f[3].isEmpty() ? null : Double.parseDouble(f[3]);
                actions.add(new Accion(f[0], f[1], Double.parseDouble(f[2]), amount, f[4], Integer.parseInt(f[5]), f[6]));
            }
        }
        return actions;
    }

    /** Chequea el mtime del CSV cada intervaloSegundos y recarga accionesRef si cambió. */
    static void iniciarWatcher(Path csvPath, AtomicReference<List<Accion>> accionesRef, long intervaloSegundos) {
        AtomicReference<FileTime> ultimoMtime = new AtomicReference<>(mtimeSeguro(csvPath));

        ScheduledExecutorService scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "watcher-acciones-csv");
            t.setDaemon(true);
            return t;
        });

        scheduler.scheduleWithFixedDelay(() -> {
            FileTime actual = mtimeSeguro(csvPath);
            if (actual != null && !actual.equals(ultimoMtime.get())) {
                try {
                    List<Accion> recargadas = loadActions(csvPath);
                    accionesRef.set(recargadas);
                    ultimoMtime.set(actual);
                    System.out.println("Recargadas " + recargadas.size() + " acciones (cambio detectado en " + csvPath + ")");
                } catch (IOException | RuntimeException e) {
                    // El CSV puede estar a mitad de escribirse; se reintenta en el próximo ciclo.
                    System.err.println("No se pudo recargar " + csvPath + ": " + e.getMessage());
                }
            }
        }, intervaloSegundos, intervaloSegundos, TimeUnit.SECONDS);
    }

    private static FileTime mtimeSeguro(Path path) {
        try {
            return Files.getLastModifiedTime(path);
        } catch (IOException e) {
            return null;
        }
    }
}
