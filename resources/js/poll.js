// Runs `callback` on an interval, but pauses while the tab is hidden/minimized
// (Page Visibility API) so a backgrounded tab doesn't keep polling the server
// for no one to see. Fires an immediate refresh the moment the tab becomes
// visible again, so the view is never stale for longer than the user notices.
export function startPolling(callback, intervalMs) {
    let timer = null;

    const start = () => {
        if (timer) return;
        timer = setInterval(callback, intervalMs);
    };

    const stop = () => {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            callback();
            start();
        }
    });

    if (!document.hidden) start();

    return stop;
}
