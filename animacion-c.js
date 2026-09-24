let isAnimating = true;

function toggleAnimation() {
    const page = document.querySelector('.page');
    if (isAnimating) {
        page.style.animationPlayState = 'paused';
        document.querySelector('button').textContent = 'Reanudar';
    } else {
        page.style.animationPlayState = 'running';
        document.querySelector('button').textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}