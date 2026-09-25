import java.util.Locale;
import java.util.StringJoiner;

/**
 * Arma objetos JSON planos campo por campo, en vez de un String.format con
 * varios "%s" posicionales: ahí, insertar/quitar/reordenar un campo puede
 * desalinizar silenciosamente un valor con el placeholder de otro (mismo tipo,
 * ningún error de compilación ni de runtime). Acá cada campo lleva su nombre
 * al lado del valor, así que ese desalineamiento no puede pasar.
 */
final class JsonBuilder {

    private final StringJoiner campos = new StringJoiner(",", "{", "}");

    JsonBuilder put(String clave, String valor) {
        campos.add(quoted(clave) + ":" + (valor == null ? "null" : quoted(valor)));
        return this;
    }

    JsonBuilder put(String clave, int valor) {
        campos.add(quoted(clave) + ":" + valor);
        return this;
    }

    /** Número con formato fijo (ej. "%.1f"); JSON null si valor es null (percentil sin datos). */
    JsonBuilder put(String clave, Double valor, String patron) {
        String texto = valor == null ? "null" : String.format(Locale.ROOT, patron, valor);
        campos.add(quoted(clave) + ":" + texto);
        return this;
    }

    String build() {
        return campos.toString();
    }

    private static String quoted(String s) {
        return "\"" + UtilHttp.escape(s) + "\"";
    }
}
