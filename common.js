// Utilidades Compartidas

class AnimationToggle {
    constructor(selector, animationState = true) {
        this.elements = document.querySelectorAll(selector);
        this.isAnimating = animationState;
        this.button = document.querySelector('button');
    }

    toggle() {
        if (this.isAnimating) {
            this.pause();
        } else {
            this.resume();
        }
    }

    pause() {
        this.elements.forEach(el => {
            el.style.animationPlayState = 'paused';
        });
        this.isAnimating = false;
        if (this.button) this.button.textContent = 'Reanudar';
    }

    resume() {
        this.elements.forEach(el => {
            el.style.animationPlayState = 'running';
        });
        this.isAnimating = true;
        if (this.button) this.button.textContent = 'Pausar/Reanudar';
    }
}

// Utilidades para manejo de colores
const Colors = {
    RED: { main: '#ff6b6b', accent: '#ee5a6f' },
    TEAL: { main: '#4ecdc4', accent: '#44a0a0' },
    YELLOW: { main: '#ffd93d', dark: '#f8b500' },
    GREEN: { main: '#6bcf7f', dark: '#00aa88' },
    PURPLE: { main: '#c44569', dark: '#667eea' }
};

// Animación rápida para crear múltiples elementos
function createAnimatedElements(count, className, parentSelector, position = 'absolute') {
    const container = document.querySelector(parentSelector);
    const elements = [];
    for (let i = 0; i < count; i++) {
        const el = createDiv(className);
        if (position) el.style.position = position;
        container.appendChild(el);
        elements.push(el);
    }
    return elements;
}

// Generador de delays para cascadas
function generateCascadeDelay(index, maxIndex, baseDelay = 0.1) {
    return (index * baseDelay) + 's';
}

// Crear elementos simples
function createDiv(className, innerHTML = '') {
    const div = document.createElement('div');
    if (className) div.className = className;
    if (innerHTML) div.innerHTML = innerHTML;
    return div;
}

function createSVGElement(tag, attributes = {}) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.entries(attributes).forEach(([key, value]) => {
        element.setAttribute(key, value);
    });
    return element;
}

// Gestión de eventos
function onButtonClick(callback) {
    const button = document.querySelector('button');
    if (button) {
        button.addEventListener('click', callback);
    }
}

// Utilidades de ventana
const ViewportSize = {
    getWidth: () => window.innerWidth,
    getHeight: () => window.innerHeight,
    getCenter: () => ({
        x: window.innerWidth / 2,
        y: window.innerHeight / 2
    })
};

// Reutilizable para animaciones basadas en física
class Physics {
    static gravity = 0.1;
    static bounce = 0.8;

    static applyGravity(velocity) {
        return velocity + this.gravity;
    }

    static applyBounce(velocity) {
        return -velocity * this.bounce;
    }
}

// Helpers para posicionamiento y transformaciones
const Transform = {
    rotate: (angle) => `rotate(${angle}deg)`,
    scale: (factor) => `scale(${factor})`,
    translate: (x, y) => `translate(${x}px, ${y}px)`,
    rotateX: (angle) => `rotateX(${angle}deg)`,
    rotateY: (angle) => `rotateY(${angle}deg)`,
    rotateZ: (angle) => `rotateZ(${angle}deg)`,
    skew: (x, y) => `skew(${x}deg, ${y}deg)`,
    matrix3d: (...values) => `matrix3d(${values.join(',')})`,
    perspective: (value) => `perspective(${value}px)`
};

// Helpers para crear efectos comunes
const Effects = {
    randomPosition: (maxX, maxY) => ({
        x: Math.random() * maxX,
        y: Math.random() * maxY
    }),
    randomDelay: (min = 0, max = 1) => Math.random() * (max - min) + min,
    randomDuration: (min = 1, max = 3) => Math.random() * (max - min) + min,
    polarToCartesian: (radius, angle) => ({
        x: Math.cos(angle) * radius,
        y: Math.sin(angle) * radius
    })
};

// Helpers para crear grillas y patrones
const Patterns = {
    createGrid: (rows, cols, width, height) => {
        const cells = [];
        const cellWidth = width / cols;
        const cellHeight = height / rows;
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                cells.push({
                    x: c * cellWidth,
                    y: r * cellHeight,
                    width: cellWidth,
                    height: cellHeight
                });
            }
        }
        return cells;
    },

    createRadial: (count, radius) => {
        const points = [];
        for (let i = 0; i < count; i++) {
            const angle = (i / count) * Math.PI * 2;
            points.push({
                angle,
                x: Math.cos(angle) * radius,
                y: Math.sin(angle) * radius
            });
        }
        return points;
    }
};