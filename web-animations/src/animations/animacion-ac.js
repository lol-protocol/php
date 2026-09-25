const stars = createAnimatedElements(30, 'star', '#nebulaContainer');
stars.forEach(star => {
    star.style.setProperty('--tx', (Math.random() * 200 - 100) + 'px');
    star.style.setProperty('--ty', (Math.random() * 200 - 100) + 'px');
    star.style.left = Math.random() * 400 + 'px';
    star.style.top = Math.random() * 400 + 'px';
    star.style.animationDuration = (Math.random() * 6 + 4) + 's';
    star.style.animationDelay = Effects.randomDelay(0, 2) + 's';
});

const toggle = new AnimationToggle('.star');
