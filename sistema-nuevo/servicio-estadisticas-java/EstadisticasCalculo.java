import java.util.List;
import java.util.Locale;
import java.util.stream.Collectors;

/** Cálculo de agregados (promedio/mediana/p90) y armado del JSON de respuesta de /stats. */
final class EstadisticasCalculo {

    private EstadisticasCalculo() {
    }

    static String buildStatsJson(String type, List<Accion> cohort) {
        List<Double> durations = cohort.stream().map(Accion::durationMs).sorted().collect(Collectors.toList());
        double avgDuration = durations.stream().mapToDouble(Double::doubleValue).average().orElse(0);

        List<Double> amounts = cohort.stream()
                .map(Accion::amountUsd)
                .filter(a -> a != null)
                .sorted()
                .collect(Collectors.toList());
        Double avgAmount = amounts.isEmpty() ? null : amounts.stream().mapToDouble(Double::doubleValue).average().orElse(0);

        return String.format(Locale.ROOT,
                "{\"type\":\"%s\",\"count\":%d,\"avg_duration_ms\":%.1f,\"median_duration_ms\":%s,"
                + "\"p90_duration_ms\":%s,\"avg_amount_usd\":%s,\"median_amount_usd\":%s,"
                + "\"p90_amount_usd\":%s,\"currency\":\"USD\"}",
                UtilHttp.escape(type), cohort.size(), avgDuration,
                formatNullable(percentile(durations, 50), "%.1f"), formatNullable(percentile(durations, 90), "%.1f"),
                formatNullable(avgAmount, "%.2f"), formatNullable(percentile(amounts, 50), "%.2f"),
                formatNullable(percentile(amounts, 90), "%.2f"));
    }

    /** Percentil por interpolación lineal sobre una lista YA ORDENADA. Null si está vacía. */
    private static Double percentile(List<Double> sorted, double p) {
        if (sorted.isEmpty()) {
            return null;
        }
        double rank = (p / 100.0) * (sorted.size() - 1);
        int lower = (int) Math.floor(rank);
        int upper = (int) Math.ceil(rank);
        if (lower == upper) {
            return sorted.get(lower);
        }
        double fraction = rank - lower;
        return sorted.get(lower) + (sorted.get(upper) - sorted.get(lower)) * fraction;
    }

    private static String formatNullable(Double value, String pattern) {
        return value == null ? "null" : String.format(Locale.ROOT, pattern, value);
    }
}
