const container = document.getElementById('particleContainer');
const colorArray = [Colors.RED.main, Colors.TEAL.main, Colors.YELLOW.main, Colors.GREEN.main, '#c44569'];

if (!container) {
    console.error('Particle container not found');
    throw new Error('Animation setup failed: #particleContainer not found');
}

// Only what is its own: how a particle is born. Gravity, bouncing and drawing come from PhysicsObject,
// whose x, y are the centre of the circle.
class Particle extends PhysicsObject {
    constructor() {
        const size = Math.random() * 20 + 5;
        const el = createDiv('particle');
        el.style.background = colorArray[Math.floor(Math.random() * colorArray.length)];
        el.style.width = size + 'px';
        el.style.height = size + 'px';
        container.appendChild(el);

        super(el, {
            x: Math.random() * window.innerWidth,
            y: Math.random() * window.innerHeight * 0.3,
            vx: (Math.random() - 0.5) * 4,
            vy: Math.random() * 2 + 1,
            radius: size / 2,
            bounceCoeff: 0.8,
            bounds: { width: window.innerWidth, height: window.innerHeight }
        });
    }
}

const particles = [];
for (let i = 0; i < 15; i++) {
    particles.push(new Particle());
}

const manager = new AnimationManager(particles);
const toggle = new AnimationToggle();

const originalToggle = toggle.toggle.bind(toggle);
toggle.toggle = function() {
    originalToggle();
    if (this.isAnimating) {
        manager.resume();
    } else {
        manager.pause();
    }
};

manager.start();