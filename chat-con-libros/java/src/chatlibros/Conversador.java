package chatlibros;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.regex.Pattern;
import java.util.stream.Collectors;

/** Port de src/Conversador.php: responde citando pasajes del libro, sin generar texto nuevo. */
public final class Conversador {
    /** Puntaje coseno mínimo para citar un pasaje. */
    public static final double UMBRAL = 0.10;
    /** Cuántos pasajes se citan como mucho. */
    public static final int MAX_CITAS = 2;

    private static final Pattern SALUDO = Pattern.compile("^(hola|buenas|buenos dias|buenas tardes|buenas noches|saludos|hey)\\b");
    private static final Pattern AYUDA = Pattern.compile("\\b(ayuda|que puedo preguntar)\\b");
    private static final Pattern AUTOR = Pattern.compile("^(quien|quienes)\\b.*\\b(escribio|escrito|autor|autora)\\b");
    private static final Pattern TITULO = Pattern.compile("\\b(como se llama|titulo del libro|nombre del libro)\\b");
    private static final Pattern RESUMEN = Pattern.compile("^(de que (trata|va|se trata)|resumen|resumeme)\\b");
    private static final Pattern RESUMEN_EN_FRASE = Pattern.compile("\\bde que (trata|va) el libro\\b");
    private static final Pattern SIGNOS_DE_APERTURA = Pattern.compile("^[¿¡\\s]+");

    private final Libro libro;
    private final Indice indice;
    private final List<String> temas;

    public Conversador(Libro libro) {
        this.libro = libro;
        List<List<String>> terminosPorPasaje = new ArrayList<>();
        Map<String, Integer> cuenta = new HashMap<>();
        for (Pasaje pasaje : libro.pasajes()) {
            terminosPorPasaje.add(Normaliza.terminos(pasaje.texto()));
            // Para mostrar los temas se cuenta la palabra tal como está escrita, no su raíz.
            for (String palabra : Normaliza.palabras(pasaje.texto())) {
                cuenta.merge(palabra, 1, Integer::sum);
            }
        }
        this.indice = new Indice(terminosPorPasaje);

        this.temas = cuenta.entrySet().stream()
                .sorted((a, b) -> {
                    int porVeces = Integer.compare(b.getValue(), a.getValue());
                    return porVeces != 0 ? porVeces : a.getKey().compareTo(b.getKey());
                })
                .limit(5)
                .map(Map.Entry::getKey)
                .collect(Collectors.toList());
    }

    public Libro libro() {
        return libro;
    }

    public String responder(String pregunta) {
        String p = SIGNOS_DE_APERTURA.matcher(Normaliza.texto(pregunta.trim())).replaceFirst("");
        String titulo = libro.titulo();
        String autor = libro.autor();

        if (p.isEmpty()) {
            return "Escribe una pregunta sobre el libro.";
        }
        if (SALUDO.matcher(p).find()) {
            return "Hola. Soy el asistente de «" + titulo + "», de " + autor + ". Pregúntame algo del libro: "
                    + "te respondo citando sus pasajes.";
        }
        if (AYUDA.matcher(p).find()) {
            return "Puedo responder citando pasajes del libro. Pregunta por un tema, un personaje "
                    + "o un objeto (por ejemplo, «¿qué pasa con el reloj?»), o pide «un resumen», "
                    + "«quién lo escribió» o «cómo se llama». Si el libro no habla de algo, te lo digo.";
        }
        if (AUTOR.matcher(p).find()) {
            return "«" + titulo + "» lo escribió " + autor + ".";
        }
        if (TITULO.matcher(p).find()) {
            return "El libro se llama «" + titulo + "».";
        }
        if (RESUMEN.matcher(p).find() || RESUMEN_EN_FRASE.matcher(p).find()) {
            return resumen();
        }
        return citar(pregunta);
    }

    private String resumen() {
        String temas = this.temas.isEmpty() ? "nada que destacar" : String.join(", ", this.temas);
        String primero = libro.pasajes().isEmpty() ? "" : libro.pasajes().get(0).texto();
        String inicio = primero.isEmpty() ? "" : " Empieza así: «" + extracto(primero, 300) + "»";
        return "«" + libro.titulo() + "», de " + libro.autor() + ", trata sobre: " + temas + "." + inicio;
    }

    private String citar(String pregunta) {
        List<String> lineas = new ArrayList<>();
        for (Indice.Resultado r : indice.buscar(Normaliza.terminos(pregunta), MAX_CITAS)) {
            if (r.puntaje() < UMBRAL) {
                continue;
            }
            Pasaje pasaje = libro.pasajes().get(r.pasaje());
            lineas.add("— [" + pasaje.capitulo() + "] «" + extracto(pasaje.texto(), 350) + "»");
        }
        if (lineas.isEmpty()) {
            return "No encuentro eso en «" + libro.titulo() + "». Prueba con otras palabras "
                    + "o pregunta por algún tema del libro.";
        }
        lineas.add(0, "Esto dice el libro:");
        return String.join("\n\n", lineas);
    }

    /** Corta un texto en el último espacio antes de max caracteres y agrega «…». */
    public static String extracto(String texto, int max) {
        if (texto.length() <= max) {
            return texto;
        }
        String corte = texto.substring(0, max);
        int espacio = corte.lastIndexOf(' ');
        if (espacio >= 0 && espacio > max * 0.6) {
            corte = corte.substring(0, espacio);
        }
        int fin = corte.length();
        while (fin > 0 && " ,;:".indexOf(corte.charAt(fin - 1)) >= 0) {
            fin--;
        }
        return corte.substring(0, fin) + "…";
    }
}
