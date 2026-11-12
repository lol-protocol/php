import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;

/**
 * Runs the PHP parser's evaluation set (../../tests/fixtures/parser-evaluation.json) through this port.
 *
 * Every case must come out exactly as written, except the ones listed in ../fallas-conocidas.txt, which
 * must keep failing: a change that fixes one removes it from the list, and one that breaks another fails
 * here. Same rule as ParserEvaluationTest in PHP and test_evaluation_set.py in the Python port.
 */
public final class EvaluationSetCheck {
    private EvaluationSetCheck() {
    }

    /** Returns the ids that do not behave as the list says (empty when all is well). */
    @SuppressWarnings("unchecked")
    public static List<String> wrongCases(Path set, Path knownFailuresFile) throws IOException {
        Map<String, Object> data = (Map<String, Object>) new Json(Files.readString(set, StandardCharsets.UTF_8)).parse();
        Set<String> knownFailures = new HashSet<>();
        for (String line : Files.readAllLines(knownFailuresFile, StandardCharsets.UTF_8)) {
            if (!line.isBlank() && !line.startsWith("#")) {
                knownFailures.add(line.trim());
            }
        }

        List<String> wrong = new ArrayList<>();
        Set<String> ids = new HashSet<>();
        for (Object item : (List<Object>) data.get("casos")) {
            Map<String, Object> c = (Map<String, Object>) item;
            String id = (String) c.get("id");
            ids.add(id);
            if (passes(c) == knownFailures.contains(id)) {
                wrong.add(id);
            }
        }
        for (String id : knownFailures) {
            if (!ids.contains(id)) {
                wrong.add(id + " (no está en el conjunto)");
            }
        }

        return wrong;
    }

    @SuppressWarnings("unchecked")
    private static boolean passes(Map<String, Object> c) {
        MultiLanguagePhoneDirectoryParser parser = new MultiLanguagePhoneDirectoryParser();
        List<Map<String, Object>> entries = parser.parseContent((String) c.get("entrada"), (String) c.get("idioma"));

        List<Map<String, Object>> actual = new ArrayList<>();
        for (Map<String, Object> e : entries) {
            Map<String, Object> row = new LinkedHashMap<>();
            row.put("tipo", e.get("type"));
            row.put("nombre", "natural".equals(e.get("type"))
                ? ((Map<String, Object>) e.get("person_name")).get("rawName")
                : e.get("business_name"));
            row.put("calle", ((Map<String, Object>) e.get("location")).get("street"));
            row.put("telefono", e.get("phone"));
            actual.add(row);
        }

        Object expectedLanguage = c.get("idioma_detectado");
        return actual.equals(c.get("esperado"))
            && (expectedLanguage == null || expectedLanguage.equals(parser.getDetectedLanguage()));
    }

    /** Just enough JSON for the evaluation set: objects, arrays, strings, null, true and false. */
    private static final class Json {
        private final String text;
        private int pos;

        Json(String text) {
            this.text = text;
        }

        Object parse() {
            skipSpace();
            char c = text.charAt(pos);
            if (c == '{') {
                return object();
            }
            if (c == '[') {
                return array();
            }
            if (c == '"') {
                return string();
            }
            for (String word : List.of("null", "true", "false")) {
                if (text.startsWith(word, pos)) {
                    pos += word.length();
                    return word.equals("null") ? null : Boolean.valueOf(word);
                }
            }
            throw new IllegalArgumentException("JSON no soportado en la posición " + pos);
        }

        private Map<String, Object> object() {
            Map<String, Object> map = new LinkedHashMap<>();
            pos++;
            skipSpace();
            if (text.charAt(pos) == '}') {
                pos++;
                return map;
            }
            while (true) {
                skipSpace();
                String key = string();
                skipSpace();
                pos++; // ':'
                map.put(key, parse());
                skipSpace();
                if (text.charAt(pos++) == '}') {
                    return map;
                }
            }
        }

        private List<Object> array() {
            List<Object> list = new ArrayList<>();
            pos++;
            skipSpace();
            if (text.charAt(pos) == ']') {
                pos++;
                return list;
            }
            while (true) {
                list.add(parse());
                skipSpace();
                if (text.charAt(pos++) == ']') {
                    return list;
                }
            }
        }

        private String string() {
            StringBuilder sb = new StringBuilder();
            pos++;
            while (true) {
                char c = text.charAt(pos++);
                if (c == '"') {
                    return sb.toString();
                }
                if (c != '\\') {
                    sb.append(c);
                    continue;
                }
                char e = text.charAt(pos++);
                switch (e) {
                    case 'n' -> sb.append('\n');
                    case 't' -> sb.append('\t');
                    case 'u' -> {
                        sb.append((char) Integer.parseInt(text.substring(pos, pos + 4), 16));
                        pos += 4;
                    }
                    default -> sb.append(e);
                }
            }
        }

        private void skipSpace() {
            while (pos < text.length() && Character.isWhitespace(text.charAt(pos))) {
                pos++;
            }
        }
    }
}
