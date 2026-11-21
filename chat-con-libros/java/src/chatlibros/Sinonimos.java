package chatlibros;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;

/**
 * Port de src/Sinonimos.php: grupos de sinónimos para ampliar la pregunta. Una línea por grupo,
 * palabras separadas por comas; «#» abre un comentario. Agrega los demás miembros del grupo
 * después de los términos originales.
 */
public final class Sinonimos {
    private final Map<String, List<String>> companeros = new HashMap<>();

    public static Sinonimos desdeTexto(String texto) {
        Sinonimos sinonimos = new Sinonimos();
        sinonimos.agregarTexto(texto);
        return sinonimos;
    }

    /** Los archivos que no existan se saltan. */
    public static Sinonimos desdeArchivos(Path... rutas) throws IOException {
        Sinonimos sinonimos = new Sinonimos();
        for (Path ruta : rutas) {
            if (Files.isRegularFile(ruta)) {
                sinonimos.agregarTexto(Files.readString(ruta, StandardCharsets.UTF_8));
            }
        }
        return sinonimos;
    }

    public void agregarTexto(String texto) {
        for (String linea : texto.split("\r\n|\n|\r", -1)) {
            linea = linea.trim();
            if (linea.isEmpty() || linea.startsWith("#")) {
                continue;
            }
            List<String> grupo = new ArrayList<>();
            for (String palabra : linea.split(",", -1)) {
                for (String t : Normaliza.terminos(palabra)) {
                    if (!grupo.contains(t)) {
                        grupo.add(t);
                    }
                }
            }
            for (String t : grupo) {
                List<String> lista = companeros.computeIfAbsent(t, k -> new ArrayList<>());
                for (String otro : grupo) {
                    if (!otro.equals(t) && !lista.contains(otro)) {
                        lista.add(otro);
                    }
                }
            }
        }
    }

    /** Los mismos términos más los de sus grupos, sin repetir los que ya estaban. */
    public List<String> expandir(List<String> terminos) {
        List<String> resultado = new ArrayList<>(terminos);
        Set<String> presentes = new HashSet<>(terminos);
        for (String t : terminos) {
            for (String otro : companeros.getOrDefault(t, List.of())) {
                if (presentes.add(otro)) {
                    resultado.add(otro);
                }
            }
        }
        return resultado;
    }
}
