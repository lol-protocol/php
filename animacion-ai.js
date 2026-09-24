const svg = document.getElementById('webSvg');
const center = {x: 200, y: 200};
const layers = 5;
const pointsPerLayer = 8;

for (let layer = 1; layer <= layers; layer++) {
    const radius = (layer / layers) * 100;
    for (let i = 0; i < pointsPerLayer; i++) {
        const angle = (i / pointsPerLayer) * Math.PI * 2;
        const x = center.x + Math.cos(angle) * radius;
        const y = center.y + Math.sin(angle) * radius;

        if (i > 0) {
            const prevAngle = ((i - 1) / pointsPerLayer) * Math.PI * 2;
            const prevX = center.x + Math.cos(prevAngle) * radius;
            const prevY = center.y + Math.sin(prevAngle) * radius;

            const line = createSVGElement('line', {
                x1: prevX, y1: prevY, x2: x, y2: y,
                class: 'web-line'
            });
            svg.appendChild(line);
        }

        if (layer > 1) {
            const prevRadius = ((layer - 1) / layers) * 100;
            const radialX = center.x + Math.cos(angle) * prevRadius;
            const radialY = center.y + Math.sin(angle) * prevRadius;

            const radial = createSVGElement('line', {
                x1: radialX, y1: radialY, x2: x, y2: y,
                class: 'web-line'
            });
            svg.appendChild(radial);
        }

        const node = createSVGElement('circle', {
            cx: x, cy: y, r: 4,
            class: 'web-node'
        });
        svg.appendChild(node);
    }
}

const toggle = new AnimationToggle('.web-svg');
