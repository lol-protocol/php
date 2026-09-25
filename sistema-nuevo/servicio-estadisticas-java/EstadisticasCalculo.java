import java.util.List;
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

        return new JsonBuilder()
                .put("type", type)
                .put("count", cohort.size())
                .put("avg_duration_ms", avgDuration, "%.1f")
                .put("median_duration_ms", percentile(durations, 50), "%.1f")
                .put("p90_duration_ms", percentile(durations, 90), "%.1f")
                .put("avg_amount_usd", avgAmount, "%.2f")
                .put("median_amount_usd", percentile(amounts, 50), "%.2f")
                .put("p90_amount_usd", percentile(amounts, 90), "%.2f")
                .put("currency", "USD")
                .build();
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
}
