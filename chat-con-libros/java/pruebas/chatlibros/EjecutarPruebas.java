package chatlibros;

import java.io.FileDescriptor;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.PrintStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;
import java.util.Locale;

/**
 * Pruebas del port a Java, sin dependencias. Se corren desde la carpeta del proyecto
 * (chat-con-libros/) y comprueban lo mismo que pruebas/ejecutar.php:
 *
 *   javac -encoding UTF-8 -d /tmp/out $(find java -name '*.java')
 *   java -cp /tmp/out chatlibros.EjecutarPruebas
 */
public final class EjecutarPruebas {
    private static int total = 0;
    private static int fallos = 0;

    private EjecutarPruebas() {
    }

    private static void comprobar(String nombre, boolean ok, String detalle) {
        total++;
        if (ok) {
            System.out.println("  ok    " + nombre);
            return;
        }
        fallos++;
        System.out.println("  FALLA " + nombre + (detalle.isEmpty() ? "" : " — " + detalle));
    }

    private static void comprobar(String nombre, boolean ok) {
        comprobar(nombre, ok, "");
    }

    public static void main(String[] args) throws IOException {
        // La salida va en UTF-8 aunque la configuración regional de la JVM no lo sea.
        System.setOut(new PrintStream(new FileOutputStream(FileDescriptor.out), true, StandardCharsets.UTF_8));
        Path raiz = Path.of(".");
        Libro libro = Libro.desdeArchivo(raiz.resolve("ejemplos/muestra.txt"));
        Conversador conversador = new Conversador(libro);

        System.out.println("Normaliza");
        comprobar("texto quita acentos y pasa a minúsculas",
                Normaliza.texto("Él dijo: ¡Mañana, en la CAÑADA!").equals("el dijo: ¡manana, en la canada!"));
        comprobar("palabras descarta vacías y cortas",
                Normaliza.palabras("Los sueños de la casa, y a").equals(List.of("suenos", "casa")));
        comprobar("raiz quita -es y -s",
                Normaliza.raiz("reyes").equals("rey") && Normaliza.raiz("relojeros").equals("relojero"));
        comprobar("raiz deja las palabras cortas",
                Normaliza.raiz("mes").equals("mes") && Normaliza.raiz("casa").equals("casa"));
        comprobar("terminos aplica raíz", Normaliza.terminos("Los sueños de los relojeros").equals(List.of("sueno", "relojero")));

        System.out.println("Libro");
        comprobar("lee título y autor",
                libro.titulo().equals("Las horas de Tomás") && libro.autor().startsWith("Elena Varela"));
        comprobar("cuenta los 10 pasajes", libro.pasajes().size() == 10, String.valueOf(libro.pasajes().size()));
        comprobar("asigna el capítulo a cada pasaje",
                libro.pasajes().get(0).capitulo().equals("Capítulo 1: El relojero")
                        && libro.pasajes().get(5).capitulo().equals("Capítulo 2: La carta")
                        && libro.pasajes().get(9).capitulo().equals("Capítulo 3: El puerto"));
        boolean sinEncabezados = libro.pasajes().stream().noneMatch(p -> p.texto().contains("Autor:"));
        comprobar("no mete los títulos ni los encabezados como pasajes", sinEncabezados);
        Libro sin = Libro.desdeTexto("Un párrafo suelto.\n\nOtro.");
        comprobar("sin título ni autor usa valores por defecto",
                sin.titulo().equals(Libro.TITULO_POR_DEFECTO) && sin.autor().equals(Libro.AUTOR_POR_DEFECTO));
        comprobar("un párrafo de varias líneas se une",
                Libro.desdeTexto("Línea uno\nlínea dos\n\nFin").pasajes().get(0).texto().equals("Línea uno línea dos"));
        comprobar("quita el BOM de UTF-8", Libro.desdeTexto("﻿Título: X\n\nHola").titulo().equals("X"));

        System.out.println("Indice");
        Indice indice = new Indice(List.of(List.of("gato", "perro"), List.of("perro", "perro", "casa"), List.of("mar", "sal")));
        comprobar("el pasaje con más repeticiones gana", indice.buscar(List.of("perro"), 3).get(0).pasaje() == 1);
        comprobar("un término ausente da puntaje 0", indice.buscar(List.of("dragon"), 1).get(0).puntaje() == 0.0);
        comprobar("los empates los decide el número de pasaje",
                new Indice(List.of(List.of("x"), List.of("x"))).buscar(List.of("x"), 2).get(0).pasaje() == 0);
        comprobar("puntaje coseno máximo 1 para el mismo pasaje",
                Math.abs(indice.buscar(List.of("mar", "sal"), 1).get(0).puntaje() - 1.0) < 1e-9);

        System.out.println("Conversador");
        comprobar("saluda con el título", conversador.responder("hola").contains("Las horas de Tomás"));
        comprobar("dice quién lo escribió",
                conversador.responder("¿quién escribió esto?").contains("lo escribió Elena Varela"));
        comprobar("dice cómo se llama",
                conversador.responder("¿cómo se llama?").equals("El libro se llama «Las horas de Tomás»."));
        String resumen = conversador.responder("¿de qué trata?");
        comprobar("resume con temas y el inicio", resumen.contains("trata sobre: ") && resumen.contains("Empieza así"));
        comprobar("un tema del libro cita pasajes con su capítulo",
                conversador.responder("¿qué pasa con la carta de la hermana?").contains("[Capítulo 2: La carta]"));
        comprobar("cita el pasaje sobre la llave", conversador.responder("la llave de la torre").contains("llave de la torre"));
        comprobar("no inventa lo que no está", conversador.responder("¿hay dragones?").startsWith("No encuentro eso"));
        comprobar("pregunta vacía", conversador.responder("   ").equals("Escribe una pregunta sobre el libro."));
        comprobar("\"qué dice el autor sobre X\" busca, no da el autor",
                conversador.responder("qué dice el autor sobre la lluvia").contains("Esto dice el libro"));
        comprobar("extracto corta en un espacio y agrega «…»",
                Conversador.extracto("palabra ".repeat(100), 50).equals("palabra palabra palabra palabra palabra palabra…"));
        comprobar("extracto no toca un texto corto", Conversador.extracto("corto", 50).equals("corto"));

        System.out.println("Vectores (pruebas/vectores.tsv)");
        Indice indiceLibro = new Indice(libro.pasajes().stream()
                .map(p -> Normaliza.terminos(p.texto())).toList());
        int filas = 0;
        for (String linea : Files.readAllLines(raiz.resolve("pruebas/vectores.tsv"), StandardCharsets.UTF_8)) {
            if (linea.isBlank() || linea.startsWith("#")) {
                continue;
            }
            String[] campos = linea.split("\t");
            Indice.Resultado mejor = indiceLibro.buscar(Normaliza.terminos(campos[0]), 1).get(0);
            filas++;
            String obtenido = String.format(Locale.ROOT, "%.6f", mejor.puntaje());
            comprobar("«" + campos[0] + "» → pasaje " + campos[1] + ", puntaje " + campos[2],
                    Integer.parseInt(campos[1]) == mejor.pasaje() && obtenido.equals(campos[2]),
                    "obtuve pasaje " + mejor.pasaje() + ", puntaje " + obtenido);
        }
        comprobar("el archivo tiene consultas", filas > 0);

        System.out.println("\n" + total + " pruebas, " + fallos + " fallos");
        System.exit(fallos == 0 ? 0 : 1);
    }
}
