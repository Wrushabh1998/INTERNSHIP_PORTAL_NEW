/**
 * InternTrack Pro — Attendance Calendar
 * Renders a color-coded monthly calendar
 */

const AttendanceCalendar = (function () {
    'use strict';

    let currentYear  = new Date().getFullYear();
    let currentMonth = new Date().getMonth(); // 0-based
    let attendanceData = {};  // { 'YYYY-MM-DD': 'Present'|'Absent'|... }

    const STATUS_CLASS = {
        'Present':  'present',
        'Absent':   'absent',
        'Leave':    'leave',
        'Holiday':  'holiday',
        'Half Day': 'half-day',
        'Late':     'late',
    };

    function init(containerId, data) {
        attendanceData = data || {};
        const container = document.getElementById(containerId);
        if (!container) return;
        render(container);
    }

    function render(container) {
        container.innerHTML = buildCalendar();
        container.querySelector('.cal-prev')?.addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            render(container);
            if (window.fetchCalendarData) fetchCalendarData(currentYear, currentMonth + 1);
        });
        container.querySelector('.cal-next')?.addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            render(container);
            if (window.fetchCalendarData) fetchCalendarData(currentYear, currentMonth + 1);
        });
    }

    function buildCalendar() {
        const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const today  = new Date();
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

        let html = `<div class="att-calendar">
            <div class="cal-header">
                <button class="cal-nav-btn cal-prev">&#8249;</button>
                <span class="cal-title">${MONTHS[currentMonth]} ${currentYear}</span>
                <button class="cal-nav-btn cal-next">&#8250;</button>
            </div>
            <div class="cal-grid">`;

        // Day name headers
        DAYS.forEach(d => { html += `<div class="cal-day-name">${d}</div>`; });

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            html += '<div class="cal-day empty"></div>';
        }

        // Day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const status  = attendanceData[dateStr];
            const cls     = STATUS_CLASS[status] || '';
            const isToday = (day === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear());
            const isFuture = new Date(currentYear, currentMonth, day) > today;

            let dayClass = 'cal-day';
            if (cls) dayClass += ' ' + cls;
            if (isToday) dayClass += ' today';
            if (isFuture && !cls) dayClass += ' future';

            const title = status ? ` title="${status}"` : '';
            html += `<div class="${dayClass}"${title}>${day}</div>`;
        }

        html += `</div>
            <div class="cal-legend">
                <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#dcfce7"></span>Present</span>
                <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#fee2e2"></span>Absent</span>
                <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#fef9c3"></span>Leave</span>
                <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#dbeafe"></span>Holiday</span>
                <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#ffedd5"></span>Half Day</span>
            </div>
        </div>`;

        return html;
    }

    function updateData(data) {
        attendanceData = data;
    }

    return { init, updateData, render };
})();
