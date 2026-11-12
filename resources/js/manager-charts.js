import {
    Chart, DoughnutController, BarController, ArcElement, BarElement,
    CategoryScale, LinearScale, Tooltip, Legend,
} from 'chart.js';

Chart.register(DoughnutController, BarController, ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const source = document.querySelector('[data-overdue-chart-data]');

if (source) {
    const data = JSON.parse(source.textContent);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const common = { responsive: true, maintainAspectRatio: false, animation: reducedMotion ? false : undefined };

    new Chart(document.getElementById('overdue-students-chart'), {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{ data: data.students, backgroundColor: data.colors, borderWidth: 3, borderColor: '#ffffff' }],
        },
        options: {
            ...common,
            cutout: '68%',
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } } },
        },
    });

    new Chart(document.getElementById('overdue-copies-chart'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{ data: data.copies, backgroundColor: data.colors, borderRadius: 5, maxBarThickness: 54 }],
        },
        options: {
            ...common,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
        },
    });
}
