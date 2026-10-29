import { BarController, BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const money = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
const moneyExact = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

/**
 * Renders every <canvas data-chart="…"> from its JSON payload:
 * { labels: [...], series: [{ label, data }], format: 'money' | 'count', stacked }.
 * Series colors follow their fixed slot (series order is stable per entity),
 * and everything is re-themed when the OS switches light/dark.
 */
export function renderCharts() {
    const charts = [];

    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const spec = JSON.parse(canvas.dataset.chart);
        charts.push({ canvas, spec, chart: new Chart(canvas, config(spec)) });
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        charts.forEach((entry) => {
            entry.chart.destroy();
            entry.chart = new Chart(entry.canvas, config(entry.spec));
        });
    });
}

function config(spec) {
    const isMoney = spec.format === 'money';
    const surface = css('--surface');
    const multi = spec.series.length > 1;

    return {
        type: 'bar',
        data: {
            labels: spec.labels,
            datasets: spec.series.map((s, i) => ({
                label: s.label,
                data: s.data,
                backgroundColor: css(`--series-${Math.min(i, 7) + 1}`),
                // 2px surface gap between adjacent fills.
                borderColor: surface,
                borderWidth: spec.stacked ? { top: 2 } : 0,
                borderRadius: 4,
                borderSkipped: 'start',
                maxBarThickness: spec.stacked ? 28 : 14,
                categoryPercentage: 0.7,
                barPercentage: spec.stacked ? 0.9 : 0.85,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: multi,
                    position: 'top',
                    align: 'start',
                    labels: { color: css('--ink-2'), boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2 },
                },
                tooltip: {
                    backgroundColor: css('--surface'),
                    titleColor: css('--ink'),
                    bodyColor: css('--ink-2'),
                    borderColor: css('--line'),
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${isMoney ? moneyExact.format(ctx.parsed.y) : ctx.parsed.y}`,
                        footer: (items) => {
                            if (!spec.stacked || items.length < 2) return '';
                            const total = items.reduce((sum, it) => sum + it.parsed.y, 0);
                            return `Total: ${isMoney ? moneyExact.format(total) : total}`;
                        },
                    },
                    footerColor: css('--ink'),
                },
            },
            scales: {
                x: {
                    stacked: !!spec.stacked,
                    grid: { display: false },
                    border: { color: css('--axis') },
                    ticks: { color: css('--muted'), maxRotation: 0, autoSkipPadding: 12 },
                },
                y: {
                    stacked: !!spec.stacked,
                    beginAtZero: true,
                    grid: { color: css('--line') },
                    border: { display: false },
                    ticks: {
                        color: css('--muted'),
                        precision: 0,
                        maxTicksLimit: 5,
                        callback: (v) => (isMoney ? money.format(v) : v),
                    },
                },
            },
        },
    };
}
