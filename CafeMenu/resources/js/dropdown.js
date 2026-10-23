// Native <details> stays open until clicked again; close any open dropdown
// when the user clicks outside of it.
document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-dropdown][open]').forEach((details) => {
        if (! details.contains(event.target)) {
            details.removeAttribute('open');
        }
    });
});
