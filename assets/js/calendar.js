(function () {
    const state = {
        currentDate: new Date(),
        events: [],
        editingId: null,
        nextId: 1,
        exportType: 'image' // 'image' or 'pdf'
    };

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    function $(id) {
        return document.getElementById(id);
    }

    function formatDate(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) return '';
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function parseDate(str) {
        if (!str) return null;
        // Parse as local date (not UTC)
        const parts = str.split('-');
        if (parts.length !== 3) return null;
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1; // months are 0-indexed
        const d = parseInt(parts[2], 10);
        const date = new Date(y, m, d);
        return isNaN(date.getTime()) ? null : date;
    }

    function renderCalendar() {
        const grid = $('calendarGrid');
        const monthTitle = $('calendarMonthTitle');
        const monthLabel = $('calendarMonthLabel');
        if (!grid || !monthTitle || !monthLabel) return;

        grid.innerHTML = '';

        const year = state.currentDate.getFullYear();
        const month = state.currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const todayStr = formatDate(new Date());

        const monthName = state.currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
        monthTitle.textContent = monthName;
        monthLabel.textContent = `Month of ${monthName}`;

        // Headers
        dayNames.forEach(d => {
            const h = document.createElement('div');
            h.className = 'calendar-day-header';
            h.textContent = d;
            grid.appendChild(h);
        });

        // Leading blanks
        const startWeekday = firstDay.getDay();
        for (let i = 0; i < startWeekday; i++) {
            const cell = document.createElement('div');
            cell.className = 'calendar-day-cell other-month';
            grid.appendChild(cell);
        }

        // Days
        const daysInMonth = lastDay.getDate();
        for (let day = 1; day <= daysInMonth; day++) {
            const cellDate = new Date(year, month, day);
            const cellDateStr = formatDate(cellDate);

            const cell = document.createElement('div');
            cell.className = 'calendar-day-cell';
            if (cellDateStr === todayStr) {
                cell.classList.add('today');
            }

            const num = document.createElement('div');
            num.className = 'calendar-day-number';
            num.textContent = day;
            cell.appendChild(num);

            // Events for this day
            state.events.forEach(ev => {
                const start = parseDate(ev.start);
                const end = parseDate(ev.end || ev.start);
                if (!start || !end) return;

                // Compare dates (ignore time)
                const cellTime = cellDate.getTime();
                const startTime = start.getTime();
                const endTime = end.getTime();

                if (cellTime >= startTime && cellTime <= endTime) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'calendar-event';

                    const span = document.createElement('span');
                    span.textContent = ev.title;
                    span.style.cursor = 'pointer';
                    span.title = 'Click to edit';

                    // Apply badge styling based on category
                    if (ev.category === 'public') {
                        span.className = 'calendar-badge badge-public';
                        span.style.backgroundColor = '#dc3545'; // RED for public holidays
                        span.style.color = '#fff';
                    } else if (ev.category === 'special') {
                        span.className = 'calendar-badge badge-special';
                        span.style.backgroundColor = '#28a745'; // GREEN for special days
                        span.style.color = '#fff';
                    } else if (ev.category === 'other') {
                        if (ev.showBadge) {
                            span.className = 'calendar-badge';
                            span.style.backgroundColor = ev.color || '#6c757d';
                            span.style.color = '#fff';
                        } else {
                            // No badge - just plain text
                            span.className = 'calendar-event-text';
                        }
                    } else {
                        span.className = 'calendar-badge badge-other';
                        span.style.backgroundColor = '#6c757d';
                        span.style.color = '#fff';
                    }

                    // Click to edit
                    span.addEventListener('click', function(e) {
                        e.stopPropagation();
                        editEvent(ev.id);
                    });

                    // Delete button
                    const delBtn = document.createElement('span');
                    delBtn.className = 'calendar-event-delete';
                    delBtn.innerHTML = '&times;';
                    delBtn.title = 'Remove event';
                    delBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (confirm('Remove this event?')) {
                            deleteEvent(ev.id);
                        }
                    });

                    wrapper.appendChild(span);
                    wrapper.appendChild(delBtn);
                    cell.appendChild(wrapper);
                }
            });

            grid.appendChild(cell);
        }

        console.log('Calendar rendered for', monthName, 'with', state.events.length, 'events');
    }

    // NEW: Load events from server
    async function loadEvents() {
        try {
            const res = await fetch('api.php?action=load');
            const data = await res.json();
            if (data.success) {
                state.events = data.events.map(ev => ({
                    id: ev.id,
                    title: ev.title,
                    category: ev.category,
                    start: ev.start_date,
                    end: ev.end_date,
                    showBadge: !!ev.show_badge,
                    color: ev.badge_color || '#6c757d'
                }));
                renderCalendar();
            }
        } catch (err) {
            console.error('Failed to load events', err);
        }
    }

    // NEW: Save event to server
    async function saveEventToServer(ev) {
        try {
            const res = await fetch('api.php?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(ev)
            });
            const data = await res.json();
            if (data.success) {
                ev.id = data.id; // Update with DB ID
                return true;
            }
        } catch (err) {
            console.error('Failed to save event', err);
        }
        return false;
    }

    // NEW: Update event on server
    async function updateEventOnServer(ev) {
        try {
            const res = await fetch('api.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(ev)
            });
            const data = await res.json();
            return data.success;
        } catch (err) {
            console.error('Failed to update event', err);
        }
        return false;
    }

    // NEW: Delete event from server
    async function deleteEventFromServer(id) {
        try {
            const res = await fetch('api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            return data.success;
        } catch (err) {
            console.error('Failed to delete event', err);
        }
        return false;
    }

    async function deleteEvent(id) {
        const success = await deleteEventFromServer(id);
        if (success) {
            state.events = state.events.filter(ev => ev.id !== id);
            renderCalendar();
        }
    }

    function editEvent(id) {
        const ev = state.events.find(e => e.id === id);
        if (!ev) return;

        // Show form if hidden
        const card = $('eventFormCard');
        if (card && card.style.display === 'none') {
            toggleEventForm();
        }

        // Fill form with event data
        if ($('eventTitle')) $('eventTitle').value = ev.title;
        if ($('eventCategory')) {
            $('eventCategory').value = ev.category;
            onCategoryChange();
        }

        // Date mode
        if (ev.start === ev.end) {
            if ($('dateMode')) $('dateMode').value = 'single';
            onDateModeChange();
            if ($('singleDate')) $('singleDate').value = ev.start;
        } else {
            if ($('dateMode')) $('dateMode').value = 'range';
            onDateModeChange();
            if ($('dateFrom')) $('dateFrom').value = ev.start;
            if ($('dateTo')) $('dateTo').value = ev.end;
        }

        // Other category options
        if (ev.category === 'other') {
            if ($('otherShowBadge')) $('otherShowBadge').checked = ev.showBadge;
            if ($('otherBadgeColor')) $('otherBadgeColor').value = ev.color || '#6c757d';
        }

        // Public holiday name
        if (ev.category === 'public' && $('publicHolidayName')) {
            $('publicHolidayName').value = ev.title;
        }

        // Set editing mode
        state.editingId = id;

        // Update button text
        const submitBtn = document.querySelector('#calendarEventForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="bi bi-pencil-square"></i> Update Event';
        }

        // Scroll to form
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function toggleEventForm() {
        const card = $('eventFormCard');
        const icon = $('eventToggleIcon');
        const text = $('eventToggleText');
        if (!card || !icon || !text) return;

        const visible = card.style.display === 'block';
        card.style.display = visible ? 'none' : 'block';
        icon.classList.toggle('bi-chevron-down', visible);
        icon.classList.toggle('bi-chevron-up', !visible);
        text.textContent = visible ? 'Show Add Event Form' : 'Hide Add Event Form';
    }

    function onCategoryChange() {
        const cat = $('eventCategory');
        const publicGroup = $('publicHolidayNameGroup');
        const otherOpts = $('otherBadgeOptions');
        if (!cat) return;

        if (publicGroup) publicGroup.style.display = (cat.value === 'public') ? 'block' : 'none';
        if (otherOpts) otherOpts.style.display = (cat.value === 'other') ? 'block' : 'none';
    }

    function onDateModeChange() {
        const mode = $('dateMode') ? $('dateMode').value : 'single';
        const singleGrp = $('singleDateGroup');
        const fromGrp = $('dateFromGroup');
        const toGrp = $('dateToGroup');

        if (mode === 'single') {
            if (singleGrp) singleGrp.style.display = 'block';
            if (fromGrp) fromGrp.style.display = 'none';
            if (toGrp) toGrp.style.display = 'none';
        } else {
            if (singleGrp) singleGrp.style.display = 'none';
            if (fromGrp) fromGrp.style.display = 'block';
            if (toGrp) toGrp.style.display = 'block';
        }
    }

    async function addEventFromForm(e) {
        e.preventDefault();
        console.log('calendar addEventFromForm handler triggered');

        const titleInput = $('eventTitle');
        const cat = $('eventCategory');
        const mode = $('dateMode');
        if (!titleInput || !cat || !mode) {
            console.warn('Form elements missing');
            return;
        }

        let title = titleInput.value.trim();
        const category = cat.value;
        if (!title || !category) {
            console.warn('Title or category empty');
            return;
        }

        const publicName = $('publicHolidayName') ? $('publicHolidayName').value.trim() : '';
        if (category === 'public' && publicName) {
            title = publicName;
        }

        let startStr = '';
        let endStr = '';

        if (mode.value === 'single') {
            const sd = $('singleDate') ? $('singleDate').value : '';
            if (!sd) {
                console.warn('Single date not selected');
                return;
            }
            startStr = sd;
            endStr = sd;
        } else {
            const df = $('dateFrom') ? $('dateFrom').value : '';
            const dt = $('dateTo') ? $('dateTo').value : '';
            if (!df || !dt) {
                console.warn('Date range not complete');
                return;
            }
            if (df <= dt) {
                startStr = df;
                endStr = dt;
            } else {
                startStr = dt;
                endStr = df;
            }
        }

        const evData = {
            title,
            category,
            start: startStr,
            end: endStr,
            showBadge: true,
            color: '#6c757d'
        };

        if (category === 'other') {
            const showBadgeEl = $('otherShowBadge');
            const colorEl = $('otherBadgeColor');
            evData.showBadge = showBadgeEl ? !!showBadgeEl.checked : true;
            evData.color = colorEl ? colorEl.value : '#6c757d';
        }

        if (state.editingId !== null) {
            // UPDATE existing event
            evData.id = state.editingId;
            const success = await updateEventOnServer(evData);
            if (success) {
                const idx = state.events.findIndex(ev => ev.id === state.editingId);
                if (idx !== -1) {
                    state.events[idx] = evData;
                    console.log('Updated calendar event', evData);
                }
                state.editingId = null;
                
                const submitBtn = document.querySelector('#calendarEventForm button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Add Event';
                }
            }
        } else {
            // ADD new event
            const success = await saveEventToServer(evData);
            if (success) {
                state.events.push(evData);
                console.log('Added calendar event', evData);
            }
        }

        const firstDate = parseDate(startStr);
        if (firstDate) {
            state.currentDate = new Date(firstDate.getFullYear(), firstDate.getMonth(), 1);
        }

        renderCalendar();

        e.target.reset();
        if ($('eventCategory')) $('eventCategory').value = '';
        onCategoryChange();
        if ($('dateMode')) $('dateMode').value = 'single';
        onDateModeChange();
    }

    function changeMonth(delta) {
        const d = state.currentDate;
        state.currentDate = new Date(d.getFullYear(), d.getMonth() + delta, 1);
        renderCalendar();
    }

    // NEW: open export modal and remember type
    function openExportModal(type) {
        state.exportType = type;
        const modal = new bootstrap.Modal(document.getElementById('exportModal'));
        modal.show();
    }

    // NEW: build the planner HTML for export
    function buildPlannerHTML(fromDate, toDate, orientation) {
        const from = parseDate(fromDate);
        const to   = parseDate(toDate);
        if (!from || !to) return '';

        // Filter events that fall within range
        const filteredEvents = [];
        state.events.forEach(ev => {
            const evStart = parseDate(ev.start);
            const evEnd   = parseDate(ev.end || ev.start);
            if (!evStart || !evEnd) return;
            // If event overlaps [from, to]
            if (evEnd >= from && evStart <= to) {
                filteredEvents.push(ev);
            }
        });

        // Sort by start date
        filteredEvents.sort((a, b) => {
            const aDate = parseDate(a.start).getTime();
            const bDate = parseDate(b.start).getTime();
            return aDate - bDate;
        });

        // Determine grid columns based on orientation
        const gridColumns = orientation === 'portrait' ? 3 : 4;

        // Build HTML with grid layout
        let html = `
            <div class="planner-export-container" style="font-family: Arial, sans-serif; max-width: ${orientation === 'portrait' ? '800px' : '1200px'}; margin: 0 auto;">
                <div class="planner-header" style="text-align: center; margin-bottom: 20px; border-bottom: 3px solid #17a2b8; padding-bottom: 15px;">
                    <img src="../../assets/images/logo.png" alt="School Logo" style="width: 70px; height: auto; margin-bottom: 8px;">
                    <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #1a3a52;">Bornwell Academy</h2>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: #17a2b8; font-style: italic;">For quality education and excellence</p>
                    <p style="margin: 6px 0 0 0; font-size: 11px; color: #7f8c8d;">South Sudan Shirkat along Nimule JUBA highway</p>
                    <p style="margin: 2px 0 0 0; font-size: 11px; color: #7f8c8d;">Tel: +211921315000  •  +211911315000</p>
                    <h3 style="margin: 12px 0 0 0; font-size: 16px; font-weight: 700; color: #2c3e50;">School Planner</h3>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: #95a5a6;">${fromDate} to ${toDate}</p>
                </div>
                <div class="planner-events" style="padding-top: 8px; display: grid; grid-template-columns: repeat(${gridColumns}, 1fr); gap: 10px; align-items: start;">
        `;

        if (filteredEvents.length === 0) {
            html += `<p style="grid-column: 1 / -1; text-align: center; color: #7f8c8d; font-size: 13px; padding: 15px;">No events scheduled in this period.</p>`;
        } else {
            filteredEvents.forEach(ev => {
                const evStart = parseDate(ev.start);
                const evEnd   = parseDate(ev.end || ev.start);
                const startStr = formatDate(evStart);
                const endStr   = formatDate(evEnd);
                const dateRange = (startStr === endStr) ? startStr : `${startStr} to ${endStr}`;

                let badgeColor = '#6c757d';
                if (ev.category === 'public') {
                    badgeColor = '#dc3545'; // Red for public holidays
                } else if (ev.category === 'special') {
                    badgeColor = '#28a745'; // Green for special days
                } else if (ev.category === 'other' && ev.showBadge) {
                    badgeColor = ev.color || '#6c757d';
                }

                html += `
                    <div class="planner-event-item" style="
                        border-left: 4px solid ${badgeColor};
                        padding: 6px 8px;
                        background: #f8f9fa;
                        border-radius: 0 4px 4px 0;
                        page-break-inside: avoid;
                        min-height: 70px;
                    ">
                        <div style="font-size: 9px; color: #95a5a6; margin-bottom: 3px; text-transform: uppercase; font-weight: 600;">${dateRange}</div>
                        <div style="font-size: 12px; font-weight: 700; color: #2c3e50; line-height: 1.3;">${ev.title}</div>
                        <div style="font-size: 9px; color: #7f8c8d; text-transform: uppercase; margin-top: 2px;">
                            ${ev.category === 'public' ? 'Public Holiday' : ev.category === 'special' ? 'Special Day' : 'Other'}
                        </div>
                    </div>
                `;
            });
        }

        html += `</div></div>`;
        return html;
    }

    // NEW: export as image using html2canvas
    async function exportAsImageRange(fromDate, toDate, orientation) {
        const container = document.getElementById('exportPlannerContainer');
        if (!container || typeof html2canvas === 'undefined') return;

        const html = buildPlannerHTML(fromDate, toDate, orientation);
        container.innerHTML = html;
        container.style.display = 'block';

        const canvas = await html2canvas(container, { scale: 2, backgroundColor: '#ffffff' });
        container.style.display = 'none';

        const dataUrl = canvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = `school-planner-${fromDate}-to-${toDate}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // NEW: export as PDF using jspdf
    async function exportAsPdfRange(fromDate, toDate, orientation) {
        const container = document.getElementById('exportPlannerContainer');
        if (!container || typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') return;

        const html = buildPlannerHTML(fromDate, toDate, orientation);
        container.innerHTML = html;
        container.style.display = 'block';

        const canvas = await html2canvas(container, { scale: 2, backgroundColor: '#ffffff' });
        container.style.display = 'none';

        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;

        const pdf = new jsPDF(orientation, 'pt', 'a4');
        const pageWidth  = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();

        const imgWidth  = pageWidth - 40;
        const imgHeight = canvas.height * (imgWidth / canvas.width);

        const x = 20;
        const y = Math.min(20, (pageHeight - imgHeight) / 2);

        pdf.addImage(imgData, 'PNG', x, y, imgWidth, imgHeight);
        pdf.save(`school-planner-${fromDate}-to-${toDate}.pdf`);
    }

    document.addEventListener('DOMContentLoaded', function () {
        console.log('calendar.js loaded, initializing');

        // Toggle form
        const toggleBtn = $('toggleEventFormBtn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleEventForm);
        }

        // Category / date mode handlers
        const cat = $('eventCategory');
        if (cat) {
            cat.addEventListener('change', onCategoryChange);
            onCategoryChange();
        }
        const dm = $('dateMode');
        if (dm) {
            dm.addEventListener('change', onDateModeChange);
            onDateModeChange();
        }

        // Form submit
        const form = $('calendarEventForm');
        if (form) form.addEventListener('submit', addEventFromForm);

        // Month navigation
        const prev = $('prevMonthBtn');
        const next = $('nextMonthBtn');
        if (prev) prev.addEventListener('click', () => changeMonth(-1));
        if (next) next.addEventListener('click', () => changeMonth(1));

        // NEW: Export buttons open modal
        const imgBtn = document.getElementById('exportImageBtn');
        const pdfBtn = document.getElementById('exportPdfBtn');
        if (imgBtn) {
            imgBtn.addEventListener('click', function() {
                state.exportType = 'image';
            });
        }
        if (pdfBtn) {
            pdfBtn.addEventListener('click', function() {
                state.exportType = 'pdf';
            });
        }

        // NEW: Confirm export button
        const confirmBtn = document.getElementById('confirmExportBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', async function() {
                const fromDate = document.getElementById('exportFromDate').value;
                const toDate   = document.getElementById('exportToDate').value;
                const orient   = document.getElementById('exportOrientation').value;

                if (!fromDate || !toDate) {
                    alert('Please select both From and To dates.');
                    return;
                }

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
                if (modal) modal.hide();

                if (state.exportType === 'image') {
                    await exportAsImageRange(fromDate, toDate, orient);
                } else {
                    await exportAsPdfRange(fromDate, toDate, orient);
                }
            });
        }

        // Load events from server on page load
        loadEvents();
    });
})();
