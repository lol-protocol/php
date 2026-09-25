let isAnimating = true;

function toggleAnimation() {
    const cube = document.querySelector('.cube');
    const button = document.querySelector('button');

    if (!cube || !button) {
        console.error('Required elements not found');
        return;
    }

    if (isAnimating) {
        cube.style.animationPlayState = 'paused';
        button.textContent = 'Reanudar';
    } else {
        cube.style.animationPlayState = 'running';
        button.textContent = 'Pausar/Reanudar';
    }
    isAnimating = !isAnimating;
}