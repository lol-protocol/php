const shapes = [
    "M100,100 m-80,0 a80,80 0 1,0 160,0 a80,80 0 1,0 -160,0",
    "M30,30 L170,30 L170,170 L30,170 Z",
    "M100,20 L170,170 L30,170 Z",
    "M100,20 L145,55 L180,110 L145,145 L55,145 L20,110 L55,55 Z"
];

const path = document.querySelector('.morph-shape path');
let currentShape = 0;

function morphShape() {
    currentShape = (currentShape + 1) % shapes.length;
    path.setAttribute('d', shapes[currentShape]);
}

const toggle = new SequenceToggle(morphShape, 2000);
toggle.scheduleNext();