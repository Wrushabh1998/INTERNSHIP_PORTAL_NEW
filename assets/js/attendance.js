/**
 * InternTrack Pro — Attendance Punch Logic (Direct Punch)
 */

const AttendanceCamera = (function () {
    'use strict';

    function init() {
        document.getElementById('btn-punch-in')?.addEventListener('click', () => submitPunch('punch_in'));
        document.getElementById('btn-punch-out')?.addEventListener('click', () => submitPunch('punch_out'));
        document.getElementById('btn-lunch-start')?.addEventListener('click', () => submitPunch('lunch_start'));
        document.getElementById('btn-lunch-end')?.addEventListener('click', () => submitPunch('lunch_end'));
    }

    function submitPunch(type) {
        const btn = document.getElementById('btn-' + type.replace('_', '-'));
        const origHTML = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>';
        }

        // Support both the new #att-csrf id and old name-based hidden input
        const csrfToken = document.getElementById('att-csrf')?.value
                       || document.querySelector('input[name="_csrf_token"]')?.value
                       || '';
        const formData  = new FormData();
        formData.append('action',      type);
        formData.append('image',       '');
        formData.append('_csrf_token', csrfToken);
        formData.append('user_agent',  navigator.userAgent);

        // Capture location only at the time of attendance marking (punch_in / punch_out)
        if ((type === 'punch_in' || type === 'punch_out') && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    formData.append('lat', pos.coords.latitude.toFixed(6));
                    formData.append('lng', pos.coords.longitude.toFixed(6));
                    doSubmit(formData, type, btn, origHTML);
                },
                err => {
                    console.warn("Geolocation fetch failed: ", err);
                    doSubmit(formData, type, btn, origHTML);
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        } else {
            doSubmit(formData, type, btn, origHTML);
        }
    }

    function doSubmit(formData, type, btn, origHTML) {
        fetch((window.BASE_URL || '') + '/ajax/attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Toast.show('success', punchLabel(type), res.message);
                updatePunchStatus(type, res.time);
                // Reload after 1.5s to show updated status
                setTimeout(() => location.reload(), 1500);
            } else {
                Toast.show('error', 'Failed', res.message);
                if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
            }
        })
        .catch(() => {
            Toast.show('error', 'Network Error', 'Could not connect to server.');
            if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
        });
    }

    function punchLabel(type) {
        const labels = {
            punch_in:    'Punched In',
            punch_out:   'Punched Out',
            lunch_start: 'Lunch Started',
            lunch_end:   'Lunch Ended'
        };
        return labels[type] || 'Recorded';
    }

    function updatePunchStatus(type, time) {
        const el = document.getElementById('status-' + type.replace('_', '-'));
        if (el) { el.textContent = time; el.parentElement.classList.add('done'); }
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', function () {
    // Check if punch buttons exist
    if (document.querySelector('.btn-punch')) {
        AttendanceCamera.init();
    }
});
