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
        difficulty: 'Fácil',
        techniques: ['Oscilación', 'Física', 'Rotación'],
        description: 'Péndulo oscila con movimiento natural suave y regresión',
        color: 'from-slate-400 to-slate-600'
      },
      {
        id: 'j',
        title: 'Bola Rodando por Laberinto',
        category: 'Movimiento',
        difficulty: 'Medio',
        techniques: ['SVG', 'Trayectoria', 'Keyframes CSS'],
        description: 'Una bola rueda a través de un laberinto simple.',
        color: 'from-sky-400 to-blue-500'
      },
      {
        id: 'k',
        title: 'Barras Ecualizador',
        category: 'Patrones',
        difficulty: 'Fácil',
        techniques: ['Barras', 'Cascade', 'Timing'],
        description: 'Barras que suben y bajan en patrón de cascada como un ecualizador de música',
        color: 'from-rose-400 to-red-500'
      },
      {
        id: 'l',
        title: 'Círculos Concéntricos',
        category: 'Patrones',
        difficulty: 'Fácil',
        techniques: ['Círculos', 'Expansión', 'Scaling'],
        description: 'Anillos concéntricos que se expanden y contraen desde el centro',
        color: 'from-violet-400 to-purple-500'
      },
      {
        id: 'm',
        title: 'Grid de Puntos Deformándose',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Cuadrícula de puntos', 'Ondulación', 'Delay secuencial'],
        description: 'Una cuadrícula de puntos que se deforma ondulando.',
        color: 'from-fuchsia-400 to-pink-500'
      },
      {
        id: 'n',
        title: 'Espiral Hipnótica Rotando',
        category: 'Patrones',
        difficulty: 'Fácil',
        techniques: ['Anillos', 'Rotación', 'Keyframes CSS'],
        description: 'Una espiral de anillos que rota hipnóticamente.',
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
        title: 'Tela Ondeando al Viento',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['SVG', 'Filtros', 'Ondulación'],
        description: 'Una tela se ondula como si estuviera en el viento.',
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
        title: 'Efecto Matriz (Código Cayendo)',
        category: 'Efectos',
        difficulty: 'Fácil',
        techniques: ['Caracteres', 'Cascada', 'Sombra de texto'],
        description: 'Caracteres digitales caen como en la película Matrix.',
        color: 'from-green-500 to-emerald-600'
      },
      {
        id: 's',
        title: 'Dominó Cayendo en Secuencia',
        category: '3D',
        difficulty: 'Medio',
        techniques: ['CSS 3D', 'Perspectiva', 'Delay secuencial'],
        description: 'Fichas de dominó caen en cascada ordenadamente.',
        color: 'from-blue-300 to-cyan-400'
      },
      {
        id: 't',
        title: 'Engranajes Rotando',
        category: 'Mecánico',
        difficulty: 'Medio',
        techniques: ['SVG', 'Rotación', 'Sincronía'],
        description: 'Engranajes interconectados rotando en conjunto.',
        color: 'from-amber-500 to-orange-600'
      },
      {
        id: 'u',
        title: 'Reloj Analógico Animado',
        category: 'Mecánico',
        difficulty: 'Fácil',
        techniques: ['Rotación', 'Manecillas', 'Keyframes CSS'],
        description: 'Manecillas de reloj que giran en un ciclo continuo.',
        color: 'from-slate-900 to-slate-700'
      },
      {
        id: 'v',
        title: 'Brújula Girando',
        category: 'Mecánico',
        difficulty: 'Fácil',
        techniques: ['Rotación', 'Gradientes', 'Sombra de texto'],
        description: 'Una brújula que gira y apunta a direcciones cardinales.',
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
        title: 'Onda Gravitacional',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Ondas', 'Expansión', 'Emisor de partículas'],
        description: 'Ondas que se propagan desde el centro como ripples en un estanque.',
        color: 'from-red-600 to-yellow-500'
      },
      {
        id: 'x',
        title: 'Rayos Fractal',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['SVG', 'Ramificación', 'Crecimiento'],
        description: 'Ramificaciones fractales que crecen dinámicamente desde el centro.',
        color: 'from-lime-500 to-green-600'
      },
      {
        id: 'y',
        title: 'Patrón Hexagonal',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['clip-path', 'Pulso', 'Elementos generados con JS'],
        description: 'Estructura hexagonal que se expande y contrae como la planta yareta.',
        color: 'from-purple-700 to-pink-600'
      },
      {
        id: 'z',
        title: 'Zoom Infinito',
        category: 'Efectos',
        difficulty: 'Fácil',
        techniques: ['Capas anidadas', 'Escala', 'Keyframes CSS'],
        description: 'Efecto de zoom infinito con capas anidadas.',
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
        title: 'Prisma de Luz',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['clip-path', 'Refracción', 'Gradientes'],
        description: 'Rayos de luz refractándose a través de un prisma.',
        color: 'from-indigo-600 to-purple-700'
      },
      {
        id: 'ab',
        title: 'Engranajes Giratorios',
        category: 'Mecánico',
        difficulty: 'Fácil',
        techniques: ['Rotación', 'Sincronía', 'Keyframes CSS'],
        description: 'Ruedas dentadas que rotan en perfecta sincronía.',
        color: 'from-rose-500 to-pink-600'
      },
      {
        id: 'ac',
        title: 'Nebulosa Estelar',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Partículas', 'Movimiento aleatorio', 'Gradientes radiales'],
        description: 'Polvo estelar moviéndose en el espacio.',
        color: 'from-orange-600 to-red-700'
      },
      {
        id: 'ad',
        title: 'Espejos Recursivos',
        category: '3D',
        difficulty: 'Medio',
        techniques: ['Perspectiva', 'Rotación', 'Gradientes'],
        description: 'Espejos reflejándose infinitamente entre sí.',
        color: 'from-teal-500 to-cyan-600'
      },
      {
        id: 'ae',
        title: 'Nodo de Red',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['SVG', 'Nodos y conexiones', 'Pulso'],
        description: 'Nodos conectados pulsando al transferir datos.',
        color: 'from-blue-600 to-cyan-700'
      },
      {
        id: 'af',
        title: 'Mandala Geométrico',
        category: 'Patrones',
        difficulty: 'Medio',
        techniques: ['Anillos generados con JS', 'Simetría', 'Rotación'],
        description: 'Patrón simétrico que rota hipnóticamente.',
        color: 'from-sky-400 to-blue-600'
      },
      {
        id: 'ag',
        title: 'ADN Helicoidal',
        category: '3D',
        difficulty: 'Medio',
        techniques: ['Perspectiva', 'Rotación', 'Doble hélice'],
        description: 'Doble hélice de ADN girando lentamente.',
        color: 'from-red-500 to-orange-600'
      },
      {
        id: 'ah',
        title: 'Lluvia de Estrellas',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['Emisor de partículas', 'Movimiento aleatorio', 'Parpadeo'],
        description: 'Estrellas cayendo con brillo intermitente.',
        color: 'from-yellow-500 to-orange-600'
      },
      {
        id: 'ai',
        title: 'Telaraña Oscilante',
        category: 'Efectos',
        difficulty: 'Medio',
        techniques: ['SVG', 'Red radial', 'Oscilación'],
        description: 'Red de araña oscilando suavemente.',
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
