const container = document.getElementById('sandContainer');

if (!container) {
    console.error('Sand container not found');
    throw new Error('Animation setup failed: #sandContainer not found');
}

class HourglassToggle extends AnimationToggle {
    constructor() {
        super();
        this.fallInterval = null;
        this.startSand();
    }

    toggle() {
        super.toggle();
        if (this.isAnimating) {
            this.startSand();
        } else {
            this._clearInterval();
        }
    }

    pause() {
        super.pause();
        this._clearInterval();
    }

    resume() {
        super.resume();
        this.startSand();
    }

    _clearInterval() {
        if (this.fallInterval) {
            clearInterval(this.fallInterval);
            this.fallInterval = null;
        }
    }

    startSand() {
        this._clearInterval();
        this.fallInterval = setInterval(() => {
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
