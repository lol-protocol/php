const container = document.getElementById('eqContainer');

if (!container) {
    console.error('EQ container not found');
    throw new Error('Animation setup failed: #eqContainer not found');
}

for (let i = 0; i < 7; i++) {
    const bar = createDiv('bar');
    container.appendChild(bar);
}

const toggle = new AnimationToggle('.bar');