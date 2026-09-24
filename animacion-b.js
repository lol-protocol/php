const container = document.getElementById('bouncingContainer');
const ball1 = document.getElementById('ball1');
const ball2 = document.getElementById('ball2');

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
        if (this.x < this.radius || this.x + this.radius > this.bounds.width) {
            this.vx = -this.vx;
            this.x = Math.max(this.radius, Math.min(this.bounds.width - this.radius, this.x));
        }
        if (this.y < this.radius || this.y + this.radius > this.bounds.height) {
            this.vy = -this.vy;
            this.y = Math.max(this.radius, Math.min(this.bounds.height - this.radius, this.y));
        }
    }

    update() {
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
const toggle = new AnimationToggle([], true);

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