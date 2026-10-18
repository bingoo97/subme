document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    // Use CSS-pixel geometry so zoom, responsive SVG sizing and long currency labels stay consistent.
    function salesTooltipPosition(plot, size, points, selected) {
        var point = points[selected];
        var gap = 16;
        var minLeft = plot.left + 8;
        var maxRight = plot.right - 8;
        var minTop = plot.top + 8;
        // Keep the date axis and the horizontal pointer path to neighbouring days clear.
        var maxBottom = plot.top + plot.height * 228 / 252;
        var maxTop = maxBottom - size.height;
        if (maxTop < minTop || size.width > maxRight - minLeft) return null;
        function clamp(value, min, max) { return Math.max(min, Math.min(value, max)); }
        function overlaps(box, other, padding) {
            return other.x > box.left - padding && other.x < box.left + size.width + padding
                && other.y > box.top - padding && other.y < box.top + size.height + padding;
        }
        var candidates = [];
        function add(left, top, side) {
            if (left < minLeft || left + size.width > maxRight) return;
            var box = {left: left, top: clamp(top, minTop, maxTop), side: side};
            if (overlaps(box, point, gap)) return;
            var score = 0;
            points.forEach(function (other, index) {
                if (overlaps(box, other, 9)) {
                    // The next/previous point must remain visible even with sales on every day.
                    score += Math.abs(index - selected) <= 1 ? 1000 : (other.sales > 0 ? 20 : 4);
                }
            });
            score += Math.abs(box.top + size.height / 2 - point.y) / 1000;
            candidates.push({box: box, score: score});
        }
        if (point.sales === 0) {
            // A zero day sits at the baseline: prefer the familiar position above it.
            var zeroLeft = clamp(point.x - size.width / 2, minLeft, maxRight - size.width);
            if (point.y - gap - size.height >= minTop) add(zeroLeft, point.y - gap - size.height, 'above');
        }
        if (!candidates.length || candidates[0].score >= 1000) {
            var step = points.length > 1 ? Math.abs(points[1].x - points[0].x) : 0;
            var sideGap = Math.max(gap + 1, step + 10);
            var tops = [point.y - size.height / 2, point.y - size.height - gap, point.y + gap, minTop, maxTop];
            var sides = point.x < (plot.left + plot.right) / 2 ? ['right', 'left'] : ['left', 'right'];
            sides.forEach(function (side) {
                var left = side === 'right' ? point.x + sideGap : point.x - sideGap - size.width;
                tops.forEach(function (top) { add(left, top, side); });
            });
        }
        candidates.sort(function (a, b) { return a.score - b.score; });
        // On a narrow/dense plot use the existing card below the SVG rather than cover selectable days.
        return candidates.length && candidates[0].score < 1000 ? candidates[0].box : null;
    }
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
            if (tooltip.hidden) return;
            tooltip.classList.remove('is-below-plot');
            tooltip.style.left = '';
            tooltip.style.top = '';
            if (mobile.matches) return;
            var bounds = svg.getBoundingClientRect();
            var parent = chart.getBoundingClientRect();
            var plot = {left: bounds.left - parent.left, right: bounds.right - parent.left,
                top: bounds.top - parent.top, height: bounds.height};
            var positions = points.map(function (point, index) {
                return {x: plot.left + pointX(index) * bounds.width / plotWidth,
                    y: plot.top + Number(point.dataset.y) * bounds.height / 252,
                    sales: Number(point.dataset.sales)};
            });
            var position = salesTooltipPosition(plot, {width: tooltip.offsetWidth, height: tooltip.offsetHeight}, positions, selected);
            tooltip.classList.toggle('is-below-plot', !position);
            if (position) {
                tooltip.style.left = position.left + 'px';
                tooltip.style.top = position.top + 'px';
            }
        }
        function select(index, focus) {
            index = Math.max(0, Math.min(points.length - 1, index));
            if (selected === index && !tooltip.hidden) {
                if (focus) active.focus({preventScroll: true});
                return;
            }
            selected = index;
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
            if (event.type === 'pointermove' && (mobile.matches || event.pointerType !== 'mouse')) return;
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
        if (mobile.addEventListener) mobile.addEventListener('change', scheduleResize);
        scheduleResize();
    });
});
