import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.util.Arrays;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.stream.Collectors;

/** Maneja GET /stats: agrega duración/monto (USD) de un tipo de acción para un universo. */
final class ManejadorEstadisticas implements HttpHandler {

    private final List<Accion> acciones;

    ManejadorEstadisticas(List<Accion> acciones) {
        this.acciones = acciones;
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        try {
            Map<String, String> params = UtilHttp.parseQuery(exchange.getRequestURI().getRawQuery());
            String type = params.get("type");
            if (type == null || type.isBlank()) {
                UtilHttp.respond(exchange, 400, "{\"error\":\"missing required parameter: type\"}");
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

            int ageMin = UtilHttp.parseIntOr(params.get("age_min"), 0);
            int ageMax = UtilHttp.parseIntOr(params.get("age_max"), 150);
            String gender = params.getOrDefault("gender", "all").toUpperCase(Locale.ROOT);
            String excludeUser = params.get("exclude");

            List<String> finalCountries = countries;
            List<Accion> cohort = acciones.stream()
                    .filter(a -> a.type().equals(type))
                    .filter(a -> finalCountries == null || finalCountries.contains(a.country()))
                    .filter(a -> a.age() >= ageMin && a.age() <= ageMax)
                    .filter(a -> gender.equals("ALL") || a.gender().equalsIgnoreCase(gender))
                    .filter(a -> excludeUser == null || !a.userId().equals(excludeUser))
                    .collect(Collectors.toList());

            UtilHttp.respond(exchange, 200, EstadisticasCalculo.buildStatsJson(type, cohort));
        } catch (Exception e) {
            String message = e.getMessage() == null ? e.toString() : e.getMessage();
            UtilHttp.respond(exchange, 500, "{\"error\":\"" + UtilHttp.escape(message) + "\"}");
        }
    }
}
