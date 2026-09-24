const container = document.getElementById('mandalaContainer');

for (let i = 0; i < 6; i++) {
    const ring = createDiv('mandala-ring');
    const size = (50 - i * 8) * 2;
    ring.style.width = size + 'px';
    ring.style.height = size + 'px';
    ring.style.top = (i * 8) + 'px';
    ring.style.left = (i * 8) + 'px';
    container.appendChild(ring);
}

const dotPoints = Patterns.createRadial(12, 140);
dotPoints.forEach(p => {
    const dot = createDiv('mandala-dot');
    dot.style.transform = `translate(calc(-50% + ${p.x}px), calc(-50% + ${p.y}px))`;
    container.appendChild(dot);
});

const toggle = new AnimationToggle('.mandala-container');
