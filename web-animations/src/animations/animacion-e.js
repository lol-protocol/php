const pyramid = document.getElementById('pyramid');

if (!pyramid) {
    console.error('Pyramid container not found');
    throw new Error('Animation setup failed: #pyramid not found');
}

let animationTimeout;

function buildPyramid() {
    pyramid.innerHTML = '';
    let delay = 0;
    for (let i = 5; i > 0; i--) {
        const row = createDiv('pyramid-row');
        for (let j = 0; j < i; j++) {
            const block = createDiv('block appear');
            block.style.animationDelay = generateCascadeDelay(delay, 1);
            row.appendChild(block);
            delay++;
        }
        pyramid.appendChild(row);
    }
}

const toggle = new SequenceToggle(() => buildPyramid(), 4000);
toggle.scheduleNext();