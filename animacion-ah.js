const container = document.getElementById('starRainContainer');

function createFallingStar() {
    const star = createDiv('falling-star');
    star.textContent = '★';
    star.style.left = Math.random() * 400 + 'px';
    star.style.animationDuration = (Math.random() * 3 + 2) + 's';
    container.appendChild(star);
    setTimeout(() => star.remove(), 5000);
}

const toggle = new ParticleEmitterToggle(createFallingStar, 200);
toggle.startEmitting();
