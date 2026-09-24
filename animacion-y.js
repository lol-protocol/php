const container = document.getElementById('hexContainer');

for (let i = 0; i < 7; i++) {
    const hex = createDiv('hexagon hex-' + i);
    container.appendChild(hex);
}

const toggle = new AnimationToggle('.hexagon');
