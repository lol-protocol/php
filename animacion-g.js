const container = document.getElementById('particleContainer');
const colorArray = [Colors.RED.main, Colors.TEAL.main, Colors.YELLOW.main, Colors.GREEN.main, '#c44569'];

class Particle {
    constructor() {
        this.x = Math.random() * window.innerWidth;
        this.y = Math.random() * window.innerHeight * 0.3;
        this.size = Math.random() * 20 + 5;
        this.vx = (Math.random() - 0.5) * 4;
        this.vy = Math.random() * 2 + 1;
        this.color = colorArray[Math.floor(Math.random() * colorArray.length)];
        this.bounce = 0.8;
        this.el = createDiv('particle');
        this.el.style.background = this.color;
        this.el.style.width = this.size + 'px';
        this.el.style.height = this.size + 'px';
        container.appendChild(this.el);
    }

    update() {
        this.vy += Physics.gravity;
        this.x += this.vx;
        this.y += this.vy;

        if (this.y + this.size > window.innerHeight) {
            this.y = window.innerHeight - this.size;
            this.vy *= -this.bounce;
        }

        if (this.x < 0 || this.x + this.size > window.innerWidth) {
            this.vx *= -1;
        }

        this.el.style.left = this.x + 'px';
        this.el.style.top = this.y + 'px';
    }
}

const particles = [];
const toggle = new AnimationToggle([], true);

for (let i = 0; i < 15; i++) {
    particles.push(new Particle());
}

function animate() {
    if (toggle.isAnimating) {
        particles.forEach(p => p.update());
    }
    requestAnimationFrame(animate);
}

animate();