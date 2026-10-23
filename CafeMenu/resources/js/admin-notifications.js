function playBeep() {
    try {
        const context = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(0.2, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.4);

        oscillator.connect(gain);
        gain.connect(context.destination);

        oscillator.start();
        oscillator.stop(context.currentTime + 0.4);
    } catch (e) {
        // Audio unavailable (autoplay policy, unsupported browser) — fail silently.
    }
}

// A more urgent two-tone ring, distinct from the single order-arrival beep —
// this one repeats (see WaiterCallAlarm below) until staff notices it, so it
// needs to actually stand out from every other notification sound.
function playAlarmRing() {
    try {
        const context = new (window.AudioContext || window.webkitAudioContext)();
        const now = context.currentTime;

        [0, 0.22].forEach((offset, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            oscillator.type = 'square';
            oscillator.frequency.value = index === 0 ? 987.77 : 1318.51; // B5 -> E6
            gain.gain.setValueAtTime(0.0001, now + offset);
            gain.gain.exponentialRampToValueAtTime(0.18, now + offset + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.2);

            oscillator.connect(gain);
            gain.connect(context.destination);

            oscillator.start(now + offset);
            oscillator.stop(now + offset + 0.22);
        });
    } catch (e) {
        // Audio unavailable (autoplay policy, unsupported browser) — fail silently.
    }
}

// Persistent visual + repeating-audio alert for waiter calls specifically —
// these need staff to notice *now*, unlike a routine new-order toast, so a
// single beep and an auto-dismissing toast aren't enough. Stays on screen
// until a staff member explicitly silences it or opens the waiter-calls
// page; the ring repeats for a while, then goes quiet on its own so it
// can't ring forever if everyone's on the floor and away from a screen.
const WaiterCallAlarm = (() => {
    const RING_INTERVAL_MS = 2800;
    const MAX_RINGS = 20; // ~56s of ringing

    let calls = [];
    let ringTimer = null;
    let ringCount = 0;
    let banner = null;

    function ensureBanner() {
        if (banner) {
            return banner;
        }

        banner = document.createElement('div');
        banner.setAttribute('role', 'alert');
        banner.className = [
            'fixed inset-x-0 top-0 z-50 flex items-center justify-between gap-3',
            'bg-danger px-4 py-3 text-sm font-medium text-white shadow-lg',
        ].join(' ');

        const message = document.createElement('span');
        message.setAttribute('data-waiter-alarm-message', '');
        message.className = 'flex-1';

        const actions = document.createElement('div');
        actions.className = 'flex shrink-0 items-center gap-2';

        if (window.ADMIN_WAITER_CALLS_URL) {
            const viewLink = document.createElement('a');
            viewLink.href = window.ADMIN_WAITER_CALLS_URL;
            viewLink.className = 'rounded-lg bg-white/15 px-3 py-1.5 hover:bg-white/25';
            viewLink.textContent = window.trans('js.waiter_call_view');
            actions.appendChild(viewLink);
        }

        const silenceButton = document.createElement('button');
        silenceButton.type = 'button';
        silenceButton.className = 'rounded-lg bg-white/15 px-3 py-1.5 hover:bg-white/25';
        silenceButton.textContent = window.trans('js.waiter_call_silence');
        silenceButton.addEventListener('click', dismiss);
        actions.appendChild(silenceButton);

        banner.appendChild(message);
        banner.appendChild(actions);
        document.body.appendChild(banner);

        return banner;
    }

    function render() {
        const el = ensureBanner();
        const messageEl = el.querySelector('[data-waiter-alarm-message]');

        messageEl.textContent = calls.length === 1
            ? window.trans('js.waiter_call_alert', { table: calls[0].table })
            : window.trans('js.waiter_call_alert_multiple', { count: String(calls.length) });

        el.hidden = false;
    }

    function startRinging() {
        if (ringTimer) {
            return;
        }

        ringCount = 0;
        playAlarmRing();
        ringCount += 1;

        ringTimer = window.setInterval(() => {
            if (ringCount >= MAX_RINGS) {
                window.clearInterval(ringTimer);
                ringTimer = null;
                return;
            }

            playAlarmRing();
            ringCount += 1;
        }, RING_INTERVAL_MS);
    }

    function stopRinging() {
        if (ringTimer) {
            window.clearInterval(ringTimer);
            ringTimer = null;
        }
    }

    function push(call) {
        calls.push(call);
        render();
        startRinging();
    }

    function dismiss() {
        calls = [];
        stopRinging();
        if (banner) {
            banner.hidden = true;
        }
    }

    return { push };
})();

document.addEventListener('DOMContentLoaded', () => {
    const userId = window.ADMIN_USER_ID;
    if (! userId || ! window.Echo) {
        return;
    }

    const badge = document.querySelector('[data-notification-count]');

    function bumpBadge() {
        if (! badge) {
            return;
        }
        const current = Number(badge.textContent || '0');
        badge.textContent = current + 1;
        badge.hidden = false;
    }

    window.Echo.private(`App.Models.User.${userId}`).notification((notification) => {
        bumpBadge();

        if (notification && notification.waiter_call_id) {
            WaiterCallAlarm.push({ id: notification.waiter_call_id, table: notification.table });
            return;
        }

        playBeep();
    });
});
