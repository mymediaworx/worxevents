(function () {
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('app-nav') || document.getElementById('site-nav');

    if (!toggle || !nav) {
        return;
    }

    var narrow = window.matchMedia('(max-width: 40rem)');

    function sync() {
        if (narrow.matches) {
            nav.setAttribute('data-collapsed', 'true');
            toggle.setAttribute('aria-expanded', 'false');
        } else {
            nav.removeAttribute('data-collapsed');
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    sync();
    narrow.addEventListener('change', sync);

    toggle.addEventListener('click', function () {
        var collapsed = nav.getAttribute('data-collapsed') === 'true';
        if (collapsed) {
            nav.removeAttribute('data-collapsed');
            toggle.setAttribute('aria-expanded', 'true');
        } else {
            nav.setAttribute('data-collapsed', 'true');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
}());
