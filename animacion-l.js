let isAnimating = true;

function toggleAnimation() {
    const rings = document.querySelectorAll('.ring');
    if (isAnimating) {
        rings.forEach(r => r.style.animationPlayState = 'paused');
        document.querySelector('button').textContent = 'Reanudar';
    } else {
        rings.forEach(r => r.style.animationPlayState = 'running');
        document.querySelector('button').textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}