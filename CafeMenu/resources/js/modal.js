document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        const modal = document.getElementById(opener.dataset.modalOpen);
        modal?.showModal();
        return;
    }

    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        closer.closest('dialog')?.close();
        return;
    }

    // Click on the backdrop (the <dialog> element itself, not its content) closes it.
    if (event.target.matches('dialog[data-modal]')) {
        event.target.close();
    }
});
