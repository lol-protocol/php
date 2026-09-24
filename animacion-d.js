let isAnimating = true;

function toggleAnimation() {
    const cube = document.querySelector('.cube');
    if (isAnimating) {
        cube.style.animationPlayState = 'paused';
        document.querySelector('button').textContent = 'Reanudar';
    } else {
        cube.style.animationPlayState = 'running';
        document.querySelector('button').textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}