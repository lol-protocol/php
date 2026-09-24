const container = document.querySelector('.wave-container');

function createWave() {
    const wave = createDiv('wave');
    container.appendChild(wave);
    setTimeout(() => wave.remove(), 2000);
}

const toggle = new ParticleEmitterToggle(createWave, 600);
toggle.startEmitting();
