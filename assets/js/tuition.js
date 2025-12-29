// Ensure function is on window and log for debugging (helps on hosted server)
window.loadEditForm = function loadEditForm(id, className, term, amount) {
    console.log('loadEditForm called', { id, className, term, amount });

    const idField    = document.getElementById('editTuitionId');
    const classField = document.getElementById('editClassName');
    const termField  = document.getElementById('editTerm');
    const amtField   = document.getElementById('editAmount');

    if (!idField || !classField || !termField || !amtField) {
        console.warn('Edit Tuition fields missing on this page', {
            idField,
            classField,
            termField,
            amtField
        });
        return;
    }

    idField.value    = id || '';
    classField.value = className || '';
    termField.value  = term || '';
    amtField.value   = amount || '';
};
