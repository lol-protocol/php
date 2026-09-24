// Generador de plantillas HTML comunes para animaciones

const AnimationTemplate = {
    // Template HTML base
    htmlBase: (id, title, description, contentHTML) => `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animación ${id} - ${title}</title>
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="animacion-${id}.css">
</head>
<body class="fullscreen-container">
    <div class="info-panel">
        <h2>${id}) ${title}</h2>
        <p>${description}</p>
    </div>

    ${contentHTML}

    <button class="control-btn" onclick="toggle.toggle()">Pausar/Reanudar</button>
    <script src="common.js"></script>
    <script src="animacion-${id}.js"></script>
</body>
</html>`,

    // Patrón CSS base para rotaciones
    cssRotate: (selector, duration = '4s') => `${selector} {
    animation: rotate ${duration} linear infinite;
}`,

    // Patrón CSS base para pulsaciones
    cssPulse: (selector, duration = '2s') => `${selector} {
    animation: pulse ${duration} ease-in-out infinite;
}`,

    // Patrón CSS para escala pulsante
    cssScalePulse: (selector, duration = '2s') => `${selector} {
    animation: scalePulse ${duration} ease-in-out infinite;
}`,

    // Patrón CSS para brillo
    cssGlow: (selector, duration = '2s') => `${selector} {
    animation: glow ${duration} ease-in-out infinite;
}`,

    // Patrón CSS para movimiento de caída
    cssFall: (keyframeName, distance = '400px', duration = '3s') => `@keyframes ${keyframeName} {
    0% {
        opacity: 1;
        transform: translateY(0);
    }
    100% {
        opacity: 0;
        transform: translateY(${distance});
    }
}`,

    // Patrón CSS para transición suave
    cssFadeTransition: (selector) => `${selector} {
    transition: all 0.3s ease;
    opacity: 1;
}`,

    // Patrón JS simple para toggle
    jsToggleSimple: (selector) => `const toggle = new AnimationToggle('${selector}');`,

    // Patrón JS para crear elementos dinámicos
    jsCreateElements: (count, className, parentId) => `
const container = document.getElementById('${parentId}');
for (let i = 0; i < ${count}; i++) {
    const el = createDiv('${className}');
    container.appendChild(el);
}
const toggle = new AnimationToggle('.${className}');`,

    // Patrón JS para crear elementos en patrón radial
    jsCreateRadialPattern: (count, className, parentId, radius) => `
const container = document.getElementById('${parentId}');
const points = Patterns.createRadial(${count}, ${radius});
points.forEach((p, i) => {
    const el = createDiv('${className}');
    el.style.left = (${radius} + p.x) + 'px';
    el.style.top = (${radius} + p.y) + 'px';
    el.style.animationDelay = generateCascadeDelay(i, ${count});
    container.appendChild(el);
});
const toggle = new AnimationToggle('.${className}');`,

    // Patrón JS para física con múltiples objetos
    jsPhysicsObjects: (objectCount, objectRadius, containerId) => `
class Ball extends PhysicsObject {
    constructor(element, bounds, radius) {
        super(element, {
            x: Math.random() * (bounds.width - radius * 2) + radius,
            y: Math.random() * (bounds.height - radius * 2) + radius,
            vx: (Math.random() - 0.5) * 4,
            vy: (Math.random() - 0.5) * 4,
            radius: radius,
            bounceCoeff: 1,
            bounds: bounds
        });
        this.render();
    }
}

const container = document.getElementById('${containerId}');
const bounds = { width: container.offsetWidth, height: container.offsetHeight };
const objects = [];
for (let i = 0; i < ${objectCount}; i++) {
    const el = createDiv('ball');
    container.appendChild(el);
    objects.push(new Ball(el, bounds, ${objectRadius}));
}

const manager = new AnimationManager(objects);
const toggle = new AnimationToggle([], true);
const origToggle = toggle.toggle.bind(toggle);
toggle.toggle = function() {
    origToggle();
    this.isAnimating ? manager.resume() : manager.pause();
};
manager.start();`,

    // Patrón JS para red radial SVG
    jsSVGRadialWeb: (layers, pointsPerLayer, centerX, centerY, svgId) => `
const svg = document.getElementById('${svgId}');
SVGPatterns.createRadialWeb(svg, ${centerX}, ${centerY}, ${layers}, ${pointsPerLayer});
const toggle = new AnimationToggle('.web-svg');`,

    // Patrón JS para secuencia repetida
    jsSequenceAnimation: (buildFunctionName, delayMs = 4000) => `
class SequenceToggle extends AnimationToggle {
    constructor(callback, delay = ${delayMs}) {
        super([], true);
        this.callback = callback;
        this.delay = delay;
        this.timeoutId = null;
    }

    toggle() {
        super.toggle();
        if (this.isAnimating) {
            this.scheduleNext();
        } else {
            clearTimeout(this.timeoutId);
        }
    }

    scheduleNext() {
        this.callback();
        if (this.isAnimating) {
            this.timeoutId = setTimeout(() => this.scheduleNext(), this.delay);
        }
    }
}

const toggle = new SequenceToggle(() => ${buildFunctionName}(), ${delayMs});
toggle.scheduleNext();`
};
