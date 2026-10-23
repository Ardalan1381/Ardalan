// Resolving a waiter call from the list page happens over fetch rather than
// a normal form POST, so a staff member acting on one row doesn't lose their
// place on a busy list via a full-page reload.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-resolve-waiter-call]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const data = await response.json();

                if (! response.ok) {
                    window.showToast?.(data.message || window.trans('js.something_went_wrong'), 'danger');
                    button.disabled = false;
                    return;
                }

                const row = button.closest('tr');
                const statusCell = row.querySelector('[data-waiter-call-status-cell]');
                const actionsCell = row.querySelector('[data-waiter-call-actions-cell]');

                const badge = document.createElement('span');
                badge.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-success-subtle text-success';
                badge.textContent = window.trans('js.waiter_call_resolved_status');

                const resolvedByNote = document.createElement('span');
                resolvedByNote.className = 'ms-1 text-xs text-gray-500 dark:text-gray-400';
                resolvedByNote.textContent = window.trans('js.waiter_call_resolved_by', { name: data.resolved_by });

                statusCell.replaceChildren(badge, resolvedByNote);
                actionsCell.replaceChildren();

                window.showToast?.(data.message, 'success');
            } catch (e) {
                window.showToast?.(window.trans('js.network_error'), 'danger');
                button.disabled = false;
            }
        });
    });
});
