let isAnimating = true;

function toggleAnimation() {
    const ink = document.querySelector('.ink');
    const button = document.querySelector('button');

    if (!ink || !button) {
        console.error('Required elements not found');
        return;
    }

    if (isAnimating) {
        ink.style.animationPlayState = 'paused';
        button.textContent = 'Reanudar';
    } else {
        ink.style.animationPlayState = 'running';
        button.textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}