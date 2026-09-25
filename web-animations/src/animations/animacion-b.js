const container = document.getElementById('bouncingContainer');
const ball1 = document.getElementById('ball1');
const ball2 = document.getElementById('ball2');

if (!container || !ball1 || !ball2) {
    console.error('Required elements not found');
    throw new Error('Animation setup failed: missing elements');
}

// Refleja lo que atravesó la pared en vez de recortarlo: recortar le quita
// altura en cada rebote y, con gravedad, las bolas terminan quietas en el piso.
function reflect(pos, vel, min, max) {
    if (pos < min) return [2 * min - pos, Math.abs(vel)];
    if (pos > max) return [2 * max - pos, -Math.abs(vel)];
    return [pos, vel];
}

class Ball extends PhysicsObject {
    constructor(element, bounds, radius) {
        super(element, {
            x: Math.random() * (bounds.width - radius * 2) + radius,
            y: Math.random() * (bounds.height - radius * 2) + radius,
            vx: (Math.random() - 0.5) * 4,
            vy: (Math.random() - 0.5) * 4,
            radius: radius,
            bounceCoeff: 1,
            bounds: bounds
        });
        this.render();
    }

    checkBounds() {
        [this.x, this.vx] = reflect(this.x, this.vx, this.radius, this.bounds.width - this.radius);
        [this.y, this.vy] = reflect(this.y, this.vy, this.radius, this.bounds.height - this.radius);
    }

    update() {
        this.vy += Physics.gravity;
        this.x += this.vx;
        this.y += this.vy;
        this.checkBounds();
        this.render();
    }
}

const bounds = { width: container.offsetWidth, height: container.offsetHeight };
const balls = [
    new Ball(ball1, bounds, 20),
    new Ball(ball2, bounds, 25)
];

const manager = new AnimationManager(balls);
const toggle = new AnimationToggle();

// Bridge AnimationToggle to AnimationManager
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

window.addEventListener('resize', () => {
    bounds.width = container.offsetWidth;
    bounds.height = container.offsetHeight;
});