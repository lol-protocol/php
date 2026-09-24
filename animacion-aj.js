const container = document.getElementById('sandContainer');
let isFalling = true;
let fallInterval;

class HourglassToggle {
    constructor() {
        this.isAnimating = true;
        this.startSand();
    }

    toggle() {
        this.isAnimating = !this.isAnimating;
        if (this.isAnimating) {
            this.startSand();
            document.querySelector('button').textContent = 'Pausar/Reanudar';
        } else {
            clearInterval(fallInterval);
            document.querySelector('button').textContent = 'Reanudar';
        }
    }

    startSand() {
        fallInterval = setInterval(() => {
            if (this.isAnimating) {
                const grain = createDiv('sand-grain');
                grain.style.left = (145 + Math.random() * 10 - 5) + 'px';
                grain.style.animationDuration = (Math.random() * 2 + 2) + 's';
                grain.style.animationDelay = Math.random() * 0.5 + 's';
                container.appendChild(grain);
                setTimeout(() => grain.remove(), 5000);
            }
        }, 150);
    }
}

const toggle = new HourglassToggle();
