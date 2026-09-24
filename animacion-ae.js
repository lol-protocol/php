const svg = document.getElementById('networkSvg');
const nodes = [{x: 100, y: 100}, {x: 300, y: 100}, {x: 200, y: 300}, {x: 100, y: 300}, {x: 300, y: 300}];

// Draw connections
nodes.forEach((n1, i) => {
    nodes.forEach((n2, j) => {
        if (i < j && Math.random() > 0.4) {
            const line = createSVGElement('line', {
                x1: n1.x, y1: n1.y, x2: n2.x, y2: n2.y,
                class: 'network-line'
            });
            svg.appendChild(line);
        }
    });
});

// Draw nodes
nodes.forEach(n => {
    const circle = createSVGElement('circle', {
        cx: n.x, cy: n.y, r: 6,
        class: 'network-node'
    });
    svg.appendChild(circle);
});

const toggle = new AnimationToggle('.network-node');
