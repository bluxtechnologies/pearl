/**
 * Pearl Framework — JavaScript Entry Point
 *
 * HTMX handles server-driven interactivity: wire/ modules can return
 * response_html() fragments and any element with hx-get/hx-post/etc.
 * swaps them in — no client-side routing or state management needed
 * for most admin-panel interactions.
 *
 * Alpine.js handles purely local UI state that never needs the server
 * (dropdown open/closed, password visibility toggle, mobile nav
 * toggle) — declared inline via x-data, no separate JS file per widget.
 *
 * The ripple effect below is the one piece of custom JS: it's a
 * genuine visual effect (positioning a ripple at the exact click
 * point), not something either library provides out of the box.
 */

import htmx from 'htmx.org';
import Alpine from 'alpinejs';
import './main.css';

window.htmx = htmx;
window.Alpine = Alpine;
Alpine.start();

/**
 * Ripple effect for any element with the .pearl-btn class. Injects a
 * short-lived span at the click coordinates; CSS (pearl-ripple
 * keyframe in animations.css) handles the actual animation. Uses
 * event delegation on document so it works for buttons added later
 * by HTMX swaps too, without re-binding listeners.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('.pearl-btn');

    if (!button) {
        return;
    }

    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const span = document.createElement('span');

    span.className = 'pearl-ripple-span';
    span.style.width = `${size}px`;
    span.style.height = `${size}px`;
    span.style.left = `${event.clientX - rect.left - size / 2}px`;
    span.style.top = `${event.clientY - rect.top - size / 2}px`;

    button.appendChild(span);

    span.addEventListener('animationend', () => span.remove());
});
