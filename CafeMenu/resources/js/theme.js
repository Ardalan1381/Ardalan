const STORAGE_KEY = 'cafe-theme';

function apply(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }

    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch (e) {
        // Storage unavailable (private browsing, disabled cookies) — the
        // toggle still works for the current page load via the class above.
    }

    apply(next);
});
