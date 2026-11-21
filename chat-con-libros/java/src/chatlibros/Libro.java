package chatlibros;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.List;

/** Port de src/Libro.php: un libro en texto plano, dividido en pasajes con su capítulo. */
public record Libro(String titulo, String autor, List<Pasaje> pasajes) {
    public static final String TITULO_POR_DEFECTO = "Libro sin título";
    public static final String AUTOR_POR_DEFECTO = "autor desconocido";

    public Libro {
        pasajes = List.copyOf(pasajes);
    }

    public static Libro desdeTexto(String texto) {
        if (texto.startsWith("﻿")) {
            texto = texto.substring(1);
        }

        String titulo = TITULO_POR_DEFECTO;
        String autor = AUTOR_POR_DEFECTO;
        List<Pasaje> pasajes = new ArrayList<>();
        String capitulo = "Inicio";
        List<String> parrafo = new ArrayList<>();

        for (String linea : texto.split("\r\n|\n|\r", -1)) {
            String l = linea.trim();
            String plano = Normaliza.texto(l);

            if (l.isEmpty()) {
                cerrarParrafo(parrafo, pasajes, capitulo);
            } else if (plano.startsWith("titulo:")) {
                titulo = valorDespuesDeDosPuntos(l);
            } else if (plano.startsWith("autor:")) {
                autor = valorDespuesDeDosPuntos(l);
            } else if (l.startsWith("#") || plano.startsWith("capitulo ")) {
                cerrarParrafo(parrafo, pasajes, capitulo);
                capitulo = l.replaceFirst("^[# ]+", "").trim();
            } else {
                parrafo.add(l);
            }
        }
        cerrarParrafo(parrafo, pasajes, capitulo);

        return new Libro(
                titulo.isEmpty() ? TITULO_POR_DEFECTO : titulo,
                autor.isEmpty() ? AUTOR_POR_DEFECTO : autor,
                pasajes);
    }

    public static Libro desdeArchivo(Path ruta) throws IOException {
        return desdeTexto(Files.readString(ruta, StandardCharsets.UTF_8));
    }

    private static String valorDespuesDeDosPuntos(String linea) {
        return linea.substring(linea.indexOf(':') + 1).trim();
    }

    private static void cerrarParrafo(List<String> parrafo, List<Pasaje> pasajes, String capitulo) {
        if (!parrafo.isEmpty()) {
            pasajes.add(new Pasaje(capitulo, String.join(" ", parrafo)));
            parrafo.clear();
        }
    }
}
