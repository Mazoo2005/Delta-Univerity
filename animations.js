document.addEventListener('DOMContentLoaded', function() {
    const captcha = document.querySelector('.captcha');
    let shakeInterval;

    captcha.addEventListener('click', function() {
        clearInterval(shakeInterval);
        captcha.style.animation = 'shake 1s infinite';
        shakeInterval = setInterval(() => {
            captcha.style.animation = '';
        }, 1000);
    });
});