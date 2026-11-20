document.addEventListener('DOMContentLoaded', () => {
    const signData = {
        love: {
            title: 'LOVE SIGN',
            desc: 'Extend thumb, index, and pinky fingers with palm facing forward.',
            imgSrc: 'images/love-sign.png',
            imgAlt: 'Love Sign hand illustration'
        },
        thankyou: {
            title: 'THANK YOU',
            desc: 'Touch fingertips of a flat hand to your chin and move forward.',
            imgSrc: 'images/thank-you.png',
            imgAlt: 'Thank You sign hand illustration'
        },
        hello: {
            title: 'HELLO',
            desc: 'Place open hand near forehead with palm out and move outward.',
            imgSrc: 'images/hello.png',
            imgAlt: 'Hello sign hand illustration'
        }
    };

    const tabs = document.querySelectorAll('.sign-tab');
    const signTitle = document.getElementById('signTitle');
    const signDesc = document.getElementById('signDesc');
    const signImage = document.getElementById('signImage');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const key = tab.getAttribute('data-sign');
            const selected = signData[key];
            if (!selected) return;

            // Update active button state
            tabs.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');

            // Update text & image
            signTitle.textContent = selected.title;
            signDesc.textContent = selected.desc;

            if (signImage) {
                signImage.src = selected.imgSrc;
                signImage.alt = selected.imgAlt;
            }
        });
    });

    // Mobile Navigation Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.getElementById('navLinks');

    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });
    }
});


