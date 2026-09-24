let isAnimating = true;

function toggleAnimation() {
    const rings = document.querySelectorAll('.ring');
    const button = document.querySelector('button');

    if (!button) {
        console.error('Button not found');
        return;
    }

    if (isAnimating) {
        rings.forEach(r => r.style.animationPlayState = 'paused');
        button.textContent = 'Reanudar';
    } else {
        rings.forEach(r => r.style.animationPlayState = 'running');
        button.textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}