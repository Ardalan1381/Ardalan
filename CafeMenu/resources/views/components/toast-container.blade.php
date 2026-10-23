<div id="toast-container" class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6"></div>

@if (session('status'))
    <script>document.addEventListener('DOMContentLoaded', () => window.showToast?.(@json(session('status')), 'success'));</script>
@endif
@if (session('error'))
    <script>document.addEventListener('DOMContentLoaded', () => window.showToast?.(@json(session('error')), 'danger'));</script>
@endif
