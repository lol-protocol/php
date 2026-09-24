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

// Sequence-based toggle for repeated callbacks
class SequenceToggle extends AnimationToggle {
    constructor(callback, delay = 4000) {
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
            this._clearTimeout();
        }
    }

    pause() {
        super.pause();
        this._clearTimeout();
    }

    resume() {
        super.resume();
        this.scheduleNext();
    }

    _clearTimeout() {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }
    }

    scheduleNext() {
        this._clearTimeout();
        this.callback();
        if (this.isAnimating) {
            this.timeoutId = setTimeout(() => this.scheduleNext(), this.delay);
        }
    }
}

// Particle emitter for spawning/destroying elements on interval
class ParticleEmitterToggle extends AnimationToggle {
    constructor(emitFn, emitInterval) {
        super([], true);
        this.emitFn = emitFn;
        this.emitInterval = emitInterval;
        this.emitterIntervalId = null;
    }

    toggle() {
        super.toggle();
        if (this.isAnimating) {
            this.startEmitting();
        } else {
            this._stopEmitting();
        }
    }

    pause() {
        super.pause();
        this._stopEmitting();
    }

    resume() {
        super.resume();
        this.startEmitting();
    }

    _stopEmitting() {
        if (this.emitterIntervalId) {
            clearInterval(this.emitterIntervalId);
            this.emitterIntervalId = null;
        }
    }

    startEmitting() {
        this._stopEmitting();
        this.emitterIntervalId = setInterval(() => {
            if (this.isAnimating) this.emitFn();
        }, this.emitInterval);
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
    if (!container) {
        console.error('Container not found: ' + parentSelector);
        return [];
    }
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

// Base class for physics-based objects
class PhysicsObject {
    constructor(element, config = {}) {
        this.element = element;
        this.x = config.x || 0;
        this.y = config.y || 0;
        this.vx = config.vx || 0;
        this.vy = config.vy || 0;
        this.radius = config.radius || 10;
        this.bounceCoeff = config.bounceCoeff || 0.8;
        this.bounds = config.bounds || { width: window.innerWidth, height: window.innerHeight };
    }

    update() {
        this.vy += Physics.gravity;
        this.x += this.vx;
        this.y += this.vy;
        this.checkBounds();
        this.render();
    }

    checkBounds() {
        if (this.y + this.radius > this.bounds.height) {
            this.y = this.bounds.height - this.radius;
            this.vy *= -this.bounceCoeff;
        }
        if (this.x < this.radius || this.x + this.radius > this.bounds.width) {
            this.vx *= -1;
            this.x = Math.max(this.radius, Math.min(this.bounds.width - this.radius, this.x));
        }
    }

    render() {
        this.element.style.left = (this.x - this.radius) + 'px';
        this.element.style.top = (this.y - this.radius) + 'px';
    }
}

// Animation manager for objects with animation loop
class AnimationManager {
    constructor(objects = []) {
        this.objects = objects;
        this.isRunning = true;
        this.animationId = null;
        this.isStarted = false;
    }

    start() {
        if (this.isStarted) {
            console.warn('AnimationManager already started');
            return;
        }
        this.isStarted = true;
        const loop = () => {
            if (this.isRunning) {
                this.objects.forEach(obj => obj.update());
            }
            this.animationId = requestAnimationFrame(loop);
        };
        loop();
    }

    stop() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
            this.isStarted = false;
        }
    }

    pause() {
        this.isRunning = false;
    }

    resume() {
        this.isRunning = true;
    }
}

// SVG pattern generator
const SVGPatterns = {
    createRadialWeb: (container, centerX, centerY, layers, pointsPerLayer) => {
        if (!container) {
            console.error('SVG container is null');
            return [];
        }
        const svg = container;
        const points = [];

        for (let layer = 1; layer <= layers; layer++) {
            const radius = (layer / layers) * 100;
            const layerPoints = [];

            for (let i = 0; i < pointsPerLayer; i++) {
                const angle = (i / pointsPerLayer) * Math.PI * 2;
                const x = centerX + Math.cos(angle) * radius;
                const y = centerY + Math.sin(angle) * radius;
                layerPoints.push({ x, y, angle, radius });

                if (i > 0) {
                    const prev = layerPoints[i - 1];
                    const line = createSVGElement('line', {
                        x1: prev.x, y1: prev.y, x2: x, y2: y,
                        class: 'web-line'
                    });
                    svg.appendChild(line);
                }

                if (layer > 1) {
                    const prevRadius = ((layer - 1) / layers) * 100;
                    const radialX = centerX + Math.cos(angle) * prevRadius;
                    const radialY = centerY + Math.sin(angle) * prevRadius;
                    const radial = createSVGElement('line', {
                        x1: radialX, y1: radialY, x2: x, y2: y,
                        class: 'web-line'
                    });
                    svg.appendChild(radial);
                }

                const node = createSVGElement('circle', {
                    cx: x, cy: y, r: 4,
                    class: 'web-node'
                });
                svg.appendChild(node);
            }
            points.push(...layerPoints);
        }
        return points;
    },

    createStarPattern: (container, count, radius, color = '#fff') => {
        const points = Patterns.createRadial(count, radius);
        points.forEach(p => {
            const circle = createSVGElement('circle', {
                cx: p.x, cy: p.y, r: 3,
                fill: color,
                class: 'star-point'
            });
            container.appendChild(circle);
        });
        return points;
    }
};