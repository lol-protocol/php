const container = document.getElementById('starRainContainer');
let isRaining = true;
let rainInterval;

class StarRainToggle {
    constructor() {
        this.isAnimating = true;
        this.startRain();
    }

    toggle() {
        this.isAnimating = !this.isAnimating;
        if (this.isAnimating) {
            this.startRain();
            document.querySelector('button').textContent = 'Pausar/Reanudar';
        } else {
            clearInterval(rainInterval);
            document.querySelector('button').textContent = 'Reanudar';
        }
    }

    startRain() {
        rainInterval = setInterval(() => {
            if (this.isAnimating) {
                const star = createDiv('falling-star');
                star.textContent = '★';
                star.style.left = Math.random() * 400 + 'px';
                star.style.animationDuration = (Math.random() * 3 + 2) + 's';
                container.appendChild(star);
                setTimeout(() => star.remove(), 5000);
            }
        }, 200);
    }
}

const toggle = new StarRainToggle();
