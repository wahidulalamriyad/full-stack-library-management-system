/* ================================================================
   SHARED JAVASCRIPT
   1. validateForm()  - client side validation before a form is sent
   2. esc()           - escapes text before it is put into the page
   3. liveSearch()    - waits until typing stops, then runs a search
   4. ajaxTable()     - fetches JSON and redraws one table body
   Remember: the PHP side validates everything again. JavaScript is
   only here to give a fast answer to the user.
   ================================================================ */


/* ---------------- 1. Form validation ---------------- */

// Shows a red message under one input.
function showFieldError(input, message) {
    input.classList.add('is-invalid');
    var note = document.createElement('span');
    note.className = 'field-error';
    note.textContent = message;
    input.parentNode.appendChild(note);
}

function clearFieldErrors(form) {
    form.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
}

// Rules are written on the inputs themselves:
//   required            -> must not be empty
//   data-min="6"        -> minimum number of characters
//   data-match="pwd"    -> must equal the field called pwd
//   data-phone="1"      -> must look like a phone number
//   type="email"        -> must look like an email address
//   type="number" + min -> must be a number inside the range
//   data-label="Name"   -> friendly name used in the message
function validateForm(form) {
    clearFieldErrors(form);
    var valid = true;
    var firstBad = null;

    form.querySelectorAll('input, select, textarea').forEach(function (input) {
        if (input.type === 'hidden' || input.type === 'checkbox' || input.disabled) { return; }

        var value = (input.value || '').trim();
        var label = input.getAttribute('data-label') || input.name || 'This field';
        var message = '';

        if (input.hasAttribute('required') && value === '') {
            message = label + ' is required.';

        } else if (value !== '' && input.dataset.min && value.length < parseInt(input.dataset.min, 10)) {
            message = label + ' needs at least ' + input.dataset.min + ' characters.';

        } else if (value !== '' && input.type === 'email' &&
                   !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
            message = 'Enter a valid email address.';

        } else if (value !== '' && input.dataset.phone &&
                   !/^[0-9+\-\s()]{6,20}$/.test(value)) {
            message = 'Enter a valid contact number.';

        } else if (value !== '' && input.dataset.match) {
            var other = form.querySelector('[name="' + input.dataset.match + '"]');
            if (other && value !== other.value.trim()) {
                message = 'The two passwords do not match.';
            }

        } else if (value !== '' && input.type === 'number') {
            var num = parseFloat(value);
            if (isNaN(num)) {
                message = label + ' must be a number.';
            } else if (input.min !== '' && num < parseFloat(input.min)) {
                message = label + ' cannot be less than ' + input.min + '.';
            } else if (input.max !== '' && num > parseFloat(input.max)) {
                message = label + ' cannot be more than ' + input.max + '.';
            }

        } else if (value !== '' && input.type === 'date' && input.min && value < input.min) {
            message = label + ' cannot be in the past.';
        }

        if (message) {
            showFieldError(input, message);
            valid = false;
            if (!firstBad) { firstBad = input; }
        }
    });

    if (firstBad) { firstBad.focus(); }
    return valid;   // false stops the form from being submitted
}


/* ---------------- 2. Escaping ---------------- */

// SECURITY: any text coming back from the server is escaped before it
// is written into the page, so a title like <script> stays harmless.
function esc(text) {
    return String(text === null || text === undefined ? '' : text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/* ---------------- 2b. Fine that is still growing ---------------- */

// A book that is out and past its due date keeps collecting a fine, so the
// number in an AJAX-refreshed table is worked out here instead of being read
// from the database.  FINE_RATE is printed by the page that loads this file.
function liveFine(request) {
    if (request.status !== 'issued' || !request.due_date) {
        return parseFloat(request.fine) || 0;
    }
    var due  = new Date(request.due_date + 'T00:00:00');
    var days = Math.floor((Date.now() - due.getTime()) / 86400000);
    return days > 0 ? days * (window.FINE_RATE || 0) : 0;
}


/* ---------------- 3. Live search ---------------- */

// Waits 250 ms after the last keystroke, then calls handler().
function liveSearch(inputId, handler) {
    var input = document.getElementById(inputId);
    if (!input) { return; }

    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(handler, 250);
    });
}


/* ---------------- 4. AJAX table redraw ---------------- */

// options = { url, tbody, counter, columns, word, row }
//   row(item, index) must return one <tr> string.
function ajaxTable(options) {
    var tbody   = document.getElementById(options.tbody);
    var counter = document.getElementById(options.counter);
    if (!tbody) { return; }

    fetch(options.url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (rows) {

            if (rows && rows.error) {
                tbody.innerHTML = '<tr><td colspan="' + options.columns + '" class="empty">' +
                                  esc(rows.error) + '</td></tr>';
                return;
            }
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="' + options.columns + '" class="empty">' +
                                  'Nothing matches your search.</td></tr>';
                if (counter) { counter.textContent = '0 ' + options.word; }
                return;
            }

            var html = '';
            rows.forEach(function (item, index) { html += options.row(item, index); });
            tbody.innerHTML = html;

            if (counter) { counter.textContent = rows.length + ' ' + options.word; }
        })
        .catch(function (err) {
            console.error('Search failed:', err);
        });
}
