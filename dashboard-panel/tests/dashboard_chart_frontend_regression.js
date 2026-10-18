/** Geometry regression: no database fixtures or real orders are required. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public_html/assets/js/admin-dashboard.js'), 'utf8');
const start = source.indexOf('    function salesTooltipPosition(');
const end = source.indexOf("    document.querySelectorAll('[data-sales-chart]')", start);
assert.ok(start > 0 && end > start);
const context = {};
vm.runInNewContext(source.slice(start, end), context);
const position = context.salesTooltipPosition;
let checks = 0, floating = 0, fallback = 0;
function check(value, label) { assert.ok(value, label); checks++; }
function overlaps(box, size, point, padding = 8) {
    return point.x > box.left - padding && point.x < box.left + size.width + padding
        && point.y > box.top - padding && point.y < box.top + size.height + padding;
}
function pointsFor(width, count, pattern, left = 0, top = 30) {
    return Array.from({ length: count }, (_, i) => {
        let y = pattern === 'zero' || (pattern === 'mixed' && i % 3 === 0) ? 222 : pattern === 'flat' ? 18 : pattern === 'baseline' ? 210
            : pattern === 'alternating' ? (i % 2 ? 18 : 222) : 18 + ((i * 73) % 204);
        return { x: left + (count === 1 ? width / 2 : 28 + i * (width - 56) / (count - 1)), y: top + y, sales: pattern === 'zero' || (pattern === 'mixed' && i % 3 === 0) ? 0 : i + 25 };
    });
}
for (const width of [200, 280, 360, 480, 640, 1040, 1500]) {
    for (const count of [1, 2, 7, 30, 31, 90, 365]) {
        for (const pattern of ['zero', 'flat', 'baseline', 'alternating', 'busy', 'mixed']) {
            for (const height of [170, 207, 255]) { // Multi-currency/long date content can grow the card.
                const plot = { left: 13, right: width + 13, top: 30, height: 252 };
                const size = { width: Math.min(244, width - 16), height };
                const points = pointsFor(width, count, pattern, 13);
                for (const index of [...new Set([0, 1, Math.floor(count / 2), count - 2, count - 1].filter(i => i >= 0 && i < count))]) {
                    const box = position(plot, size, points, index);
                    if (!box) { fallback++; continue; }
                    floating++;
                    check(box.left >= plot.left + 8 && box.left + size.width <= plot.right - 8 + .001, 'Tooltip stays within horizontal chart bounds');
                    check(box.top >= plot.top + 8 && box.top + size.height <= plot.top + 228 + .001, 'Tooltip never covers date-axis labels');
                    check(!overlaps(box, size, points[index]), 'Selected marker stays visible');
                    for (const nearby of [index - 1, index + 1].filter(i => i >= 0 && i < count)) {
                        check(!overlaps(box, size, points[nearby]), 'Previous/next marker stays visible in dense daily sales');
                    }
                    check(points[index].sales === 0 || ['left', 'right'].includes(box.side), 'Sales use side placement');
                }
            }
        }
    }
}
const plot = { left: 0, right: 1040, top: 30, height: 252 }, size = { width: 244, height: 190 };
const zero = position(plot, size, pointsFor(1040, 30, 'zero'), 15);
check(zero && zero.side === 'above', 'Zero sales prefer the space above the baseline');
const flat = pointsFor(1040, 30, 'flat');
check(position(plot, size, flat, 0).side === 'right', 'First day opens to the right');
check(position(plot, size, flat, 29).side === 'left', 'Last day opens to the left');
check(position({ left: 0, right: 280, top: 30, height: 252 }, size, pointsFor(280, 30, 'flat'), 15) === null, 'Narrow centre falls back below the plot instead of covering markers');
check(position(plot, { width: 244, height: 300 }, flat, 15) === null, 'Very tall content falls back without covering the axis');
check(floating > 0 && fallback > 0, 'Both floating and flow placements exercised');
console.log(`PASS: ${checks} chart placement assertions (${floating} floating, ${fallback} below-plot cases)`);

// Exercise real event wiring, including touch scrolling and next/previous controls.
function chartHarness(isMobile) {
    function node() {
        const names = new Set();
        return { attrs: {}, style: {}, events: {}, hidden: false, dataset: {},
            classList: { add: v => names.add(v), remove: v => names.delete(v), toggle: (v, on) => on ? names.add(v) : names.delete(v), contains: v => names.has(v) },
            setAttribute(name, value) { this.attrs[name] = String(value); },
            addEventListener(name, fn) { this.events[name] = fn; }, focus() { if (this.events.focus) this.events.focus(); } };
    }
    const points = Array.from({length: 30}, (_, index) => {
        const point = node();
        point.dataset = { date: `2026-10-${String(index + 1).padStart(2, '0')}`, label: String(index), sales: String(index + 1), revenue: '100 EUR', purchases: '1', renewals: '0', y: '18' };
        const children = { '[data-sales-marker]': node(), '[data-sales-crosshair]': node(), '[data-sales-hit]': node() };
        point.querySelector = selector => children[selector];
        return point;
    });
    const tooltip = node(), svg = node(), chart = node(), previous = node(), next = node();
    const fields = Object.fromEntries(['date', 'sales', 'revenue', 'purchases', 'renewals'].map(name => [`[data-tooltip-${name}]`, node()]));
    tooltip.hidden = true; tooltip.offsetWidth = 244; tooltip.offsetHeight = 190;
    tooltip.querySelector = selector => fields[selector];
    chart.dataset.locale = 'pl'; chart.clientWidth = 350;
    chart.getBoundingClientRect = () => ({left: 0, top: 0});
    svg.getBoundingClientRect = () => ({left: 0, right: 350, top: 30, bottom: 282, width: 350, height: 252});
    svg.contains = value => points.includes(value);
    chart.contains = value => value === svg || points.includes(value) || value === previous || value === next;
    const area = node();
    chart.querySelector = selector => ({'[data-sales-plot]': svg, '[data-sales-tooltip]': tooltip, '[data-sales-prev]': previous, '[data-sales-next]': next, '[data-sales-area]': area})[selector];
    chart.querySelectorAll = selector => selector === '[data-sales-point]' ? points : [];
    const media = {matches: isMobile, addEventListener() {}};
    const listeners = {};
    const document = {activeElement: null, querySelectorAll: selector => selector === '[data-sales-chart]' ? [chart] : [], addEventListener(name, fn) { listeners[name] = fn; }};
    vm.runInNewContext(source, {document, window: {matchMedia: () => media, addEventListener() {}}, Intl, Date, requestAnimationFrame: fn => { fn(); return 1; }, cancelAnimationFrame() {}});
    listeners.DOMContentLoaded();
    const current = () => points.findIndex(point => point.classList.contains('is-active'));
    return {svg, points, tooltip, previous, next, fields, current};
}
let chart = chartHarness(true);
const xForDay = index => 28 + index * (350 - 56) / 29;
chart.svg.events.pointerdown({type: 'pointerdown', pointerType: 'touch', clientX: xForDay(15)});
check(chart.current() === 15, 'A touch selects the intended day');
chart.svg.events.pointermove({type: 'pointermove', pointerType: 'touch', buttons: 1, clientX: xForDay(27)});
check(chart.current() === 15, 'Vertical touch scrolling does not change the selected day');
chart.svg.events.pointermove({type: 'pointermove', pointerType: 'mouse', clientX: xForDay(27)});
chart.next.events.click();
check(chart.current() === 16, 'Moving toward the mobile next button does not first select a different day');
chart.previous.events.click();
check(chart.current() === 15, 'Mobile previous control selects the immediately preceding day');
check(chart.fields['[data-tooltip-sales]'].textContent === '16', 'Tooltip updates its contents after mobile navigation');
chart.svg.events.keydown({key:'End', preventDefault(){}});
check(chart.current() === 29 && chart.next.disabled, 'End selects last day and disables next control');
chart.svg.events.keydown({key:'Home', preventDefault(){}});
check(chart.current() === 0 && chart.previous.disabled, 'Home selects first day and disables previous control');
chart.svg.events.keydown({key:'Escape'});
check(chart.tooltip.hidden, 'Escape hides chart details');
chart = chartHarness(false);
chart.svg.events.pointermove({type:'pointermove', pointerType:'mouse', clientX:xForDay(12)});
check(chart.current() === 12, 'Desktop mouse hovering still selects points');
chart.svg.events.keydown({key:'ArrowRight', preventDefault(){}});
check(chart.current() === 13, 'Keyboard navigation continues from the current hovered day');
console.log('PASS: 10 chart touch/pointer/keyboard/navigation assertions');
