document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    document.querySelectorAll('[data-sales-chart]').forEach(function (chart) {
        var svg = chart.querySelector('[data-sales-plot]');
        var points = Array.from(chart.querySelectorAll('[data-sales-point]'));
        var tooltip = chart.querySelector('[data-sales-tooltip]');
        if (!svg || !points.length || !tooltip) return;
        var selected = points.length - 1;
        var plotWidth = 640;
        var active = points[selected];
        var frame = 0;
        var mobile = window.matchMedia('(max-width:575px)');
        var dateFormatter;
        try {
            dateFormatter = new Intl.DateTimeFormat(chart.dataset.locale === 'pl' ? 'pl-PL' : 'en-GB', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });
        } catch (error) { dateFormatter = null; }
        var fields = {};
        ['date', 'revenue', 'sales', 'purchases', 'renewals'].forEach(function (name) {
            fields[name] = tooltip.querySelector('[data-tooltip-' + name + ']');
        });
        var prev = chart.querySelector('[data-sales-prev]');
        var next = chart.querySelector('[data-sales-next]');
        function pointX(index) {
            return points.length > 1 ? 28 + index * (plotWidth - 56) / (points.length - 1) : plotWidth / 2;
        }
        function placeTooltip() {
            if (tooltip.hidden || mobile.matches) return;
            var bounds = svg.getBoundingClientRect();
            var parent = chart.getBoundingClientRect();
            var x = pointX(selected) * bounds.width / plotWidth;
            var y = Number(points[selected].dataset.y) * bounds.height / 252;
            var top = bounds.top - parent.top + y - tooltip.offsetHeight - 14;
            if (top < 30) top = bounds.top - parent.top + y + 16;
            var minTop = bounds.top - parent.top + 8;
            var maxTop = bounds.bottom - parent.top - tooltip.offsetHeight - 6;
            tooltip.style.left = Math.max(8, Math.min(x - tooltip.offsetWidth / 2, chart.clientWidth - tooltip.offsetWidth - 8)) + 'px';
            tooltip.style.top = Math.max(minTop, Math.min(top, Math.max(minTop, maxTop))) + 'px';
        }
        function select(index, focus) {
            selected = Math.max(0, Math.min(points.length - 1, index));
            if (active) {
                active.classList.remove('is-active');
                active.setAttribute('tabindex', '-1');
                active.setAttribute('aria-describedby', 'dashboardSalesChartHint');
            }
            active = points[selected];
            active.classList.add('is-active');
            active.setAttribute('tabindex', '0');
            var data = active.dataset;
            fields.date.textContent = dateFormatter ? dateFormatter.format(new Date(data.date + 'T12:00:00')) : data.label;
            fields.date.setAttribute('datetime', data.date);
            ['revenue', 'sales', 'purchases', 'renewals'].forEach(function (name) { fields[name].textContent = data[name]; });
            tooltip.hidden = false;
            active.setAttribute('aria-describedby', 'dashboardSalesChartHint dashboardSalesChartTooltip');
            if (prev) prev.disabled = selected === 0;
            if (next) next.disabled = selected === points.length - 1;
            placeTooltip();
            if (focus) active.focus({preventScroll: true});
        }
        function hide() {
            tooltip.hidden = true;
            if (active) {
                active.classList.remove('is-active');
                active.setAttribute('aria-describedby', 'dashboardSalesChartHint');
            }
        }
        function resize() {
            var width = Math.round(svg.getBoundingClientRect().width);
            if (!width || width === plotWidth) { placeTooltip(); return; }
            plotWidth = Math.max(180, width);
            svg.setAttribute('viewBox', '0 0 ' + plotWidth + ' 252');
            var path = [];
            points.forEach(function (point, index) {
                var x = pointX(index);
                var y = Number(point.dataset.y);
                path.push((index ? 'L ' : 'M ') + x.toFixed(2) + ' ' + y.toFixed(2));
                point.querySelector('[data-sales-marker]').setAttribute('cx', x.toFixed(2));
                var line = point.querySelector('[data-sales-crosshair]');
                line.setAttribute('x1', x); line.setAttribute('x2', x);
                var hit = point.querySelector('[data-sales-hit]');
                var step = (plotWidth - 56) / Math.max(1, points.length - 1);
                hit.setAttribute('x', x - step / 2); hit.setAttribute('width', Math.max(1, step));
            });
            chart.querySelectorAll('[data-sales-line]').forEach(function (line) { line.setAttribute('d', path.join(' ')); });
            chart.querySelector('[data-sales-area]').setAttribute('d', path.join(' ') + ' L ' + pointX(points.length - 1).toFixed(2) + ' 222 L ' + pointX(0).toFixed(2) + ' 222 Z');
            chart.querySelectorAll('[data-sales-grid]').forEach(function (line) { line.setAttribute('x2', plotWidth - 28); });
            var stepLabel = Math.ceil((points.length - 1) / Math.max(2, Math.floor((plotWidth - 56) / 76)));
            chart.querySelectorAll('[data-sales-axis-label]').forEach(function (label) {
                var index = Number(label.dataset.index);
                label.setAttribute('x', pointX(index));
                label.style.display = index === 0 || index === points.length - 1 || (index % stepLabel === 0 && index < points.length - 1 - stepLabel / 2) ? '' : 'none';
            });
            placeTooltip();
        }
        function scheduleResize() {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(resize);
        }
        function pointerSelect(event) {
            if (event.type === 'pointermove' && event.pointerType !== 'mouse' && !event.buttons) return;
            var bounds = svg.getBoundingClientRect();
            var x = (event.clientX - bounds.left) * plotWidth / bounds.width;
            select(Math.round((x - 28) / Math.max(1, plotWidth - 56) * (points.length - 1)), false);
        }
        svg.addEventListener('pointermove', pointerSelect);
        svg.addEventListener('pointerdown', pointerSelect);
        svg.addEventListener('pointerleave', function (event) {
            if (event.pointerType === 'mouse' && !svg.contains(document.activeElement)) hide();
        });
        points.forEach(function (point, index) {
            point.addEventListener('focus', function () { select(index, false); });
        });
        svg.addEventListener('focusout', function (event) { if (!chart.contains(event.relatedTarget)) hide(); });
        svg.addEventListener('keydown', function (event) {
            var index = selected;
            if (event.key === 'ArrowLeft') index--;
            else if (event.key === 'ArrowRight') index++;
            else if (event.key === 'Home') index = 0;
            else if (event.key === 'End') index = points.length - 1;
            else if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(selected, false); return; }
            else if (event.key === 'Escape') { hide(); return; }
            else return;
            event.preventDefault(); select(index, true);
        });
        if (prev) prev.addEventListener('click', function () { select(selected - 1, false); });
        if (next) next.addEventListener('click', function () { select(selected + 1, false); });
        document.addEventListener('pointerdown', function (event) { if (!chart.contains(event.target)) hide(); });
        document.querySelectorAll('.admin-dashboard-tabs [data-bs-toggle="tab"]').forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function () { hide(); scheduleResize(); });
        });
        if (window.ResizeObserver) new ResizeObserver(scheduleResize).observe(svg);
        else window.addEventListener('resize', scheduleResize);
        scheduleResize();
    });
});
