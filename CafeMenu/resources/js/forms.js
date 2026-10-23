// A form with data-confirm="message" (HTML-escaped by Blade, so translated
// strings containing apostrophes/quotes are always safe) asks for
// confirmation before submitting — replaces fragile inline
// onsubmit="confirm('...')" string interpolation.
document.addEventListener('submit', (event) => {
    const confirmForm = event.target;
    if (confirmForm instanceof HTMLFormElement && confirmForm.dataset.confirm) {
        if (! window.confirm(confirmForm.dataset.confirm)) {
            event.preventDefault();
            return;
        }
    }
});

// A checkbox with data-confirm-uncheck="message" that the server rendered
// as checked (e.g. a product's "Active"/"Available" toggle) asks for
// confirmation if it gets unchecked and the form is submitted — catches an
// accidental uncheck that would silently hide something from customers,
// without nagging on every normal save where it stays checked.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (! (form instanceof HTMLFormElement)) {
        return;
    }

    const uncheckedGuardedBox = Array.from(form.querySelectorAll('[data-confirm-uncheck]'))
        .find((checkbox) => checkbox.defaultChecked && ! checkbox.checked);

    if (uncheckedGuardedBox && ! window.confirm(uncheckedGuardedBox.dataset.confirmUncheck)) {
        event.preventDefault();
    }
});

// Generic repeatable-row form sections (purchase line items, stock
// adjustment/waste/return lines, recipe editors): a container marked
// data-repeater holds a data-repeater-rows list of data-repeater-row
// elements and a <template data-repeater-template> whose markup contains
// the literal placeholder __INDEX__ everywhere an array index belongs
// (e.g. name="lines[__INDEX__][quantity]"). Clicking [data-repeater-add]
// appends a fresh row from the template with __INDEX__ replaced by a
// counter that only ever increases, so a removed row's index is never
// reused and can't collide with one still on the page. Clicking a row's
// [data-repeater-remove] just removes that row — no re-indexing needed,
// since array keys don't need to be contiguous for PHP to parse them.
document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-repeater-add]');
    if (addButton) {
        const repeater = addButton.closest('[data-repeater]');
        const template = repeater?.querySelector('[data-repeater-template]');
        const rows = repeater?.querySelector('[data-repeater-rows]');
        if (! repeater || ! template || ! rows) {
            return;
        }

        const nextIndex = Number(repeater.dataset.repeaterNextIndex ?? rows.children.length);
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex)));
        repeater.dataset.repeaterNextIndex = String(nextIndex + 1);
        return;
    }

    const removeButton = event.target.closest('[data-repeater-remove]');
    if (removeButton) {
        removeButton.closest('[data-repeater-row]')?.remove();
    }
});

// Disable a form's submit button on submission to prevent duplicate
// requests (double-clicks, slow networks). Opt out with data-allow-resubmit.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (! (form instanceof HTMLFormElement) || form.hasAttribute('data-allow-resubmit')) {
        return;
    }

    const button = form.querySelector('button[type="submit"], input[type="submit"]');
    if (! button || button.disabled) {
        return;
    }

    // Deferred so the browser finishes reading the submitter's name/value
    // into the request before we disable it — disabling synchronously here
    // can drop that field from some implementations' form serialization.
    setTimeout(() => {
        button.disabled = true;
        if (button.dataset.loadingText) {
            button.dataset.originalText = button.textContent;
            button.textContent = button.dataset.loadingText;
        }
    }, 0);
});
