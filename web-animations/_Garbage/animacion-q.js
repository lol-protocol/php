let isAnimating = true;

function toggleAnimation() {
    const auroras = document.querySelectorAll('.aurora');
    const button = document.querySelector('button');

    if (!button) {
        console.error('Button not found');
        return;
    }

    if (isAnimating) {
        auroras.forEach(a => a.style.animationPlayState = 'paused');
        button.textContent = 'Reanudar';
    } else {
        auroras.forEach(a => a.style.animationPlayState = 'running');
        button.textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}