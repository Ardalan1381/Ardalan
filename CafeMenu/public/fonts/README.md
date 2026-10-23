# Persian font (Vazirmatn)

Persian text currently renders with a system-font fallback (Tahoma/Arial),
which looks correct but not as polished as a proper Persian typeface.

To activate Vazirmatn, download the woff2 files from the official project
(https://github.com/rastikerdar/vazirmatn, SIL Open Font License) and place
them here with these exact names — `resources/css/app.css` already declares
the matching `@font-face` rules, so no code changes are needed:

- `Vazirmatn-Regular.woff2` (weight 400)
- `Vazirmatn-Medium.woff2` (weight 500)
- `Vazirmatn-Bold.woff2` (weight 700)

Then run `npm run build` (or restart `npm run dev`) to pick them up.
