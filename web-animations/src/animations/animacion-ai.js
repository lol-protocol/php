const svg = document.getElementById('webSvg');

if (!svg) {
    console.error('Web SVG element not found');
    throw new Error('Animation setup failed: #webSvg not found');
}

SVGPatterns.createRadialWeb(svg, 200, 200, 5, 8);
const toggle = new AnimationToggle('.web-svg');
