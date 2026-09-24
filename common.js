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