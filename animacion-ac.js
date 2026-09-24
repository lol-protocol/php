const container = document.getElementById('nebulaContainer');

for (let i = 0; i < 30; i++) {
    const star = createDiv('star');
    const tx = Math.random() * 200 - 100;
    const ty = Math.random() * 200 - 100;
    star.style.setProperty('--tx', tx + 'px');
    star.style.setProperty('--ty', ty + 'px');
    star.style.left = Math.random() * 400 + 'px';
    star.style.top = Math.random() * 400 + 'px';
    star.style.animationDuration = (Math.random() * 6 + 4) + 's';
    star.style.animationDelay = Math.random() * 2 + 's';
    container.appendChild(star);
}

const toggle = new AnimationToggle('.star');
