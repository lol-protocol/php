let isAnimating = true;

function toggleAnimation() {
    const auroras = document.querySelectorAll('.aurora');
    if (isAnimating) {
        auroras.forEach(a => a.style.animationPlayState = 'paused');
        document.querySelector('button').textContent = 'Reanudar';
    } else {
        auroras.forEach(a => a.style.animationPlayState = 'running');
        document.querySelector('button').textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}