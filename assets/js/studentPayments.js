// Toggle payment form visibility
function togglePaymentForm() {
    const card = document.getElementById('paymentFormCard');
    const btn = document.querySelector('.btn-toggle-form');
    if (!card) return;

    if (card.style.display === 'none' || card.style.display === '') {
        card.style.display = 'block';
        if (btn) btn.querySelector('i').className = 'bi bi-chevron-down';
    } else {
        card.style.display = 'none';
        if (btn) btn.querySelector('i').className = 'bi bi-chevron-right';
    }
}

// Handle student name input from datalist
function handleStudentInput() {
    const input = document.getElementById('studentNameInput');
    const hiddenId = document.getElementById('studentIdHidden');
    const list = document.getElementById('studentList');
    const val = input.value;
    
    // Find matching option in datalist
    let matchingOption = null;
    for (let i = 0; i < list.options.length; i++) {
        if (list.options[i].value === val) {
            matchingOption = list.options[i];
            break;
        }
    }

    if (matchingOption) {
        // Existing student selected
        const data = matchingOption.dataset;
        hiddenId.value = data.id;
        input.style.borderColor = '#27ae60'; // Green for recognized student
        
        document.getElementById('gender').value = data.gender || '';
        document.getElementById('classSelect').value = data.class || '';
        document.getElementById('dayBoarding').value = data.boarding || '';
        document.getElementById('admissionFee').value = parseFloat(data.admissionFee || 0).toFixed(2);
        document.getElementById('uniformFee').value = parseFloat(data.uniformFee || 0).toFixed(2);
        document.getElementById('parentContact').value = data.contact || '';
        document.getElementById('parentEmail').value = data.email || '';
        document.getElementById('studentStatus').value = data.status === 'approved' ? 'Approved' : 'Pending Approval';
        document.getElementById('term').value = window.currentTerm || 'Term 1';
        document.getElementById('category').value = data.category || 'Normal';
        
        // Auto-fill tuition for this class
        handleClassChange();
        
        // Auto-fill applicable terms
        const termsArr = (data.applicableTerms || "T1,T2,T3").split(",");
        if (document.getElementById("termT1")) document.getElementById("termT1").checked = termsArr.includes("T1");
        if (document.getElementById("termT2")) document.getElementById("termT2").checked = termsArr.includes("T2");
        if (document.getElementById("termT3")) document.getElementById("termT3").checked = termsArr.includes("T3");
        
        updatePreview(val, data.className, window.currentTerm, data.boarding, data.gender);
    } else {
        // New student or manual typing
        hiddenId.value = '0';
        document.getElementById('studentStatus').value = 'Approved (New)';
        
        // Reset terms to checked by default
        if (document.getElementById("termT1")) document.getElementById("termT1").checked = true;
        if (document.getElementById("termT2")) document.getElementById("termT2").checked = true;
        if (document.getElementById("termT3")) document.getElementById("termT3").checked = true;
        
        // CHECK FOR DUPLICATE NAME MANUALLY
        let isDuplicate = false;
        for (let i = 0; i < list.options.length; i++) {
            if (list.options[i].value.toLowerCase() === val.toLowerCase()) {
                isDuplicate = true;
                break;
            }
        }
        
        if (isDuplicate && val.length > 2) {
            input.style.borderColor = '#e74c3c'; // Red for duplicate warning
            const existingNote = document.querySelector('.text-muted.d-block.mt-2');
            if (existingNote) {
                existingNote.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> <span class="text-danger fw-bold">Warning: A student with this name already exists! Select them from the list to avoid duplicates.</span>';
            }
        } else {
            input.style.borderColor = ''; // Reset
            const existingNote = document.querySelector('.text-muted.d-block.mt-2');
            if (existingNote) {
                existingNote.innerHTML = '<i class="bi bi-info-circle"></i> If the student is new, just type the full name and fill other fields below.';
            }
        }

        const preview = document.getElementById('studentPreviewCard');
        if (preview) preview.style.display = 'none';
    }
}

