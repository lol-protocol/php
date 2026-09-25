const container = document.getElementById('hexContainer');

if (!container) {
    console.error('Hex container not found');
    throw new Error('Animation setup failed: #hexContainer not found');
}

for (let i = 0; i < 7; i++) {
    const hex = createDiv('hexagon hex-' + i);
    container.appendChild(hex);
}

const toggle = new AnimationToggle('.hexagon');
