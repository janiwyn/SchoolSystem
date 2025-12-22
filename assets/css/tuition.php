<style>

.form-header {
    background-color: #17a2b8 !important;
}

.btn-form-submit {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
    font-weight: 600;
    padding: 10px 24px;
}

.btn-form-submit:hover {
    background-color: #138496;
    border-color: #138496;
    color: white;
}

.filter-card {
    background: #f8f9fa;
    border: none;
    margin-bottom: 30px;
}

.filter-card .card-body {
    padding: 25px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.filter-group input,
.filter-group select {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    height: 40px;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: #17a2b8;
    box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

.btn-filter {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    height: 40px;
    font-size: 14px;
}

.btn-filter:hover {
    background-color: #138496;
}

.btn-reset {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    height: 40px;
    width: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-reset:hover {
    background-color: #5a6268;
}

.table-container {
    overflow-x: auto;
}

.table {
    margin: 0;
    font-size: 14px;
}

.table thead th {
    background-color: #17a2b8;
    color: white;
    font-weight: 600;
    border: none;
    padding: 16px 12px;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

.table tbody td {
    padding: 14px 12px;
    border-color: #eee;
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.table tbody tr:last-child td {
    border-bottom: 1px solid #eee;
}

</style>