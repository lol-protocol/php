const container = document.querySelector('.wave-container');

if (!container) {
    console.error('Wave container not found');
    throw new Error('Animation setup failed: .wave-container not found');
}

function createWave() {
    const wave = createDiv('wave');
    container.appendChild(wave);
    setTimeout(() => wave.remove(), 2000);
}

const toggle = new ParticleEmitterToggle(createWave, 600);
toggle.startEmitting();