// Helper to normalize different term strings (e.g. "Term 3", "term 3", "3", "t3" all become "term 3")
function normalizeTermName(term) {
    if (!term) return '';
    const t = String(term).trim().toLowerCase();
    
    // Check for annual
    if (t.includes('annual') || t.includes('annually') || t.includes('year') || t === 'yr') {
        return 'annual';
    }
    
    // Extract numbers to match Term 1, Term 2, Term 3
    const numMatch = t.match(/\d+/);
    if (numMatch) {
        return 'term ' + numMatch[0];
    }
    
    // Default return trimmed lowercased
    return t;
}

// Auto-fill expected tuition when class or term changes
function handleClassChange() {
    const classSelect = document.getElementById('classSelect');
    const classId = classSelect.value;
    const termInput = document.getElementById('term');
    const tuitionInput = document.getElementById('expectedTuition');
    
    if (!classId) {
        termInput.value = '';
        tuitionInput.value = '';
        return;
    }

    const selectedOption = classSelect.options[classSelect.selectedIndex];
    const className = selectedOption ? selectedOption.text : '';
    const category = document.getElementById('category').value;
    console.log('Class Change triggered:', { classId, className, category });
    
    // 1. Try to find tuition data by Category first (if not Normal)
    let tuitionData = null;
    const termToUse = document.getElementById('term').value || window.currentTerm || 'Term 1';

    if (category && category.toLowerCase() !== 'normal') {
        const catTuition = window.categoryTuition[category.toLowerCase().trim()];
        if (catTuition) {
            // Advanced fuzzy lookup for term
            const normTerm = normalizeTermName(termToUse);
            const matchedKey = Object.keys(catTuition).find(k => normalizeTermName(k) === normTerm);
            if (matchedKey !== undefined && catTuition[matchedKey] !== undefined) {
                tuitionData = { [termToUse]: catTuition[matchedKey] };
            }
        }
    }

    // 2. Fallback to Class Tuition Data if no category fee found
    if (!tuitionData) {
        tuitionData = window.classTermTuition[classId] || window.classTermTuition[parseInt(classId)];
    }

    // 2. Fallback: Try to find by exact Name (normalized for casing/spaces)
    if (!tuitionData && className) {
        const normalizedName = className.trim().toLowerCase();
        tuitionData = window.classNameTuition[normalizedName];
        
        // 3. Fallback: Try fuzzy matching (no dots)
        if (!tuitionData) {
            const fuzzyName = normalizedName.replace(/\./g, '');
            tuitionData = window.classNameTuition[fuzzyName];
        }
    }

    if (tuitionData) {
        const availableTerms = Object.keys(tuitionData);
        let termToSelect = '';

        // Prefer the current system term if it's available for this class
        if (availableTerms.includes(window.currentTerm)) {
            termToSelect = window.currentTerm;
        } else if (availableTerms.length > 0) {
            // Otherwise pick the first available one (e.g. Term 1 or Annual)
            termToSelect = availableTerms[0];
        }

        if (termToSelect) {
            termInput.value = termToSelect;
        }

        // Now update tuition based on the auto-filled term
        const finalTerm = termInput.value;
        if (tuitionData[finalTerm] !== undefined) {
            tuitionInput.value = parseFloat(tuitionData[finalTerm]).toFixed(2);
        } else {
            tuitionInput.value = '';
        }
    } else {
        console.warn('No tuition data found for:', className);
        termInput.value = '';
        tuitionInput.value = '';
    }
    
    // Update preview if name is present
    const name = document.getElementById('studentNameInput').value;
    if (name) {
        const boarding = document.getElementById('dayBoarding').value;
        const gender = document.getElementById('gender').value;
        updatePreview(name, className, termInput.value, boarding, gender, category);
    }
}

// Ensure category change also triggers tuition recalculation
document.addEventListener('DOMContentLoaded', function() {
    const catSelect = document.getElementById('category');
    if (catSelect) {
        catSelect.addEventListener('change', handleClassChange);
    }
});

