const container = document.getElementById('mandalaContainer');

for (let i = 0; i < 6; i++) {
    const ring = createDiv('mandala-ring');
    ring.style.width = (50 - i * 8) * 2 + 'px';
    ring.style.height = (50 - i * 8) * 2 + 'px';
    ring.style.top = (i * 8) + 'px';
    ring.style.left = (i * 8) + 'px';
    container.appendChild(ring);
}

for (let i = 0; i < 12; i++) {
    const dot = createDiv('mandala-dot');
    const angle = (i / 12) * Math.PI * 2;
    const x = Math.cos(angle) * 140;
    const y = Math.sin(angle) * 140;
    dot.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
    container.appendChild(dot);
}

const toggle = new AnimationToggle('.mandala-container');
