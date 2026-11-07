const container = document.getElementById('bouncingContainer');
const ball1 = document.getElementById('ball1');
const ball2 = document.getElementById('ball2');

if (!container || !ball1 || !ball2) {
    console.error('Required elements not found');
    throw new Error('Animation setup failed: missing elements');
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
            bounceMode: 'reflect',
            bounds: bounds
        });
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