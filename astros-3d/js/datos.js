// Generado por generar.js desde datos/astros.json: no lo edites a mano.
window.ASTROS_DATOS = {
  "version": "1.0.0",
  "nota": "Valores de referencia redondeados (NASA Planetary Fact Sheet y fichas de satélites; estrellas, valores aproximados de la literatura). Las estrellas de radio incierto llevan incertidumbre «alta». Las texturas son procedurales: colores aproximados, no fotografías ni mapas científicos.",
  "categorias": {
    "planeta": "Planetas y el planeta enano Plutón",
    "luna": "Lunas del Sistema Solar (con su cuerpo padre)",
    "sol": "Soles: el Sol y cinco estrellas de referencia"
  },
  "astros": [
    {
      "id": "mercurio",
      "nombre": "Mercurio",
      "categoria": "planeta",
      "tipo": "Planeta rocoso",
      "radio_km": 2439.7,
      "masa_kg": 3.301e+23,
      "periodo_rotacion_h": 1407.6,
      "periodo_orbital_dias": 87.97,
      "inclinacion_eje_grados": 0.03,
      "distancia_media": {
        "valor": 57.9,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 440,
      "atmosfera": "Casi inexistente (exosfera)",
      "anillos": false,
      "lunas": 0,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#5e5853",
          "#8c8580",
          "#b3ada6"
        ],
        "craters": 260,
        "escala": 4
      },
      "gravedad_ms2": 3.7013390935421295,
      "densidad_kg_m3": 5426.847735155947
    },
    {
      "id": "venus",
      "nombre": "Venus",
      "categoria": "planeta",
      "tipo": "Planeta rocoso",
      "radio_km": 6051.8,
      "masa_kg": 4.867e+24,
      "periodo_rotacion_h": -5832.5,
      "periodo_orbital_dias": 224.7,
      "inclinacion_eje_grados": 177.36,
      "distancia_media": {
        "valor": 108.2,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 737,
      "atmosfera": "Densa, de CO₂ con nubes de ácido sulfúrico",
      "anillos": false,
      "lunas": 0,
      "textura": {
        "tipo": "nubes",
        "colores": [
          "#d9b97a",
          "#e8d7a1",
          "#f5ecd0"
        ],
        "bandas": 4,
        "bandas_amplitud": 0.04,
        "halo": "#ffd98a"
      },
      "gravedad_ms2": 8.869076778142567,
      "densidad_kg_m3": 5242.265782799359
    },
    {
      "id": "tierra",
      "nombre": "Tierra",
      "categoria": "planeta",
      "tipo": "Planeta rocoso",
      "radio_km": 6371,
      "masa_kg": 5.972e+24,
      "periodo_rotacion_h": 23.934,
      "periodo_orbital_dias": 365.26,
      "inclinacion_eje_grados": 23.44,
      "distancia_media": {
        "valor": 149.6,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 288,
      "atmosfera": "Nitrógeno y oxígeno, con nubes de agua",
      "anillos": false,
      "lunas": 1,
      "textura": {
        "tipo": "tierra",
        "oceano": [
          "#14406f",
          "#1b4f9c",
          "#2e7dc4"
        ],
        "tierra": [
          "#3f7d3a",
          "#6b7f3a",
          "#8a7a4a"
        ],
        "hielo": "#f2f6fa",
        "nubes": "#ffffff",
        "halo": "#6fb6ff"
      },
      "gravedad_ms2": 9.819532032815959,
      "densidad_kg_m3": 5513.258738589094
    },
    {
      "id": "marte",
      "nombre": "Marte",
      "categoria": "planeta",
      "tipo": "Planeta rocoso",
      "radio_km": 3389.5,
      "masa_kg": 6.417e+23,
      "periodo_rotacion_h": 24.623,
      "periodo_orbital_dias": 686.98,
      "inclinacion_eje_grados": 25.19,
      "distancia_media": {
        "valor": 227.9,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 210,
      "atmosfera": "Fina, de CO₂",
      "anillos": false,
      "lunas": 2,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#8f3418",
          "#c1440e",
          "#e27b58"
        ],
        "craters": 120,
        "escala": 3,
        "casquetes": "#f4f1ee",
        "halo": "#e8a070"
      },
      "gravedad_ms2": 3.7277515985747836,
      "densidad_kg_m3": 3934.0195631294914
    },
    {
      "id": "jupiter",
      "nombre": "Júpiter",
      "categoria": "planeta",
      "tipo": "Gigante gaseoso",
      "radio_km": 69911,
      "masa_kg": 1.898e+27,
      "periodo_rotacion_h": 9.925,
      "periodo_orbital_dias": 4332.6,
      "inclinacion_eje_grados": 3.13,
      "distancia_media": {
        "valor": 778.5,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 165,
      "atmosfera": "Hidrógeno y helio, con bandas de nubes",
      "anillos": true,
      "lunas": 95,
      "textura": {
        "tipo": "bandas",
        "colores": [
          "#b59f88",
          "#c4b19b",
          "#d6c7b0",
          "#e4d8c4",
          "#b09884"
        ],
        "bandas": 6,
        "contraste": 0.3,
        "mancha": {
          "lat": -22,
          "lon": 20,
          "rx": 0.07,
          "ry": 0.035,
          "color": "#c4a595"
        },
        "anillos": {
          "interior": 1.29,
          "exterior": 1.81,
          "opacidad": 0.04,
          "colores": [
            "#5a4a40",
            "#7a6a5e",
            "#4a3e36"
          ]
        },
        "deformacion": 0.2,
        "grano": 0
      },
      "gravedad_ms2": 25.917397035788568,
      "densidad_kg_m3": 1326.084063780249
    },
    {
      "id": "saturno",
      "nombre": "Saturno",
      "categoria": "planeta",
      "tipo": "Gigante gaseoso",
      "radio_km": 58232,
      "masa_kg": 5.683e+26,
      "periodo_rotacion_h": 10.656,
      "periodo_orbital_dias": 10759.2,
      "inclinacion_eje_grados": 26.73,
      "distancia_media": {
        "valor": 1434,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 134,
      "atmosfera": "Hidrógeno y helio, con bandas suaves",
      "anillos": true,
      "lunas": 146,
      "textura": {
        "tipo": "bandas",
        "colores": [
          "#b89a5e",
          "#c8a86a",
          "#e4d191",
          "#f0e3b8"
        ],
        "bandas": 7,
        "contraste": 0.45,
        "anillos": {
          "interior": 1.24,
          "exterior": 2.27,
          "opacidad": 1,
          "colores": [
            "#8f7c58",
            "#d6c08e",
            "#efe2bb",
            "#b9a374"
          ]
        }
      },
      "gravedad_ms2": 11.185114792829006,
      "densidad_kg_m3": 687.074653679189
    },
    {
      "id": "urano",
      "nombre": "Urano",
      "categoria": "planeta",
      "tipo": "Gigante de hielo",
      "radio_km": 25362,
      "masa_kg": 8.681e+25,
      "periodo_rotacion_h": -17.24,
      "periodo_orbital_dias": 30685,
      "inclinacion_eje_grados": 97.77,
      "distancia_media": {
        "valor": 2871,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 76,
      "atmosfera": "Hidrógeno, helio y metano (le da el tono cian)",
      "anillos": true,
      "lunas": 28,
      "textura": {
        "tipo": "bandas",
        "colores": [
          "#5fb3c4",
          "#7fc4d0",
          "#9fd8e0",
          "#b6e6ec"
        ],
        "bandas": 5,
        "contraste": 0.15,
        "anillos": {
          "interior": 1.6,
          "exterior": 2,
          "opacidad": 0.5,
          "colores": [
            "#3b4f57",
            "#6f8a92",
            "#3b4f57"
          ]
        },
        "halo": "#9fe3f0"
      },
      "gravedad_ms2": 9.007182495377197,
      "densidad_kg_m3": 1270.3712373717458
    },
    {
      "id": "neptuno",
      "nombre": "Neptuno",
      "categoria": "planeta",
      "tipo": "Gigante de hielo",
      "radio_km": 24622,
      "masa_kg": 1.024e+26,
      "periodo_rotacion_h": 16.11,
      "periodo_orbital_dias": 60190,
      "inclinacion_eje_grados": 28.32,
      "distancia_media": {
        "valor": 4495.1,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 72,
      "atmosfera": "Hidrógeno, helio y metano; vientos de los más rápidos",
      "anillos": true,
      "lunas": 16,
      "textura": {
        "tipo": "bandas",
        "colores": [
          "#1f2f8a",
          "#2b43a6",
          "#3f54ba",
          "#5b7bd0"
        ],
        "bandas": 6,
        "contraste": 0.5,
        "mancha": {
          "lat": -22,
          "lon": 120,
          "rx": 0.09,
          "ry": 0.04,
          "color": "#0f1f6a"
        },
        "halo": "#4f7cff",
        "anillos": {
          "interior": 1.7,
          "exterior": 2.55,
          "opacidad": 0.2,
          "colores": [
            "#2b3a6a",
            "#4a5f9a",
            "#222e55"
          ]
        }
      },
      "gravedad_ms2": 11.272999948317743,
      "densidad_kg_m3": 1637.726462393788
    },
    {
      "id": "pluton",
      "nombre": "Plutón",
      "categoria": "planeta",
      "tipo": "Planeta enano (hielo y roca)",
      "radio_km": 1188.3,
      "masa_kg": 1.303e+22,
      "periodo_rotacion_h": -153.3,
      "periodo_orbital_dias": 90560,
      "inclinacion_eje_grados": 122.53,
      "distancia_media": {
        "valor": 5906.4,
        "unidad": "millones de km",
        "referencia": "Sol"
      },
      "temperatura_media_k": 44,
      "atmosfera": "Tenue, de nitrógeno que se congela en parte de la órbita",
      "anillos": false,
      "lunas": 5,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#5e4a3e",
          "#8a6f5c",
          "#c9b49a",
          "#e8dccb"
        ],
        "craters": 90,
        "escala": 3,
        "mancha": {
          "lat": 18,
          "lon": 40,
          "rx": 0.16,
          "ry": 0.12,
          "color": "#efe6da"
        }
      },
      "gravedad_ms2": 0.6158549320204797,
      "densidad_kg_m3": 1853.862480479675
    },
    {
      "id": "luna",
      "nombre": "Luna",
      "categoria": "luna",
      "padre": "tierra",
      "tipo": "Luna rocosa",
      "radio_km": 1737.4,
      "masa_kg": 7.342e+22,
      "periodo_rotacion_h": 655.7,
      "periodo_orbital_dias": 27.32,
      "distancia_media": {
        "valor": 384400,
        "unidad": "km",
        "referencia": "Tierra"
      },
      "temperatura_media_k": 250,
      "atmosfera": "Prácticamente nula",
      "anillos": false,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#5a5a5a",
          "#8f8f8f",
          "#bdbdbd"
        ],
        "craters": 300,
        "escala": 3.5,
        "maria": true
      },
      "gravedad_ms2": 1.6233080716971033,
      "densidad_kg_m3": 3342.152936972654
    },
    {
      "id": "fobos",
      "nombre": "Fobos",
      "categoria": "luna",
      "padre": "marte",
      "tipo": "Luna irregular (asteroide capturado)",
      "radio_km": 11.3,
      "masa_kg": 10660000000000000,
      "periodo_rotacion_h": 7.66,
      "periodo_orbital_dias": 0.319,
      "distancia_media": {
        "valor": 9376,
        "unidad": "km",
        "referencia": "Marte"
      },
      "temperatura_media_k": 233,
      "atmosfera": "Nula",
      "anillos": false,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#4d4742",
          "#6b655f",
          "#8a837b"
        ],
        "craters": 80,
        "escala": 6
      },
      "gravedad_ms2": 0.005571684548515936,
      "densidad_kg_m3": 1763.734722602796
    },
    {
      "id": "deimos",
      "nombre": "Deimos",
      "categoria": "luna",
      "padre": "marte",
      "tipo": "Luna irregular (asteroide capturado)",
      "radio_km": 6.2,
      "masa_kg": 1476000000000000,
      "periodo_rotacion_h": 30.3,
      "periodo_orbital_dias": 1.263,
      "distancia_media": {
        "valor": 23463,
        "unidad": "km",
        "referencia": "Marte"
      },
      "temperatura_media_k": 233,
      "atmosfera": "Nula",
      "anillos": false,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#7a7268",
          "#948a7e"
        ],
        "craters": 60,
        "escala": 6
      },
      "gravedad_ms2": 0.0025626493236212276,
      "densidad_kg_m3": 1478.5045987272008
    },
    {
      "id": "io",
      "nombre": "Ío",
      "categoria": "luna",
      "padre": "jupiter",
      "tipo": "Luna volcánica",
      "radio_km": 1821.6,
      "masa_kg": 8.93e+22,
      "periodo_rotacion_h": 42.46,
      "periodo_orbital_dias": 1.769,
      "distancia_media": {
        "valor": 421700,
        "unidad": "km",
        "referencia": "Júpiter"
      },
      "temperatura_media_k": 130,
      "atmosfera": "Muy tenue, de dióxido de azufre",
      "anillos": false,
      "textura": {
        "tipo": "volcanico",
        "colores": [
          "#2b1d12",
          "#b33b1a",
          "#e07b20",
          "#f2d24a",
          "#fff3a3"
        ],
        "escala": 4,
        "volcanes": 40
      },
      "gravedad_ms2": 1.7961046035385841,
      "densidad_kg_m3": 3526.9864952316607
    },
    {
      "id": "europa",
      "nombre": "Europa",
      "categoria": "luna",
      "padre": "jupiter",
      "tipo": "Luna de hielo",
      "radio_km": 1560.8,
      "masa_kg": 4.8e+22,
      "periodo_rotacion_h": 85.2,
      "periodo_orbital_dias": 3.551,
      "distancia_media": {
        "valor": 671034,
        "unidad": "km",
        "referencia": "Júpiter"
      },
      "temperatura_media_k": 102,
      "atmosfera": "Muy tenue, de oxígeno",
      "anillos": false,
      "textura": {
        "tipo": "hielo",
        "colores": [
          "#8c5a3c",
          "#c9a57a",
          "#e9e1d2",
          "#ffffff"
        ],
        "escala": 5,
        "lineas": "#8c5a3c"
      },
      "gravedad_ms2": 1.3150217226193455,
      "densidad_kg_m3": 3013.77522854792
    },
    {
      "id": "ganimedes",
      "nombre": "Ganímedes",
      "categoria": "luna",
      "padre": "jupiter",
      "tipo": "Luna rocosa-helada (la mayor del Sistema Solar)",
      "radio_km": 2634.1,
      "masa_kg": 1.482e+23,
      "periodo_rotacion_h": 171.7,
      "periodo_orbital_dias": 7.155,
      "distancia_media": {
        "valor": 1070412,
        "unidad": "km",
        "referencia": "Júpiter"
      },
      "temperatura_media_k": 110,
      "atmosfera": "Muy tenue, de oxígeno",
      "anillos": false,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#6f6257",
          "#8e8a84",
          "#b5ada3",
          "#c9c2b6"
        ],
        "craters": 200,
        "escala": 3.5
      },
      "gravedad_ms2": 1.4255087561426127,
      "densidad_kg_m3": 1935.8104026498347
    },
    {
      "id": "calisto",
      "nombre": "Calisto",
      "categoria": "luna",
      "padre": "jupiter",
      "tipo": "Luna helada muy craterizada",
      "radio_km": 2410.3,
      "masa_kg": 1.076e+23,
      "periodo_rotacion_h": 400.5,
      "periodo_orbital_dias": 16.69,
      "distancia_media": {
        "valor": 1882709,
        "unidad": "km",
        "referencia": "Júpiter"
      },
      "temperatura_media_k": 134,
      "atmosfera": "Muy tenue, de dióxido de carbono",
      "anillos": false,
      "textura": {
        "tipo": "rocoso",
        "colores": [
          "#3b332d",
          "#5a5048",
          "#7d7064",
          "#9a8d80"
        ],
        "craters": 350,
        "escala": 3
      },
      "gravedad_ms2": 1.236107587193615,
      "densidad_kg_m3": 1834.4689263348494
    },
    {
      "id": "titan",
      "nombre": "Titán",
      "categoria": "luna",
      "padre": "saturno",
      "tipo": "Luna con atmósfera densa",
      "radio_km": 2574.7,
      "masa_kg": 1.345e+23,
      "periodo_rotacion_h": 382.7,
      "periodo_orbital_dias": 15.945,
      "distancia_media": {
        "valor": 1221870,
        "unidad": "km",
        "referencia": "Saturno"
      },
      "temperatura_media_k": 94,
      "atmosfera": "Densa, de nitrógeno con neblina de metano",
      "anillos": false,
      "textura": {
        "tipo": "nubes",
        "colores": [
          "#7a4a22",
          "#b5692c",
          "#d18a3c",
          "#e8b06a"
        ],
        "bandas": 3,
        "halo": "#f0a050"
      },
      "gravedad_ms2": 1.354113976317942,
      "densidad_kg_m3": 1881.2814619180867
    },
    {
      "id": "encelado",
      "nombre": "Encélado",
      "categoria": "luna",
      "padre": "saturno",
      "tipo": "Luna de hielo muy reflectante",
      "radio_km": 252.1,
      "masa_kg": 108000000000000000000,
      "periodo_rotacion_h": 32.9,
      "periodo_orbital_dias": 1.37,
      "distancia_media": {
        "valor": 237948,
        "unidad": "km",
        "referencia": "Saturno"
      },
      "temperatura_media_k": 75,
      "atmosfera": "Muy tenue, de vapor de agua en los géiseres del polo sur",
      "anillos": false,
      "textura": {
        "tipo": "hielo",
        "colores": [
          "#cfe0ee",
          "#e4eef8",
          "#f8fbff"
        ],
        "escala": 4,
        "lineas": "#9fb6c8"
      },
      "gravedad_ms2": 0.11341337288789242,
      "densidad_kg_m3": 1609.2244006651376
    },
    {
      "id": "triton",
      "nombre": "Tritón",
      "categoria": "luna",
      "padre": "neptuno",
      "tipo": "Luna helada (órbita retrógrada)",
      "radio_km": 1353.4,
      "masa_kg": 2.139e+22,
      "periodo_rotacion_h": -141,
      "periodo_orbital_dias": 5.877,
      "distancia_media": {
        "valor": 354759,
        "unidad": "km",
        "referencia": "Neptuno"
      },
      "temperatura_media_k": 38,
      "atmosfera": "Tenue, de nitrógeno",
      "anillos": false,
      "textura": {
        "tipo": "hielo",
        "colores": [
          "#b07f78",
          "#d8b9b0",
          "#efe2dc",
          "#f5f0ec"
        ],
        "escala": 4,
        "craters": 40
      },
      "gravedad_ms2": 0.7793717191119229,
      "densidad_kg_m3": 2059.888242971553
    },
    {
      "id": "sol",
      "nombre": "Sol",
      "categoria": "sol",
      "tipo": "Estrella de secuencia principal (G2V)",
      "radio_solar": 1,
      "masa_solar": 1,
      "temperatura_superficial_k": 5772,
      "tipo_espectral": "G2V",
      "distancia": {
        "valor": 1,
        "unidad": "UA",
        "referencia": "Tierra"
      },
      "edad_aprox_mil_millones_anios": 4.6,
      "incertidumbre_radio": "baja",
      "textura": {
        "tipo": "estrella",
        "granulos": 10,
        "escala": 5
      },
      "radio_km": 695700,
      "masa_kg": 1.989e+30,
      "gravedad_ms2": null,
      "densidad_kg_m3": null
    },
    {
      "id": "sirio",
      "nombre": "Sirio A",
      "categoria": "sol",
      "tipo": "Estrella blanca de secuencia principal",
      "radio_solar": 1.711,
      "masa_solar": 2.02,
      "temperatura_superficial_k": 9940,
      "tipo_espectral": "A1V",
      "distancia": {
        "valor": 8.6,
        "unidad": "años luz",
        "referencia": "Sol"
      },
      "edad_aprox_mil_millones_anios": 0.24,
      "incertidumbre_radio": "baja",
      "textura": {
        "tipo": "estrella",
        "granulos": 6,
        "escala": 4
      },
      "radio_km": 1190343,
      "masa_kg": 4.01778e+30,
      "gravedad_ms2": null,
      "densidad_kg_m3": null
    },
    {
      "id": "vega",
      "nombre": "Vega",
      "categoria": "sol",
      "tipo": "Estrella blanca de secuencia principal",
      "radio_solar": 2.36,
      "masa_solar": 2.14,
      "temperatura_superficial_k": 9602,
      "tipo_espectral": "A0V",
      "distancia": {
        "valor": 25,
        "unidad": "años luz",
        "referencia": "Sol"
      },
      "edad_aprox_mil_millones_anios": 0.455,
      "incertidumbre_radio": "media",
      "textura": {
        "tipo": "estrella",
        "granulos": 6,
        "escala": 4
      },
      "radio_km": 1641852,
      "masa_kg": 4.25646e+30,
      "gravedad_ms2": null,
      "densidad_kg_m3": null
    },
    {
      "id": "polaris",
      "nombre": "Polaris",
      "categoria": "sol",
      "tipo": "Supergigante amarilla (cefeida)",
      "radio_solar": 37.5,
      "masa_solar": 5.4,
      "temperatura_superficial_k": 6015,
      "tipo_espectral": "F7Ib",
      "distancia": {
        "valor": 430,
        "unidad": "años luz",
        "referencia": "Sol"
      },
      "edad_aprox_mil_millones_anios": 0.07,
      "incertidumbre_radio": "alta",
      "textura": {
        "tipo": "estrella",
        "granulos": 8,
        "escala": 4
      },
      "radio_km": 26088750,
      "masa_kg": 1.07406e+31,
      "gravedad_ms2": null,
      "densidad_kg_m3": null
    },
    {
      "id": "betelgeuse",
      "nombre": "Betelgeuse",
      "categoria": "sol",
      "tipo": "Supergigante roja variable",
      "radio_solar": 764,
      "masa_solar": 16.5,
      "temperatura_superficial_k": 3590,
      "tipo_espectral": "M1-2 Ia-Iab",
      "distancia": {
        "valor": 548,
        "unidad": "años luz",
        "referencia": "Sol"
      },
      "edad_aprox_mil_millones_anios": 0.009,
      "incertidumbre_radio": "alta",
      "textura": {
        "tipo": "estrella",
        "granulos": 3,
        "escala": 2.5
      },
      "radio_km": 531514800,
      "masa_kg": 3.28185e+31,
      "gravedad_ms2": null,
      "densidad_kg_m3": null
    },
    {
      "id": "antares",
      "nombre": "Antares",
      "categoria": "sol",
      "tipo": "Supergigante roja",
      "radio_solar": 680,
      "masa_solar": 12,
      "temperatura_superficial_k": 3570,
      "tipo_espectral": "M1.5 Iab-Ib",
      "distancia": {
        "valor": 550,
        "unidad": "años luz",
        "referencia": "Sol"
      },
      "edad_aprox_mil_millones_anios": 0.012,
      "incertidumbre_radio": "alta",
      "textura": {
        "tipo": "estrella",
        "granulos": 3,
        "escala": 2.5
      },
      "radio_km": 473076000,
      "masa_kg": 2.3868e+31,
      "gravedad_ms2": null,
      "densidad_kg_m3": null
    }
  ]
};
