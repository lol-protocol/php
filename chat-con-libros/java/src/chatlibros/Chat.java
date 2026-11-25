package chatlibros;

import java.io.BufferedReader;
import java.io.FileDescriptor;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.PrintStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;
import java.util.Locale;

/**
 * Chat en la terminal con un libro (equivale a bin/chat.php):
 *
 *   java -cp out chatlibros.Chat                 # usa ejemplos/muestra.txt
 *   java -cp out chatlibros.Chat ruta/libro.txt
 */
public final class Chat {
    private Chat() {
    }

    public static void main(String[] args) throws IOException {
        System.setOut(new PrintStream(new FileOutputStream(FileDescriptor.out), true, StandardCharsets.UTF_8));
        Path ruta = Path.of(args.length > 0 ? args[0] : "ejemplos/muestra.txt");
        if (!Files.isRegularFile(ruta)) {
            System.err.println("No encuentro el libro: " + ruta);
            System.exit(1);
        }
        Libro libro = Libro.desdeArchivo(ruta);
        if (libro.pasajes().isEmpty()) {
            System.err.println("El archivo no tiene texto que leer: " + ruta);
            System.exit(1);
        }
        // Los sinónimos generales (se buscan desde la carpeta del proyecto) y los del libro (<libro>.sinonimos.tsv).
        Sinonimos sinonimos = Sinonimos.desdeArchivos(
                Path.of("datos/sinonimos.tsv"),
                Path.of(ruta.toString().replaceFirst("(?i)\\.txt$", "") + ".sinonimos.tsv"));
        Conversador conversador = new Conversador(libro, sinonimos);

        System.out.println("«" + libro.titulo() + "», de " + libro.autor() + " — " + libro.pasajes().size() + " pasajes.");
        System.out.println("Escribe tu pregunta (o «salir»).");

        BufferedReader entrada = new BufferedReader(new InputStreamReader(System.in, StandardCharsets.UTF_8));
        while (true) {
            System.out.print("\n> ");
            String linea = entrada.readLine();
            if (linea == null) {
                System.out.println();
                break;
            }
            String pregunta = linea.trim();
            if (List.of("salir", "exit", "chao").contains(pregunta.toLowerCase(Locale.ROOT))) {
                break;
            }
            System.out.println(conversador.responder(pregunta));
        }
    }
}
