package chatlibros;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Port de src/Indice.php: TF-IDF con similitud coseno. Suma en el mismo orden que la versión
 * PHP (orden de aparición de los términos) para que los puntajes coincidan.
 */
public final class Indice {
    /** Un resultado: número de pasaje y puntaje coseno. */
    public record Resultado(int pasaje, double puntaje) {
    }

    private final Map<String, Double> idf = new HashMap<>();
    private final List<Map<String, Double>> vectores = new ArrayList<>();

    /** @param pasajes términos normalizados de cada pasaje */
    public Indice(List<List<String>> pasajes) {
        int n = pasajes.size();
        Map<String, Integer> df = new HashMap<>();
        for (List<String> terminos : pasajes) {
            for (String t : new java.util.LinkedHashSet<>(terminos)) {
                df.merge(t, 1, Integer::sum);
            }
        }
        for (Map.Entry<String, Integer> e : df.entrySet()) {
            idf.put(e.getKey(), Math.log((double) (n + 1) / (e.getValue() + 1)) + 1.0);
        }
        for (List<String> terminos : pasajes) {
            vectores.add(vector(terminos));
        }
    }

    /** Pasajes más parecidos a la consulta, del más parecido al menos parecido. */
    public List<Resultado> buscar(List<String> terminos, int k) {
        Map<String, Double> consulta = vector(terminos);
        List<Resultado> resultados = new ArrayList<>();
        for (int i = 0; i < vectores.size(); i++) {
            Map<String, Double> vector = vectores.get(i);
            double puntaje = 0.0;
            for (Map.Entry<String, Double> e : consulta.entrySet()) {
                Double peso = vector.get(e.getKey());
                if (peso != null) {
                    puntaje += e.getValue() * peso;
                }
            }
            resultados.add(new Resultado(i, puntaje));
        }
        resultados.sort((a, b) -> {
            int porPuntaje = Double.compare(b.puntaje(), a.puntaje());
            return porPuntaje != 0 ? porPuntaje : Integer.compare(a.pasaje(), b.pasaje());
        });
        return List.copyOf(resultados.subList(0, Math.min(Math.max(0, k), resultados.size())));
    }

    private Map<String, Double> vector(List<String> terminos) {
        Map<String, Double> v = new LinkedHashMap<>();
        for (String t : terminos) {
            if (!idf.containsKey(t)) {
                continue;
            }
            v.merge(t, 1.0, Double::sum);
        }
        double norma = 0.0;
        for (Map.Entry<String, Double> e : v.entrySet()) {
            e.setValue(e.getValue() * idf.get(e.getKey()));
            norma += e.getValue() * e.getValue();
        }
        norma = Math.sqrt(norma);
        if (norma > 0.0) {
            for (Map.Entry<String, Double> e : v.entrySet()) {
                e.setValue(e.getValue() / norma);
            }
        }
        return v;
    }
}
