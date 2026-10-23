// The admin sidebar is an off-canvas drawer on small screens (see the
// `-translate-x-full`/`rtl:translate-x-full` classes in the layout) and a
// static column from the `md` breakpoint up (`2xl` on focus-mode screens
// such as the KDS and POS). This just toggles it open/
// closed on mobile — the layout's own responsive classes handle desktop.
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('admin-sidebar');
    const backdrop = document.getElementById('admin-sidebar-backdrop');
    const openButton = document.getElementById('admin-sidebar-open');
    const closeButton = document.getElementById('admin-sidebar-close');

    if (! sidebar || ! backdrop || ! openButton) {
        return;
    }

    function open() {
        sidebar.classList.remove('-translate-x-full', 'rtl:translate-x-full');
        backdrop.hidden = false;
    }

    function close() {
        sidebar.classList.add('-translate-x-full', 'rtl:translate-x-full');
        backdrop.hidden = true;
    }

    openButton.addEventListener('click', open);
    closeButton?.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! backdrop.hidden) {
            close();
        }
    });

    // Picking a page from the drawer should close it too, otherwise it
    // stays open over the newly-loaded page until manually dismissed.
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
});