function updatePreview(name, className, term, boarding, gender, category) {
    const previewCard = document.getElementById('studentPreviewCard');
    if (previewCard) {
        document.getElementById('previewStudentName').textContent = name;
        document.getElementById('previewClass').textContent = className;
        document.getElementById('previewTerm').textContent = term || '-';
        document.getElementById('previewDayBoarding').textContent = boarding || '-';
        document.getElementById('previewGender').textContent = gender || '-';
        // If category is not normal, show it in the preview
        if (category && category.toLowerCase() !== 'normal') {
             document.getElementById('previewClass').textContent = className + ' (' + category + ')';
        }
        previewCard.style.display = 'block';
    }
}

// Set payment ID for additional payment modal
function setPaymentId(id, balance) {
    const paymentId = document.getElementById('modalPaymentId');
    const balanceField = document.getElementById('modalBalance');
    const amountField = document.getElementById('modalAmount');

    if (paymentId) paymentId.value = id;
    if (balanceField) balanceField.value = parseFloat(balance).toFixed(2);
    if (amountField) {
        amountField.value = '';
        amountField.max = balance;
    }
}

// Load edit payment data into modal
function loadEditPayment(id, amountPaid, admissionFee, uniformFee, expectedTuition, studentName, studentId, classId, category, dayBoarding, term, applicableTerms) {
    const el = (selector) => document.getElementById(selector);

    if (!el('editPaymentId')) {
        console.error('Edit Payment Modal not found on page');
        return;
    }

    el('editPaymentId').value = id;
    el('editStudentId').value = studentId || 0;
    el('editPaymentStudentName').value = studentName;
    el('editPaymentExpected').value = parseFloat(expectedTuition).toFixed(2);
    el('editPaymentAmountPaid').value = parseFloat(amountPaid).toFixed(2);
    el('editPaidSoFar').value = parseFloat(amountPaid).toFixed(2); // Set base for transitions
    el('editRemainingExpected').value = ''; // Reset transition field
    el('editPaymentAdmissionFee').value = parseFloat(admissionFee).toFixed(2);
    el('editPaymentUniformFee').value = parseFloat(uniformFee).toFixed(2);
    
    // Set Class and Boarding
    if (el('editPaymentClass')) el('editPaymentClass').value = classId || '';
    if (el('editPaymentCategory')) el('editPaymentCategory').value = category || 'Normal';
    if (el('editPaymentBoarding')) el('editPaymentBoarding').value = dayBoarding || '';
    if (el('editPaymentTerm')) el('editPaymentTerm').value = term || '';

    // Load active checkboxes for terms
    const termsArr = (applicableTerms || "T1,T2,T3").split(",");
    if (el("editTermT1")) el("editTermT1").checked = termsArr.includes("T1");
    if (el("editTermT2")) el("editTermT2").checked = termsArr.includes("T2");
    if (el("editTermT3")) el("editTermT3").checked = termsArr.includes("T3");

    calculateEditBalance();
}

// Handle manual transition override
function calculateTransitionTuition() {
    const paidSoFar = parseFloat(document.getElementById('editPaidSoFar').value) || 0;
    const remaining = parseFloat(document.getElementById('editRemainingExpected').value) || 0;
    const tuitionInput = document.getElementById('editPaymentExpected');
    
    if (remaining > 0) {
        // Calculate new annual total: what they paid + what they still owe
        const newTotal = paidSoFar + remaining;
        tuitionInput.value = newTotal.toFixed(2);
        
        // Trigger balance recalculation
        calculateEditBalance();
    } else {
        // If remaining is cleared, revert to standard class/category fee
        handleEditClassChange();
    }
}

