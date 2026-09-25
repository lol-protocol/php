// Datos de todas las 36 animaciones
const animationsData = {
  initial: {
    title: "🎬 Iniciales (A-V)",
    description: "Primera fase de implementación - 22 animaciones fundamentales",
    color: "from-blue-500 to-purple-600",
    animations: [
      {
        id: 'a',
        title: 'Vuelo 3D',
        category: '3D',
        difficulty: 'Medio',
        techniques: ['CSS 3D', 'Transformaciones', 'Perspectiva'],
        description: 'Rectángulo centrado que rota en 3D y vuela a la esquina superior izquierda de la pantalla',
        color: 'from-blue-400 to-blue-600'
      },
      {
        id: 'b',
        title: 'Círculos Rebotadores',
        category: 'Física',
        difficulty: 'Medio',
        techniques: ['Física', 'Gravedad', 'Colisiones'],
        description: 'Dos círculos con degradados 3D rebotan dentro de un rectángulo dorado con proporción áurea',
        color: 'from-yellow-400 to-orange-500'
      },
      {
        id: 'c',
        title: 'Página Volteándose',
        category: '3D',
        difficulty: 'Alto',
        techniques: ['CSS 3D', 'Rotación', 'Perspectiva'],
        description: 'Un libro/página se voltea en 3D mostrando ambos lados con efecto de profundidad',
        color: 'from-red-400 to-pink-500'
      },
      {
        id: 'd',
        title: 'Cubo 3D',
        category: '3D',
        difficulty: 'Medio',
        techniques: ['CSS 3D', 'Rotación', 'Caras múltiples'],
        description: 'Cubo rotando continuamente mostrando sus 6 caras con colores distintos',
        color: 'from-purple-400 to-pink-500'
      },
      {
        id: 'e',
        title: 'Pirámide Construyéndose',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Cascada', 'Bloques', 'Delay secuencial'],
        description: 'Pirámide construyéndose capa por capa con bloques que caen ordenadamente',
        color: 'from-green-400 to-teal-500'
      },
      {
        id: 'f',
        title: 'Polígono Morphing',
        category: 'Patrones',
        difficulty: 'Alto',
        techniques: ['SVG', 'Morphing', 'Transformaciones'],
        description: 'Forma que se transforma fluidamente entre círculo, cuadrado, triángulo y hexágono',
        color: 'from-indigo-400 to-blue-500'
      },
      {
        id: 'g',
        title: 'Lluvia Partículas',
        category: 'Física',
        difficulty: 'Medio',
        techniques: ['Partículas', 'Gravedad', 'Colisiones'],
        description: 'Partículas caen con gravedad realista y rebotan en las paredes del contenedor',
        color: 'from-cyan-400 to-blue-500'
      },
      {
        id: 'h',
        title: 'Hoja Cayendo',
        category: 'Física',
        difficulty: 'Medio',
        techniques: ['Movimiento ondulante', 'Rotación', 'Física'],
        description: 'Hoja cae con movimiento ondulante realista, girando mientras cae',
        color: 'from-amber-400 to-orange-500'
      },
      {
        id: 'i',
        title: 'Péndulo',
        category: 'Mecánico',
        difficulty: 'Bajo',
        techniques: ['Oscilación', 'Física', 'Rotación'],
        description: 'Péndulo oscila con movimiento natural suave y regresión',
        color: 'from-slate-400 to-slate-600'
      },
      {
        id: 'j',
        title: 'Onda Sinusoidal',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Matemáticas', 'SVG', 'Animación'],
        description: 'Visualización de onda sinusoidal que se propaga continuamente',
        color: 'from-sky-400 to-blue-500'
      },
      {
        id: 'k',
        title: 'Barras Ecualizador',
        category: 'Patrones',
        difficulty: 'Bajo',
        techniques: ['Barras', 'Cascade', 'Timing'],
        description: 'Barras que suben y bajan en patrón de cascada como un ecualizador de música',
        color: 'from-rose-400 to-red-500'
      },
      {
        id: 'l',
        title: 'Círculos Concéntricos',
        category: 'Patrones',
        difficulty: 'Bajo',
        techniques: ['Círculos', 'Expansión', 'Scaling'],
        description: 'Anillos concéntricos que se expanden y contraen desde el centro',
        color: 'from-violet-400 to-purple-500'
      },
      {
        id: 'm',
        title: 'Burbujas',
        category: 'Física',
        difficulty: 'Medio',
        techniques: ['Flotación', 'Movimiento aleatorio', 'Opacidad'],
        description: 'Burbujas flotan hacia arriba con movimiento suave y desaparecen',
        color: 'from-fuchsia-400 to-pink-500'
      },
      {
        id: 'n',
        title: 'Fractales',
        category: 'Patrones',
        difficulty: 'Alto',
        techniques: ['Fractales', 'Recursión', 'Matemáticas'],
        description: 'Árbol fractal que crece y se expande con patrón recursivo',
        color: 'from-lime-400 to-green-500'
      },
      {
        id: 'o',
        title: 'Tinta Derramándose',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Filtros', 'Opacidad', 'Expansión'],
        description: 'Mancha de tinta se disuelve y expande como si se derramara en agua',
        color: 'from-slate-500 to-slate-700'
      },
      {
        id: 'p',
        title: 'Galaxia',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Órbitas', 'Rotación', 'Partículas'],
        description: 'Galaxia espiral con planetas orbitando y estrellas parpadeantes',
        color: 'from-indigo-600 to-purple-800'
      },
      {
        id: 'q',
        title: 'Aurora Boreal',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Luces', 'Ondas', 'Gradientes'],
        description: 'Luces danzantes de colores simulando una aurora boreal',
        color: 'from-teal-400 to-cyan-300'
      },
      {
        id: 'r',
        title: 'Cascada Rectángulos',
        category: 'Patrones',
        difficulty: 'Bajo',
        techniques: ['Cascada', 'Delay', 'Transformaciones'],
        description: 'Rectángulos caen en cascada ordenada con timing preciso',
        color: 'from-orange-400 to-red-500'
      },
      {
        id: 's',
        title: 'Ondas Ripple',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Ondas', 'Expansión', 'Opacidad'],
        description: 'Ondas concéntricas que se expanden desde un punto central',
        color: 'from-blue-300 to-cyan-400'
      },
      {
        id: 't',
        title: 'Torbellino',
        category: 'Movimiento',
        difficulty: 'Medio',
        techniques: ['Rotación', 'Escala', 'Opacidad'],
        description: 'Elementos girando en espiral que se acelera hacia el centro',
        color: 'from-amber-500 to-orange-600'
      },
      {
        id: 'u',
        title: 'Universo',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Órbitas', 'Matemáticas', 'Movimiento'],
        description: 'Simulación de sistema orbital con múltiples cuerpos celestes',
        color: 'from-slate-900 to-slate-700'
      },
      {
        id: 'v',
        title: 'Vortex',
        category: 'Movimiento',
        difficulty: 'Medio',
        techniques: ['Rotación', 'Perspectiva', 'Escala'],
        description: 'Vórtice de elementos que giran acelerándose hacia el centro',
        color: 'from-purple-600 to-pink-600'
      }
    ]
  },
  extended: {
    title: "🚀 Extended (W-Z)",
    description: "Extensiones y variaciones - 4 animaciones avanzadas",
    color: "from-emerald-500 to-teal-600",
    animations: [
      {
        id: 'w',
        title: 'Lava Flow',
        category: 'Efectos',
        difficulty: 'Alto',
        techniques: ['Fluidos', 'Gradientes', 'Ondulación'],
        description: 'Simulación de lava fluyendo con movimiento viscoso y colores ardientes',
        color: 'from-red-600 to-yellow-500'
      },
      {
        id: 'x',
        title: 'Matrix Rain',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Caracteres', 'Cascada', 'Opacidad'],
        description: 'Caracteres digitales cayendo como en la película Matrix',
        color: 'from-green-500 to-emerald-600'
      },
      {
        id: 'y',
        title: 'Nebula',
        category: 'Patrones',
        difficulty: 'Alto',
        techniques: ['Partículas', 'Gradientes', 'Movimiento'],
        description: 'Nebulosa con partículas que flotan y colisiona creando efecto cósmico',
        color: 'from-purple-700 to-pink-600'
      },
      {
        id: 'z',
        title: 'Zen Garden',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Recursión', 'Patrones', 'SVG'],
        description: 'Jardín zen con patrón recursivo de arena y piedras',
        color: 'from-amber-600 to-orange-700'
      }
    ]
  },
  complex: {
    title: "⚡ Complex (AA-AJ)",
    description: "Animaciones complejas y avanzadas - 10 animaciones sofisticadas",
    color: "from-fuchsia-500 to-rose-600",
    animations: [
      {
        id: 'aa',
        title: 'Möbius Strip',
        category: '3D',
        difficulty: 'Alto',
        techniques: ['CSS 3D', 'Topología', 'Rotación'],
        description: 'Banda de Möbius rotando en 3D mostrando su naturaleza unilateral',
        color: 'from-indigo-600 to-purple-700'
      },
      {
        id: 'ab',
        title: 'Kaleidoscope',
        category: 'Patrones',
        difficulty: 'Alto',
        techniques: ['Simetría', 'Rotación', 'Espejos'],
        description: 'Caleidoscopio con patrones simétricos que rotan y cambian colores',
        color: 'from-rose-500 to-pink-600'
      },
      {
        id: 'ac',
        title: 'Mandelbrot Set',
        category: 'Patrones',
        difficulty: 'Muy Alto',
        techniques: ['Fractales', 'Zoom', 'Matemáticas complejas'],
        description: 'Visualización del conjunto de Mandelbrot con zoom interactivo',
        color: 'from-orange-600 to-red-700'
      },
      {
        id: 'ad',
        title: 'Swarm Intelligence',
        category: 'Movimiento',
        difficulty: 'Alto',
        techniques: ['IA', 'Flocking', 'Comportamiento colectivo'],
        description: 'Partículas que se comportan como un enjambre inteligente',
        color: 'from-teal-500 to-cyan-600'
      },
      {
        id: 'ae',
        title: 'Network Graph',
        category: 'Patrones',
        difficulty: 'Alto',
        techniques: ['Grafos', 'Física de fuerzas', 'Líneas dinámicas'],
        description: 'Grafo de red dirigido por fuerzas conectando nodos dinámicamente',
        color: 'from-blue-600 to-cyan-700'
      },
      {
        id: 'af',
        title: 'Crystal Growth',
        category: 'Patrones',
        difficulty: 'Alto',
        techniques: ['Crecimiento recursivo', 'Geometría', 'Transformaciones'],
        description: 'Cristal que crece y se expande con estructura geométrica recursiva',
        color: 'from-sky-400 to-blue-600'
      },
      {
        id: 'ag',
        title: 'Magnetic Field',
        category: 'Efectos',
        difficulty: 'Alto',
        techniques: ['Campo vectorial', 'Partículas', 'Física'],
        description: 'Visualización de campo magnético con líneas de fuerza',
        color: 'from-red-500 to-orange-600'
      },
      {
        id: 'ah',
        title: 'Traffic Flow',
        category: 'Movimiento',
        difficulty: 'Medio',
        techniques: ['Autómata celular', 'Tráfico', 'Lógica'],
        description: 'Simulación de flujo de tráfico con comportamiento emergente',
        color: 'from-yellow-500 to-orange-600'
      },
      {
        id: 'ai',
        title: 'Clock Tower',
        category: 'Mecánico',
        difficulty: 'Medio',
        techniques: ['Engranajes', 'Tiempo real', 'Rotación'],
        description: 'Torre de reloj con engranajes y manecillas en sincronía perfecta',
        color: 'from-slate-600 to-slate-800'
      },
      {
        id: 'aj',
        title: 'Arena Cayendo',
        category: 'Física',
        difficulty: 'Medio',
        techniques: ['Partículas', 'Gravedad', 'Física de arena'],
        description: 'Arena fluyendo y cayendo con comportamiento realista de particulado',
        color: 'from-yellow-600 to-amber-700'
      }
    ]
  }
};

// Helper para obtener todas las animaciones
function getAllAnimations() {
  return [
    ...animationsData.initial.animations,
    ...animationsData.extended.animations,
    ...animationsData.complex.animations
  ];
}

// Helper para obtener animación por ID
function getAnimationById(id) {
  return getAllAnimations().find(anim => anim.id === id);
}
