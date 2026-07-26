// Delays invoking `fn` until `wait` ms have passed since the last call -
// collapses a burst of rapid calls (e.g. someone tapping through several
// dates) into a single trailing invocation, so we don't fire one network
// request per tap and risk tripping the availability-read rate limit.
export function debounce(fn, wait) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}
