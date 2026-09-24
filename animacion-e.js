const pyramid = document.getElementById('pyramid');
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

class SequenceToggle extends AnimationToggle {
    constructor(callback, delay = 4000) {
        super([], true);
        this.callback = callback;
        this.delay = delay;
        this.timeoutId = null;
    }

    toggle() {
        super.toggle();
        if (this.isAnimating) {
            this.scheduleNext();
        } else {
            clearTimeout(this.timeoutId);
        }
    }

    scheduleNext() {
        this.callback();
        if (this.isAnimating) {
            this.timeoutId = setTimeout(() => this.scheduleNext(), this.delay);
        }
    }
}

const toggle = new SequenceToggle(() => buildPyramid(), 4000);
toggle.scheduleNext();