const pyramid = document.getElementById('pyramid');
let animationTimeout;

function buildPyramid() {
    pyramid.innerHTML = '';
    let delay = 0;
    for (let i = 5; i > 0; i--) {
        const row = createDiv('pyramid-row');
        for (let j = 0; j < i; j++) {
            const block = createDiv('block appear');
            block.style.animationDelay = generateCascadeDelay(delay, 1);
            row.appendChild(block);
            delay++;
        }
        pyramid.appendChild(row);
    }
}

class PyramidToggle {
    constructor() {
        this.isAnimating = true;
    }

    toggle() {
        this.isAnimating = !this.isAnimating;
        if (this.isAnimating) {
            this.startAnimation();
            document.querySelector('button').textContent = 'Pausar/Reanudar';
        } else {
            clearTimeout(animationTimeout);
            document.querySelector('button').textContent = 'Reanudar';
        }
    }

    startAnimation() {
        buildPyramid();
        if (this.isAnimating) {
            animationTimeout = setTimeout(() => this.startAnimation(), 4000);
        }
    }
}

const toggle = new PyramidToggle();
toggle.startAnimation();