// Handle class change in EDIT modal to update expected tuition
function handleEditClassChange() {
    const classId = document.getElementById('editPaymentClass').value;
    const termInput = document.getElementById('editPaymentTerm');
    const tuitionInput = document.getElementById('editPaymentExpected');

    if (!classId) return;

    const category = document.getElementById('editPaymentCategory').value;
    const termToUse = termInput.value || window.currentTerm || 'Term 1';
    
    // 1. Try category fee first
    let tuitionData = null;
    if (category && category.toLowerCase() !== 'normal') {
        const catTuition = window.categoryTuition[category.toLowerCase().trim()];
        if (catTuition) {
            // Advanced fuzzy lookup for term
            const normTerm = normalizeTermName(termToUse);
            const matchedKey = Object.keys(catTuition).find(k => normalizeTermName(k) === normTerm);
            if (matchedKey !== undefined && catTuition[matchedKey] !== undefined) {
                tuitionData = { [termToUse]: catTuition[matchedKey] };
            }
        }
    }

    // 2. Fallback to Class Tuition Data
    if (!tuitionData) {
        tuitionData = window.classTermTuition[classId] || window.classTermTuition[parseInt(classId)];
    }
    
    if (tuitionData) {
        const availableTerms = Object.keys(tuitionData);
        let currentVal = termInput.value;

        // If the current term typed is not in this new class, auto-pick one
        if (!availableTerms.includes(currentVal)) {
            if (availableTerms.includes(window.currentTerm)) {
                termInput.value = window.currentTerm;
            } else if (availableTerms.length > 0) {
                termInput.value = availableTerms[0];
            }
        }

        const finalTerm = termInput.value;
        if (tuitionData[finalTerm] !== undefined) {
            tuitionInput.value = parseFloat(tuitionData[finalTerm]).toFixed(2);
        } else {
            tuitionInput.value = '';
        }
    } else {
        console.warn('No tuition data found for this class in edit modal');
        tuitionInput.value = '';
    }

    calculateEditBalance();
}

// Calculate new balance when amount paid changes in edit modal
function calculateEditBalance() {
    const expectedField = document.getElementById('editPaymentExpected');
    const paidField = document.getElementById('editPaymentAmountPaid');
    const balanceField = document.getElementById('editPaymentNewBalance');

    if (!expectedField || !paidField || !balanceField) return;

    const expected = parseFloat(expectedField.value) || 0;
    const paid = parseFloat(paidField.value) || 0;
    const balance = expected - paid;
    balanceField.value = balance.toFixed(2);

    balanceField.style.color = balance <= 0 ? '#27ae60' : '#e74c3c';
}

// AJAX to save inline comment
function saveComment(id) {
    const input = document.getElementById('comment_' + id);
    const statusDiv = document.getElementById('comment_status_' + id);
    if (!input) return;

    const comment = input.value;
    
    // Visual feedback: sending...
    statusDiv.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Saving...</span>';
    
    const formData = new FormData();
    formData.append('payment_id', id);
    formData.append('comment', comment);

    fetch('save_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Saved</span>';
            // Fade out after 2 seconds
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 2000);
        } else {
            statusDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Error: ' + data.message + '</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Connection Error</span>';
    });
}

// Prevent double form submission
document.addEventListener('DOMContentLoaded', function() {
    // Auto-expand all comment textareas on page load
    const textareas = document.querySelectorAll('.auto-expand');
    textareas.forEach(textarea => {
        if (textarea.value) {
            textarea.style.height = textarea.scrollHeight + 'px';
        }
    });

    const paymentForm = document.getElementById('paymentForm');

    if (paymentForm) {
        let isSubmitting = false;

        paymentForm.addEventListener('submit', function(e) {
            // Check checkboxes
            const checkboxes = paymentForm.querySelectorAll('.term-checkbox');
            const isChecked = Array.from(checkboxes).some(cb => cb.checked);
            if (!isChecked) {
                e.preventDefault();
                alert('Please check at least one term (T1, T2, or T3) to apply the tuition to.');
                return false;
            }

            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            const submitBtn = paymentForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Recording...';
            }

            isSubmitting = true;
        });
    }

    const editPaymentForm = document.getElementById('editPaymentForm');
    if (editPaymentForm) {
        editPaymentForm.addEventListener('submit', function(e) {
            const checkboxes = editPaymentForm.querySelectorAll('.edit-term-checkbox');
            const isChecked = Array.from(checkboxes).some(cb => cb.checked);
            if (!isChecked) {
                e.preventDefault();
                alert('Please check at least one term (T1, T2, or T3) to apply the tuition to in the edit form.');
                return false;
            }
        });
    }
});
