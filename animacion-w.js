const container = document.querySelector('.wave-container');
let isWaving = true;

class WaveToggle {
    constructor() {
        this.isAnimating = true;
        this.waveInterval = null;
        this.startWaves();
    }

    toggle() {
        this.isAnimating = !this.isAnimating;
        if (this.isAnimating) {
            this.startWaves();
            document.querySelector('button').textContent = 'Pausar/Reanudar';
        } else {
            clearInterval(this.waveInterval);
            document.querySelector('button').textContent = 'Reanudar';
        }
    }

    startWaves() {
        this.waveInterval = setInterval(() => {
            if (this.isAnimating) {
                const wave = createDiv('wave');
                container.appendChild(wave);
                setTimeout(() => wave.remove(), 2000);
            }
        }, 600);
    }
}

const toggle = new WaveToggle();
