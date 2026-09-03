import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpServer;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.stream.Collectors;

/**
 * Motor de estadisticas del backoffice: agrega duracion/monto (en USD) de acciones de
 * usuario por "universo" de comparacion (paises, rango de edad, genero). Sin
 * dependencias externas (solo JDK) para poder compilar/ejecutar sin acceso a red.
 *
 * Uso: java ServicioEstadisticas.java [ruta-a-acciones-planas.csv] [puerto]
 */
public class ServicioEstadisticas {

    record Action(String userId, String type, double durationMs, Double amountUsd,
                  String country, int age, String gender) {
    }

    private static List<Action> ACTIONS;

    public static void main(String[] args) throws IOException {
        Path csvPath = resolveCsvPath(args.length > 0 ? args[0] : null);
        int port = args.length > 1 ? Integer.parseInt(args[1]) : 8081;

        ACTIONS = loadActions(csvPath);
        System.out.println("Cargadas " + ACTIONS.size() + " acciones desde " + csvPath);

        HttpServer server = HttpServer.create(new InetSocketAddress(port), 0);
        server.createContext("/stats", new StatsHandler());
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

    private static List<Action> loadActions(Path csvPath) throws IOException {
        List<Action> actions = new ArrayList<>();
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
                actions.add(new Action(f[0], f[1], Double.parseDouble(f[2]), amount, f[4], Integer.parseInt(f[5]), f[6]));
            }
        }
        return actions;
    }

    static class StatsHandler implements HttpHandler {
        @Override
        public void handle(HttpExchange exchange) throws IOException {
            try {
                Map<String, String> params = parseQuery(exchange.getRequestURI().getRawQuery());
                String type = params.get("type");
                if (type == null || type.isBlank()) {
                    respond(exchange, 400, "{\"error\":\"missing required parameter: type\"}");
                    return;
                }

                List<String> countries = null;
                String countriesParam = params.get("countries");
                if (countriesParam != null && !countriesParam.isBlank()) {
                    countries = Arrays.stream(countriesParam.split(","))
                            .map(s -> s.trim().toUpperCase(Locale.ROOT))
                            .filter(s -> !s.isEmpty())
                            .collect(Collectors.toList());
                }

                int ageMin = parseIntOr(params.get("age_min"), 0);
                int ageMax = parseIntOr(params.get("age_max"), 150);
                String gender = params.getOrDefault("gender", "all").toUpperCase(Locale.ROOT);
                String excludeUser = params.get("exclude");

                List<String> finalCountries = countries;
                List<Action> cohort = ACTIONS.stream()
                        .filter(a -> a.type().equals(type))
                        .filter(a -> finalCountries == null || finalCountries.contains(a.country()))
                        .filter(a -> a.age() >= ageMin && a.age() <= ageMax)
                        .filter(a -> gender.equals("ALL") || a.gender().equalsIgnoreCase(gender))
                        .filter(a -> excludeUser == null || !a.userId().equals(excludeUser))
                        .collect(Collectors.toList());

                int count = cohort.size();
                double avgDuration = cohort.stream().mapToDouble(Action::durationMs).average().orElse(0);
                List<Double> amounts = cohort.stream()
                        .map(Action::amountUsd)
                        .filter(a -> a != null)
                        .collect(Collectors.toList());
                Double avgAmount = amounts.isEmpty()
                        ? null
                        : amounts.stream().mapToDouble(Double::doubleValue).average().orElse(0);

                String json = String.format(Locale.ROOT,
                        "{\"type\":\"%s\",\"count\":%d,\"avg_duration_ms\":%.1f,\"avg_amount_usd\":%s,\"currency\":\"USD\"}",
                        escape(type), count, avgDuration,
                        avgAmount == null ? "null" : String.format(Locale.ROOT, "%.2f", avgAmount));

                respond(exchange, 200, json);
            } catch (Exception e) {
                String message = e.getMessage() == null ? e.toString() : e.getMessage();
                respond(exchange, 500, "{\"error\":\"" + escape(message) + "\"}");
            }
        }
    }

    private static int parseIntOr(String s, int fallback) {
        if (s == null || s.isBlank()) {
            return fallback;
        }
        try {
            return Integer.parseInt(s.trim());
        } catch (NumberFormatException e) {
            return fallback;
        }
    }

    private static Map<String, String> parseQuery(String rawQuery) {
        Map<String, String> result = new HashMap<>();
        if (rawQuery == null || rawQuery.isBlank()) {
            return result;
        }
        for (String pair : rawQuery.split("&")) {
            int eq = pair.indexOf('=');
            String key = eq >= 0 ? pair.substring(0, eq) : pair;
            String value = eq >= 0 ? pair.substring(eq + 1) : "";
            result.put(URLDecoder.decode(key, StandardCharsets.UTF_8), URLDecoder.decode(value, StandardCharsets.UTF_8));
        }
        return result;
    }

    private static String escape(String s) {
        return s.replace("\\", "\\\\").replace("\"", "\\\"");
    }

    private static void respond(HttpExchange exchange, int status, String json) throws IOException {
        byte[] body = json.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().set("Content-Type", "application/json; charset=utf-8");
        exchange.sendResponseHeaders(status, body.length);
        try (OutputStream os = exchange.getResponseBody()) {
            os.write(body);
        }
    }
}
