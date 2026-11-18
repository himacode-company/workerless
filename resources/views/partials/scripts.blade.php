<script>
(function () {
    var root = document.documentElement;

    function get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function set(key, value) { try { localStorage.setItem(key, value); } catch (e) {} }

    // Theme: follows the system until the user picks one.
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var current = root.getAttribute('data-theme');
            var dark = current === 'dark' || (current !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var next = dark ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            set('workerless-theme', next);
        });
    });

    var dialog = document.getElementById('wl-confirm');

    // Live mode: reload every 10 s while the tab is visible and no dialog is open.
    var live = get('workerless-live') === '1';
    var timer = null;

    function paint() {
        document.querySelectorAll('[data-live-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', live ? 'true' : 'false');
        });
    }

    function schedule() {
        clearTimeout(timer);
        if (!live) return;
        timer = setTimeout(function () {
            if (document.hidden || (dialog && dialog.open)) { schedule(); } else { window.location.reload(); }
        }, 10000);
    }

    document.querySelectorAll('[data-live-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            live = !live;
            set('workerless-live', live ? '1' : '0');
            paint();
            schedule();
        });
    });

    paint();
    schedule();

    // Styled confirmation before important actions (falls back to the browser one on very old browsers).
    var pending = null;
    var title = document.getElementById('wl-confirm-title');
    var text = document.getElementById('wl-confirm-text');
    var ok = document.getElementById('wl-confirm-ok');

    // Navigation loader (blur + spinner): same-site links in this tab, and form submissions.
    function loading() { document.body.classList.add('is-loading'); }

    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target === '_blank' || link.origin !== window.location.origin || link.getAttribute('aria-disabled') === 'true') return;
        if (link.getAttribute('href').charAt(0) === '#') return;
        loading();
    });

    // Back/forward cache restores the page as it was: hide the loader again.
    window.addEventListener('pageshow', function () { document.body.classList.remove('is-loading'); });

    function submit(form) {
        loading();
        form.querySelectorAll('button').forEach(function (b) { b.classList.add('is-busy'); });
        form.submit(); // does not fire "submit" again
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!dialog || typeof dialog.showModal !== 'function') {
                if (window.confirm(form.getAttribute('data-confirm'))) submit(form);
                return;
            }

            pending = form;
            title.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
            text.textContent = form.getAttribute('data-confirm');
            ok.textContent = form.getAttribute('data-confirm-button') || 'Confirm';
            dialog.setAttribute('data-tone', form.getAttribute('data-confirm-tone') || 'danger');
            dialog.returnValue = '';
            dialog.showModal();
        });
    });

    if (dialog) {
        dialog.addEventListener('close', function () {
            var form = pending;
            pending = null;
            if (form && dialog.returnValue === 'ok') submit(form);
        });

        // Click outside the box closes it.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) dialog.close('cancel');
        });
    }
})();
</script>
