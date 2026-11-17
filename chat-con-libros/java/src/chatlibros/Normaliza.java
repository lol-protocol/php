package chatlibros;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Port de src/Normaliza.php: minúsculas, sin acentos, sin palabras vacías y con una raíz
 * muy simple. Los dos lados dan los mismos resultados (pruebas/vectores.tsv).
 */
public final class Normaliza {
    private static final String ORIGEN = "áàäâéèëêíìïîóòöôúùüûñç";
    // Mismo largo que ORIGEN, letra a letra: aaaa eeee iiii oooo uuuu n c.
    private static final String DESTINO = "aaaaeeeeiiiioooouuuunc";

    private static final Set<String> VACIAS = new HashSet<>(List.of(
            "a", "al", "como", "con", "cual", "cuales", "cuando", "de", "del", "donde", "e", "el",
            "ella", "ellas", "ellos", "en", "entre", "era", "es", "esa", "ese", "eso", "esta", "este",
            "esto", "estaba", "estaban", "fue", "ha", "habia", "han", "hay", "la", "las", "le", "les",
            "lo", "los", "mas", "me", "mi", "muy", "ni", "no", "o", "para", "pero", "por", "que", "quien",
            "se", "ser", "si", "sin", "tenia", "tiene", "tienen", "dice", "decia", "dijo", "hacia",
            "sobre", "son", "su", "sus", "te", "tu", "u", "un", "una", "unas", "unos", "y", "ya",
            "and", "an", "as", "at", "be", "by", "for", "in", "is", "it", "of", "on", "the", "this",
            "that", "to", "with"));

    private static final Pattern PALABRA = Pattern.compile("[a-z0-9]+");

    private Normaliza() {
    }

    /** Minúsculas y sin acentos (ñ pasa a n). */
    public static String texto(String s) {
        String minusculas = s.toLowerCase(Locale.ROOT);
        StringBuilder sb = new StringBuilder(minusculas.length());
        for (int i = 0; i < minusculas.length(); i++) {
            char c = minusculas.charAt(i);
            int k = ORIGEN.indexOf(c);
            sb.append(k >= 0 ? DESTINO.charAt(k) : c);
        }
        return sb.toString();
    }

    /** Palabras tal como se leen (sin acentos, en minúsculas), sin palabras vacías. */
    public static List<String> palabras(String s) {
        Matcher m = PALABRA.matcher(texto(s));
        List<String> palabras = new ArrayList<>();
        while (m.find()) {
            String palabra = m.group();
            if (palabra.length() < 2 || VACIAS.contains(palabra)) {
                continue;
            }
            palabras.add(palabra);
        }
        return palabras;
    }

    /** Términos de búsqueda: las palabras de palabras() con la raíz aplicada. */
    public static List<String> terminos(String s) {
        List<String> terminos = new ArrayList<>();
        for (String palabra : palabras(s)) {
            terminos.add(raiz(palabra));
        }
        return terminos;
    }

    /** Terminaciones que se quitan después del plural, de la más larga a la más corta (ver src/Normaliza.php). */
    private static final String[] SUFIJOS = {"mente", "aban", "iendo", "ando", "aba", "ado", "ido", "ar", "er", "ir"};

    /**
     * Raíz muy simple: quita «-es» (más de 4 letras) o «-s» (más de 3) del plural y, después,
     * una terminación de SUFIJOS, solo si queda una raíz de 4 letras o más.
     */
    public static String raiz(String palabra) {
        String p = palabra;
        int largo = p.length();
        if (largo > 4 && p.endsWith("es")) {
            p = p.substring(0, largo - 2);
        } else if (largo > 3 && p.endsWith("s")) {
            p = p.substring(0, largo - 1);
        }
        for (String sufijo : SUFIJOS) {
            if (p.endsWith(sufijo) && p.length() - sufijo.length() >= 4) {
                return p.substring(0, p.length() - sufijo.length());
            }
        }
        return p;
    }
}
