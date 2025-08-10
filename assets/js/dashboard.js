// Dashboard-specific JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts if data is available
    if (typeof window.dashboardData !== 'undefined') {
        initializeCharts();
    }

    // Auto-refresh dashboard data every 5 minutes
    setInterval(refreshDashboardStats, 5 * 60 * 1000);

    // Initialize real-time updates (if WebSocket or similar is available)
    // initializeRealTimeUpdates();
});

function initializeCharts() {
    const taskCounts = window.dashboardData.taskCounts;
    
    // Task Status Chart (Bar Chart)
    const statusCtx = document.getElementById('taskStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Rejected'],
                datasets: [{
                    label: 'Number of Tasks',
                    data: [
                        taskCounts['Pending'] || 0,
                        taskCounts['In Progress'] || 0,
                        taskCounts['Completed'] || 0,
                        taskCounts['Rejected'] || 0
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#17a2b8',
                        '#28a745',
                        '#dc3545'
                    ],
                    borderColor: [
                        '#e0a800',
                        '#138496',
                        '#1e7e34',
                        '#c82333'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 4
                    }
                }
            }
        });
    }

    // Task Distribution Chart (Doughnut Chart)
    const distributionCtx = document.getElementById('taskDistributionChart');
    if (distributionCtx) {
        const totalTasks = Object.values(taskCounts).reduce((sum, count) => sum + count, 0);
        
        if (totalTasks > 0) {
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed', 'Rejected'],
                    datasets: [{
                        data: [
                            taskCounts['Pending'] || 0,
                            taskCounts['In Progress'] || 0,
                            taskCounts['Completed'] || 0,
                            taskCounts['Rejected'] || 0
                        ],
                        backgroundColor: [
                            '#ffc107',
                            '#17a2b8',
                            '#28a745',
                            '#dc3545'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = totalTasks > 0 ? ((value / totalTasks) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        } else {
            // Show "No data" message
            distributionCtx.style.display = 'none';
            const noDataMsg = document.createElement('div');
            noDataMsg.className = 'text-center text-muted py-4';
            noDataMsg.innerHTML = '<i class="fas fa-chart-pie fa-2x mb-2"></i><br>No task data available';
            distributionCtx.parentElement.appendChild(noDataMsg);
        }
    }
}

function refreshDashboardStats() {
    // Make AJAX request to refresh dashboard statistics
    fetch('/ajax/get-dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatistics(data.stats);
            }
        })
        .catch(error => {
            console.error('Failed to refresh dashboard stats:', error);
        });
}

function updateStatistics(stats) {
    // Update stat cards
    const statElements = {
        'total_projects': document.querySelector('.total-projects .h5'),
        'active_projects': document.querySelector('.active-projects .h5'),
        'total_tasks': document.querySelector('.total-tasks .h5'),
        'completed_tasks': document.querySelector('.completed-tasks .h5')
    };

    Object.keys(statElements).forEach(key => {
        const element = statElements[key];
        if (element && stats[key] !== undefined) {
            // Animate the number change
            animateNumber(element, parseInt(element.textContent), stats[key]);
        }
    });
}

function animateNumber(element, start, end, duration = 1000) {
    if (start === end) return;
    
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            element.textContent = end;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 16);
}

// Project progress tracking
function updateProjectProgress() {
    const progressBars = document.querySelectorAll('.progress-bar[data-project-id]');
    
    progressBars.forEach(bar => {
        const projectId = bar.getAttribute('data-project-id');
        
        fetch(`/ajax/get-project-progress.php?id=${projectId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const percentage = data.progress;
                    bar.style.width = `${percentage}%`;
                    bar.textContent = `${percentage}%`;
                    
                    // Update progress color based on completion
                    bar.className = 'progress-bar';
                    if (percentage === 100) {
                        bar.classList.add('bg-success');
                    } else if (percentage >= 75) {
                        bar.classList.add('bg-info');
                    } else if (percentage >= 50) {
                        bar.classList.add('bg-primary');
                    } else {
                        bar.classList.add('bg-warning');
                    }
                }
            })
            .catch(error => {
                console.error(`Failed to update progress for project ${projectId}:`, error);
            });
    });
}

// Task status quick update
function quickUpdateTaskStatus(taskId, newStatus) {
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('status', newStatus);
    
    fetch('/ajax/update-task-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            HousekeepPM.showAlert('Task status updated successfully!', 'success');
            // Refresh the dashboard
            location.reload();
        } else {
            HousekeepPM.showAlert(data.message || 'Failed to update task status', 'danger');
        }
    })
    .catch(error => {
        console.error('Error updating task status:', error);
        HousekeepPM.showAlert('An error occurred while updating the task status', 'danger');
    });
}

// Activity feed updates
function loadRecentActivity() {
    fetch('/ajax/get-recent-activity.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.activities) {
                updateActivityFeed(data.activities);
            }
        })
        .catch(error => {
            console.error('Failed to load recent activity:', error);
        });
}

function updateActivityFeed(activities) {
    const activityContainer = document.querySelector('#recent-activity-list');
    if (!activityContainer) return;
    
    activityContainer.innerHTML = '';
    
    if (activities.length === 0) {
        activityContainer.innerHTML = '<p class="text-muted text-center py-3">No recent activity.</p>';
        return;
    }
    
    activities.forEach(activity => {
        const activityElement = document.createElement('div');
        activityElement.className = 'd-flex align-items-start mb-3';
        activityElement.innerHTML = `
            <div class="flex-shrink-0">
                <i class="fas fa-circle text-primary" style="font-size: 0.5rem; margin-top: 0.5rem;"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <p class="mb-1"><strong>${escapeHtml(activity.user_name)}</strong> ${escapeHtml(activity.description)}</p>
                <small class="text-muted">${escapeHtml(activity.project_name)} • ${formatDateTime(activity.created_at)}</small>
            </div>
        `;
        activityContainer.appendChild(activityElement);
    });
}

// Utility functions for dashboard
function formatDateTime(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-US', options);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Keyboard shortcuts for dashboard
document.addEventListener('keydown', function(e) {
    // Alt + D for Dashboard
    if (e.altKey && e.key === 'd') {
        e.preventDefault();
        window.location.href = '/dashboard.php';
    }
    
    // Alt + P for Projects
    if (e.altKey && e.key === 'p') {
        e.preventDefault();
        window.location.href = '/projects/';
    }
    
    // Alt + T for Tasks
    if (e.altKey && e.key === 't') {
        e.preventDefault();
        window.location.href = '/tasks/';
    }
});

// Export dashboard functions
window.Dashboard = {
    refreshDashboardStats,
    updateProjectProgress,
    quickUpdateTaskStatus,
    loadRecentActivity
};
