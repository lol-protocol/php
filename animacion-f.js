const shapes = [
    "M100,100 m-80,0 a80,80 0 1,0 160,0 a80,80 0 1,0 -160,0",
    "M30,30 L170,30 L170,170 L30,170 Z",
    "M100,20 L170,170 L30,170 Z",
    "M100,20 L145,55 L180,110 L145,145 L55,145 L20,110 L55,55 Z"
];

class MorphToggle {
    constructor() {
        this.isAnimating = true;
        this.currentShape = 0;
        this.path = document.querySelector('.morph-shape path');
        this.startMorph();
    }

    toggle() {
        this.isAnimating = !this.isAnimating;
        document.querySelector('button').textContent = this.isAnimating ? 'Pausar/Reanudar' : 'Reanudar';
    }

    startMorph() {
        setInterval(() => {
            if (this.isAnimating) {
                this.currentShape = (this.currentShape + 1) % shapes.length;
                this.path.setAttribute('d', shapes[this.currentShape]);
            }
        }, 2000);
    }
}

const toggle = new MorphToggle();