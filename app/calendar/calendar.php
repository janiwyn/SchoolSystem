<?php
$title = "School Calendar";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

// Allow all main roles
requireRole(['admin', 'principal', 'bursar']);

// Include layout AFTER header operations
require_once __DIR__ . '/../helper/layout.php';
?>

<div class="card shadow-sm border-0 mb-4 calendar-card">
    <div class="card-header calendar-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <img src="../../assets/images/logo.png" alt="School Logo" class="calendar-logo">
            <div class="calendar-school-info">
                <h5 class="mb-0">School Calendar</h5>
                <small class="text-muted">Academic Events, Holidays &amp; Special Days</small>
            </div>
        </div>
        <div class="calendar-export-buttons">
            <!-- NEW: open modal instead of direct export -->
            <button type="button" id="exportImageBtn" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#exportModal" data-type="image">
                <i class="bi bi-image"></i> Export as Image
            </button>
            <button type="button" id="exportPdfBtn" class="btn btn-sm btn-light text-primary" data-bs-toggle="modal" data-bs-target="#exportModal" data-type="pdf">
                <i class="bi bi-file-earmark-pdf"></i> Export as PDF
            </button>
        </div>
    </div>
    <div class="card-body calendar-body">
        <!-- Toggle Add Event Form -->
        <button type="button" class="btn-toggle-form mb-3" id="toggleEventFormBtn">
            <i class="bi bi-chevron-down" id="eventToggleIcon"></i>
            <span id="eventToggleText">Show Add Event Form</span>
        </button>

        <!-- Add Event Form -->
        <div class="calendar-event-form card mb-4" id="eventFormCard" style="display: none;">
            <div class="card-body">
                <form id="calendarEventForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Event Title</label>
                        <input type="text" name="title" id="eventTitle" class="form-control" placeholder="e.g., Midterm Exams" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="category" id="eventCategory" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="public">Public Holiday</option>
                            <option value="special">Special Day</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-4" id="publicHolidayNameGroup" style="display: none;">
                        <label class="form-label">Public Holiday Name</label>
                        <input type="text" id="publicHolidayName" class="form-control" placeholder="e.g., Independence Day">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Date Mode</label>
                        <select id="dateMode" class="form-control">
                            <option value="single">Single Date</option>
                            <option value="range">Date Range</option>
                        </select>
                    </div>

                    <div class="col-md-4" id="singleDateGroup">
                        <label class="form-label">Date</label>
                        <input type="date" id="singleDate" class="form-control">
                    </div>

                    <div class="col-md-4" id="dateFromGroup" style="display: none;">
                        <label class="form-label">Date From</label>
                        <input type="date" id="dateFrom" class="form-control">
                    </div>

                    <div class="col-md-4" id="dateToGroup" style="display: none;">
                        <label class="form-label">Date To</label>
                        <input type="date" id="dateTo" class="form-control">
                    </div>

                    <!-- Other category options -->
                    <div class="col-md-4" id="otherBadgeOptions" style="display: none;">
                        <label class="form-label d-block">Badge Options</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="otherShowBadge" checked>
                            <label class="form-check-label" for="otherShowBadge">Show Badge</label>
                        </div>
                        <div class="mt-2">
                            <label class="form-label mb-1">Badge Color</label>
                            <input type="color" id="otherBadgeColor" class="form-control form-control-color" value="#6c757d" title="Choose badge color">
                        </div>
                    </div>

                    <div class="col-md-12 mt-3">
                        <!-- Simplified button with explicit class -->
                        <button type="submit" class="calendar-add-btn">
                            <i class="bi bi-plus-circle"></i> Add Event
                        </button>
                    </div>
                </form>

                <div class="mt-3 small text-muted">
                    <strong>Legend:</strong>
                    <span class="badge calendar-badge badge-public">Public Holiday</span>
                    <span class="badge calendar-badge badge-special">Special Day</span>
                    <span class="badge calendar-badge badge-other">Other</span>
                </div>
            </div>
        </div>

        <!-- Calendar Wrapper (used for export) -->
        <div id="calendarExportWrapper" class="calendar-export-wrapper">
            <div class="calendar-export-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/logo.png" alt="School Logo" class="calendar-logo">
                    <div class="calendar-school-info">
                        <h5 class="mb-0">Bornwell Academy</h5>
                        <small class="text-muted">Official School Calendar</small>
                    </div>
                </div>
                <div id="calendarMonthLabel" class="calendar-month-label"></div>
            </div>

            <div class="calendar-nav d-flex justify-content-between align-items-center mt-3 mb-2">
                <button type="button" id="prevMonthBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> Previous
                </button>
                <div id="calendarMonthTitle" class="fw-bold"></div>
                <button type="button" id="nextMonthBtn" class="btn btn-sm btn-outline-secondary">
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <div id="calendarGrid" class="calendar-grid">
                <!-- Filled by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Export Range Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header form-header text-white">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="bi bi-calendar-range"></i> Export School Planner
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="exportFromDate" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="exportFromDate" required>
                </div>
                <div class="mb-3">
                    <label for="exportToDate" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="exportToDate" required>
                </div>
                <div class="mb-3">
                    <label for="exportOrientation" class="form-label">Orientation</label>
                    <select class="form-control" id="exportOrientation">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-form-submit" id="confirmExportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden container for export (will be populated by JS) -->
<div id="exportPlannerContainer" style="display: none; width: 100%; background: white; padding: 30px;">
    <!-- Populated dynamically by JS -->
</div>

<!-- Export libraries + page JS -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="../../assets/js/calendar.js?v=12"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
