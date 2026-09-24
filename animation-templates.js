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
const toggle = new AnimationToggle('.${className}');`
};
