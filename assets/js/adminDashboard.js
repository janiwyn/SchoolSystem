// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeTuitionChart();
    initializeAdmissionsChart();
    initializeExpensesChart();
});

function initializeTuitionChart() {
    const months = window.chartMonths;
    const expectedData = window.expectedData;
    const receivedData = window.receivedData;
    const balanceData = window.balanceData; // Unpaid balance data

    const ctx = document.getElementById('tuitionChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Expected Tuition',
                    data: expectedData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                },
                {
                    label: 'Received Tuition',
                    data: receivedData,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#27ae60',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                },
                {
                    label: 'Unpaid Balance',
                    data: balanceData,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#e74c3c',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                }
            ]
        },
        options: getChartOptions('currency')
    });
}

function initializeAdmissionsChart() {
    const months = window.chartMonths;
    const admittedData = window.admittedData;

    const ctx2 = document.getElementById('admissionsChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Students Admitted',
                    data: admittedData,
                    borderColor: '#9b59b6',
                    backgroundColor: 'rgba(155, 89, 182, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#9b59b6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                }
            ]
        },
        options: getChartOptions('number')
    });
}

function initializeExpensesChart() {
    const months = window.chartMonths;
    const expensesData = window.expensesData;

    const ctx3 = document.getElementById('expensesChart').getContext('2d');
    new Chart(ctx3, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Total Expenses',
                    data: expensesData,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#e74c3c',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                }
            ]
        },
        options: getChartOptions('currency')
    });
}

function getChartOptions(type) {
    return {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: {
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                },
                borderColor: '#ddd',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        if (type === 'currency') {
                            return context.dataset.label + ': ' + new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(context.parsed.y);
                        } else {
                            return context.dataset.label + ': ' + context.parsed.y + ' students';
                        }
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (type === 'currency') {
                            return new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(value);
                        } else {
                            return Math.round(value);
                        }
                    },
                    font: {
                        size: 12
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    font: {
                        size: 12
                    }
                }
            }
        }
    };
}
