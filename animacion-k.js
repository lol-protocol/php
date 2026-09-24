const container = document.getElementById('eqContainer');

for (let i = 0; i < 7; i++) {
    const bar = createDiv('bar');
    container.appendChild(bar);
}

const toggle = new AnimationToggle('.bar');