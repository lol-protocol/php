# Sugerencias de índices desde los datos

> Generado por `generar_tabla.py` (`sugerencias.py`). No editar a mano. Para cada índice, una regresión lineal sobre los datos con los puntajes actuales como objetivo. Se listan los países cuyo puntaje se aparta 1,5 puntos o más de lo que predicen sus datos: no son errores, son los casos que conviene revisar. Un dato faltante cuenta como la media de los demás países; los países a los que les falta más de 1 dato del modelo quedan fuera.

## IG

194 países (14 sin datos suficientes). R² = 0.48. Modelo: IG ≈ -1.86 − 0.01 × ln(1 + emigrantes %) + 6.42 × IDH − 0.75 × internet / 100.

| País | IG actual | Sugerido | Emigrantes (%) | IDH | Internet (%) | Alfabetización (%) | Libertad | CPI | Comentario |
|---|---|---|---|---|---|---|---|---|---|
| China | 5 | 2.6 | 0.8 | 0.797 | 92 | 97 | no libre | 42 | Gran tradición de jiapu, pero cortafuegos, licencias y localización obligatoria de datos; difícil de operar |
| Baréin | 1 | 3.2 | 3.6 | 0.899 | 100 | 98 | no libre | 42 | Reino pequeño; árabe e inglés; registros poco públicos; control estatal de internet (no libre según Freedom House) |
| Catar | 1 | 3.1 | 0.7 | 0.886 | 98 | 98 | no libre | 58 | Muy rico y estable; registros poco públicos; árabe e inglés; control estatal de internet (no libre según Freedom House) |
| Omán | 1 | 2.9 | 0.3 | 0.858 | 95 | 97 | no libre | 43 | Estable; restricciones a contenidos en línea; árabe e inglés; control estatal de internet (no libre según Freedom House) |
| Kuwait | 1 | 2.9 | 3.4 | 0.852 | 100 | 96 | parcial | 46 | Estable y solvente; sin registros civiles públicos de acceso abierto; árabe e inglés |
| Portugal | 5 | 3.2 | 17.3 | 0.890 | 88 | 94 | libre | 61 | Torre do Tombo y registros parroquiales muy completos; vínculo con la diáspora de Brasil; portugués; RGPD |
| Brunéi | 1 | 2.8 | 10.1 | 0.837 | 96 | 96 | no libre | 60 | Monarquía absoluta con código penal islámico desde 2019; mercado muy pequeño |
| Sudáfrica | 4 | 2.3 | 1.6 | 0.741 | 78 | 91 | libre | 41 | Diáspora europea y británica; archivos y registros accesibles; inglés; apagones y crimen; ley de datos (POPIA) |
| Italia | 5 | 3.3 | 5.0 | 0.915 | 89 | 99 | libre | 56 | Portal Antenati con registros civiles digitalizados; diáspora gigantesca en América; italiano; burocracia lenta |
| Corea del Sur | 5 | 3.4 | 3.9 | 0.937 | 98 | — | libre | 63 | Tradición de jokbo muy fuerte; registros familiares parcialmente digitalizados; coreano; ley de datos (PIPA) estricta |
| Estados Unidos | 5 | 3.4 | 0.9 | 0.938 | 95 | — | libre | 69 | Mayor mercado y diáspora; registros censales y de inmigración accesibles; inglés; privacidad regulada por estado |
| Canadá | 5 | 3.5 | 3.4 | 0.939 | 94 | — | libre | 76 | Registros civiles y de inmigración muy buenos; inglés y francés; leyes de privacidad fuertes (PIPEDA) |
| Reino Unido | 5 | 3.5 | 6.9 | 0.946 | 96 | — | libre | 71 | Registros civiles y censales muy completos; diáspora mundial; inglés; RGPD del Reino Unido |
| Cuba | 4 | 2.5 | 17.1 | 0.762 | 70 | 98 | no libre | 42 | Embargo de EE. UU.; control estatal de internet y de medios; pagos casi imposibles; registros coloniales valiosos pero de acceso restringido |
| Irlanda | 5 | 3.5 | 13.6 | 0.949 | 97 | — | libre | 77 | Gran diáspora irlandesa; inglés; sede europea de muchas tecnológicas y de su autoridad de datos |

## IO

175 países (33 sin datos suficientes). R² = 0.81. Modelo: IO ≈ 0.06 + 0.35 × libertad (0–2) + 2.68 × CPI / 100 + 0.84 × EIU / 100 − 0.27 × internet / 100 + 1.59 × IDH.

Ningún país se aparta tanto.

## IL

191 países (17 sin datos suficientes). R² = 0.79. Modelo: IL ≈ -1.62 + 1.11 × alfabetización / 100 − 0.45 × internet / 100 + 5.78 × IDH.

Ningún país se aparta tanto.
