let isAnimating = true;

function toggleAnimation() {
    const ink = document.querySelector('.ink');
    if (isAnimating) {
        ink.style.animationPlayState = 'paused';
        document.querySelector('button').textContent = 'Reanudar';
    } else {
        ink.style.animationPlayState = 'running';
        document.querySelector('button').textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}