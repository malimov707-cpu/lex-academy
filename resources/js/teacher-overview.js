function svgNode(tag, attributes, label) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const [name, value] of Object.entries(attributes)) {
        element.setAttribute(name, value);
    }
    if (label !== undefined) element.textContent = label;
    return element;
}

function initializeActivityChart(chart) {
    const history = JSON.parse(chart.dataset.days);
    const series = [...chart.querySelectorAll('[data-series]')].map(item => ({
        key: item.dataset.series,
        label: item.textContent.trim(),
    }));
    const period = chart.querySelector('[data-teacher-period]');
    const svg = chart.querySelector('svg.teacher-chart-svg');
    const grid = svg.querySelector('[data-plot-grid]');
    const lines = svg.querySelector('[data-plot-lines]');
    const cursor = svg.querySelector('[data-plot-cursor]');
    const slider = chart.querySelector('[data-teacher-point]');
    const tooltip = chart.querySelector('[data-teacher-tooltip]');
    const empty = chart.querySelector('[data-chart-empty]');
    const months = JSON.parse(chart.dataset.months);
    let days = [];
    let left, right, top, bottom, maximum;
    const x = index => left + (right - left) * index / Math.max(1, days.length - 1);
    const y = count => bottom - (bottom - top) * count / maximum;

    function dateLabel(isoDate, withYear = false) {
        const [year, month, day] = isoDate.split('-');
        return Number(day) + ' ' + months[Number(month) - 1] + (withYear ? ' ' + year : '');
    }

    function selectDay(index, visible) {
        const day = days[index];
        if (!day) return;
        slider.value = index;
        const date = dateLabel(day.date, true);
        slider.setAttribute('aria-valuetext', [date, ...series.map(({ key, label }) => label + ' ' + day[key])].join(', '));
        tooltip.hidden = !visible;
        cursor.replaceChildren();
        if (!visible) return;

        const position = x(index);
        cursor.append(svgNode('line', {
            x1: position, x2: position, y1: top, y2: bottom, stroke: 'var(--teacher-muted)', 'stroke-dasharray': '3 4',
        }));
        tooltip.querySelector('strong').textContent = date;
        for (const { key } of series) {
            tooltip.querySelector('[data-point-value="' + key + '"]').textContent = day[key];
            cursor.append(svgNode('circle', {
                cx: position, cy: y(day[key]), r: 3.5, fill: 'var(--teacher-paper)', stroke: 'var(--teacher-series)',
                'stroke-width': 2, class: 'teacher-series-' + key,
            }));
        }
        const target = position + tooltip.offsetWidth + 14 < svg.clientWidth ? position + 12 : position - tooltip.offsetWidth - 12;
        tooltip.style.left = Math.max(0, target) + 'px';
        tooltip.style.top = top + 'px';
    }

    function draw() {
        days = history.slice(-Number(period.value));
        if (!days.length || !svg.clientWidth) return;
        const width = svg.clientWidth;
        const height = svg.clientHeight;
        const peak = Math.max(...days.flatMap(day => series.map(({ key }) => day[key])));
        const stepBase = Math.max(1, peak / 4);
        const magnitude = 10 ** Math.floor(Math.log10(stepBase));
        const step = ([1, 2, 5, 10].find(n => n * magnitude >= stepBase)) * magnitude;
        maximum = Math.max(1, Math.ceil(peak / step)) * step;
        left = Math.max(25, String(maximum).length * 9 + 12);
        right = width - 12;
        top = 12;
        bottom = height - 25;
        svg.setAttribute('viewBox', [0, 0, width, height].join(' '));
        grid.replaceChildren();
        for (let value = 0; value <= maximum; value += step) {
            const row = y(value);
            grid.append(
                svgNode('line', { x1: left, x2: right, y1: row, y2: row, class: 'teacher-chart-gridline' }),
                svgNode('text', { x: left - 8, y: row, 'dominant-baseline': 'middle', 'text-anchor': 'end' }, value),
            );
        }
        const intervals = Math.min(width < 450 ? 3 : 4, days.length - 1);
        for (let tick = 0; tick <= intervals; tick++) {
            const index = Math.round(tick * (days.length - 1) / Math.max(intervals, 1));
            const position = x(index);
            grid.append(
                svgNode('line', { x1: position, x2: position, y1: top, y2: bottom, class: 'teacher-chart-gridline' }),
                svgNode('text', { x: position, y: height - 5, 'text-anchor': tick === 0 ? 'start' : tick === intervals ? 'end' : 'middle' }, dateLabel(days[index].date)),
            );
        }
        lines.replaceChildren();
        for (const { key } of series) {
            const path = days.map((day, index) => (index === 0 ? 'M' : 'L') + x(index) + ',' + y(day[key])).join(' ');
            lines.append(
                svgNode('path', { d: path + ' L' + right + ',' + bottom + ' L' + left + ',' + bottom + ' Z', fill: 'url(#teacher-fill-' + key + ')' }),
                svgNode('path', { d: path, class: 'teacher-chart-line teacher-series-' + key }),
            );
        }
        empty.hidden = peak > 0;
        slider.max = days.length - 1;
        if (document.activeElement === slider) {
            selectDay(Math.min(Number(slider.value), days.length - 1), true);
        } else {
            const lastActivity = days.findLastIndex(day => series.some(({ key }) => day[key] > 0));
            selectDay(lastActivity < 0 ? days.length - 1 : lastActivity, lastActivity >= 0 && width >= 620);
        }
    }

    period.addEventListener('change', draw);
    slider.addEventListener('pointermove', event => {
        const ratio = (event.clientX - svg.getBoundingClientRect().left - left) / (right - left);
        selectDay(Math.round(Math.max(0, Math.min(1, ratio)) * (days.length - 1)), true);
    });
    slider.addEventListener('pointerleave', () => selectDay(Number(slider.value), slider.matches(':focus-visible')));
    slider.addEventListener('input', () => selectDay(Number(slider.value), true));
    slider.addEventListener('focus', () => selectDay(Number(slider.value), true));
    slider.addEventListener('blur', () => selectDay(Number(slider.value), false));
    draw();
    new ResizeObserver(draw).observe(svg.parentElement);
}

const dashboard = document.querySelector('[data-teacher-dashboard]');
if (dashboard) {
    const slides = [...dashboard.querySelectorAll('[data-teacher-slide]')];
    const buttons = [...dashboard.querySelectorAll('[data-teacher-slide-button]')];
    buttons.forEach((button, index) => button.addEventListener('click', () => {
        slides.forEach((slide, position) => { slide.hidden = position !== index; });
        buttons.forEach((dot, position) => dot.setAttribute('aria-pressed', String(position === index)));
    }));
    initializeActivityChart(dashboard.querySelector('[data-teacher-chart]'));
    const activity = dashboard.querySelector('[data-teacher-activity-dialog]');
    dashboard.querySelector('[data-teacher-activity-open]')?.addEventListener('click', () => activity.showModal());
    activity?.addEventListener('click', event => {
        if (event.target !== activity) return;
        const box = activity.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
            activity.close();
        }
    });
}
