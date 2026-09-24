const container = document.getElementById('bouncingContainer');
const ball1 = document.getElementById('ball1');
const ball2 = document.getElementById('ball2');

class Ball {
    constructor(element, containerWidth, containerHeight, radius) {
        this.element = element;
        this.containerWidth = containerWidth;
        this.containerHeight = containerHeight;
        this.radius = radius;
        this.x = Math.random() * (containerWidth - radius * 2) + radius;
        this.y = Math.random() * (containerHeight - radius * 2) + radius;
        this.vx = (Math.random() - 0.5) * 4;
        this.vy = (Math.random() - 0.5) * 4;
        this.update();
    }

    update() {
        this.x += this.vx;
        this.y += this.vy;

        if (this.x - this.radius <= 0 || this.x + this.radius >= this.containerWidth) {
            this.vx = -this.vx;
            this.x = Math.max(this.radius, Math.min(this.containerWidth - this.radius, this.x));
        }

        if (this.y - this.radius <= 0 || this.y + this.radius >= this.containerHeight) {
            this.vy = -this.vy;
            this.y = Math.max(this.radius, Math.min(this.containerHeight - this.radius, this.y));
        }

        this.element.style.left = (this.x - this.radius) + 'px';
        this.element.style.top = (this.y - this.radius) + 'px';
    }
}

const containerWidth = container.offsetWidth;
const containerHeight = container.offsetHeight;
const balls = [
    new Ball(ball1, containerWidth, containerHeight, 20),
    new Ball(ball2, containerWidth, containerHeight, 25)
];

const toggle = new AnimationToggle([], true);

function animate() {
    if (toggle.isAnimating) {
        balls.forEach(ball => ball.update());
    }
    requestAnimationFrame(animate);
}

animate();

window.addEventListener('resize', () => {
    const newWidth = container.offsetWidth;
    const newHeight = container.offsetHeight;
    balls.forEach(ball => {
        ball.containerWidth = newWidth;
        ball.containerHeight = newHeight;
    });
});