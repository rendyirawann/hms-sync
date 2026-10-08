/* ==========================================================================
   HMS admin UI conventions (WORKFLOW.md §4)

   - Every form submit shows a spinner on its submit button and blocks
     double clicks.
   - <form data-ajax> posts via fetch. Success and failure are reported with
     SweetAlert; validation errors (422) are listed in the alert.
   - <form data-ajax data-ajax-confirm="..."> asks first (deletes, status
     changes).
   - [data-hms-drawer="#id"] opens a right-side offcanvas form, filled from
     data-record (edit) or reset (create).
   - [data-hms-show="url"] loads a detail partial into the centered modal.
   ========================================================================== */
(function (window, document) {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function swal(options) {
        if (window.Swal) {
            return window.Swal.fire(Object.assign({
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' }
            }, options));
        }
        window.alert((options.title ? options.title + '\n' : '') + (options.text || ''));
        return Promise.resolve({ isConfirmed: true });
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    window.HMS = window.HMS || {};

    window.HMS.success = function (message) {
        return swal({ icon: 'success', title: 'Berhasil', text: message, timer: 1800, showConfirmButton: false });
    };

    window.HMS.error = function (message, errors) {
        var html = escapeHtml(message || 'Terjadi kesalahan.');
        if (errors) {
            var items = [];
            Object.keys(errors).forEach(function (key) {
                [].concat(errors[key]).forEach(function (msg) { items.push('<li>' + escapeHtml(msg) + '</li>'); });
            });
            if (items.length) {
                html = '<ul class="text-start mb-0 ps-5">' + items.join('') + '</ul>';
            }
        }
        return swal({ icon: 'error', title: errors ? 'Periksa kembali input' : 'Gagal', html: html });
    };

    /* ---------------- Spinner on submit ---------------- */

    function setBusy(button, busy) {
        if (!button) {
            return;
        }
        if (busy) {
            if (button.dataset.busy === '1') {
                return;
            }
            button.dataset.busy = '1';
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm align-middle me-2" aria-hidden="true"></span>'
                + escapeHtml(button.dataset.loadingText || 'Memproses...');
        } else if (button.dataset.busy === '1') {
            button.dataset.busy = '';
            button.disabled = false;
            button.innerHTML = button.dataset.originalHtml;
        }
    }

    function submitButton(form, event) {
        return (event && event.submitter) || form.querySelector('[type="submit"]');
    }

    /* ---------------- AJAX forms ---------------- */

    function sendAjax(form, button) {
        setBusy(button, true);

        var body = new FormData(form);
        if (button && button.name) {
            body.append(button.name, button.value);
        }

        return fetch(form.action, {
            method: 'POST', // _method carries PUT/DELETE
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        }).then(function (result) {
            if (result.ok) {
                var drawer = form.closest('.offcanvas');
                if (drawer && window.bootstrap) {
                    window.bootstrap.Offcanvas.getOrCreateInstance(drawer).hide();
                }
                window.HMS.success(result.data.message || 'Data berhasil disimpan.').then(function () {
                    if (result.data.redirect) {
                        window.location.href = result.data.redirect;
                    } else {
                        window.location.reload();
                    }
                });
                return;
            }

            setBusy(button, false);

            if (result.status === 422) {
                window.HMS.error(result.data.message, result.data.errors || {});
            } else if (result.status === 419) {
                window.HMS.error('Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.');
            } else if (result.status === 429) {
                window.HMS.error('Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.');
            } else {
                window.HMS.error(result.data.message || 'Terjadi kesalahan pada server (' + result.status + ').');
            }
        }).catch(function () {
            setBusy(button, false);
            window.HMS.error('Tidak dapat terhubung ke server.');
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var button = submitButton(form, event);

        if (!form.hasAttribute('data-ajax')) {
            // A page script already took over this submit (its own AJAX flow).
            if (event.defaultPrevented || form.hasAttribute('data-no-spinner')) {
                return;
            }
            // Plain forms still get the spinner + double-submit guard.
            if (button && button.dataset.busy === '1') {
                event.preventDefault();
                return;
            }
            setBusy(button, true);
            return;
        }

        event.preventDefault();

        var question = form.getAttribute('data-ajax-confirm');
        if (!question) {
            sendAjax(form, button);
            return;
        }

        swal({
            icon: form.getAttribute('data-confirm-icon') || 'warning',
            title: form.getAttribute('data-confirm-title') || 'Konfirmasi',
            text: question,
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: form.getAttribute('data-confirm-ok') || 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' }
        }).then(function (result) {
            if (result.isConfirmed) {
                sendAjax(form, button);
            }
        });
    });

    // Restore buttons when the page is shown from bfcache.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-busy="1"]').forEach(function (button) { setBusy(button, false); });
    });

    /* ---------------- Right drawer forms ---------------- */

    function fillForm(form, record) {
        Array.prototype.forEach.call(form.elements, function (field) {
            if (!field.name || field.name === '_token' || field.name === '_method' || field.type === 'file') {
                return;
            }
            var key = field.name.replace(/\[\]$/, '');
            var value = record && Object.prototype.hasOwnProperty.call(record, key) ? record[key] : null;

            if (field.type === 'checkbox') {
                field.checked = value === null ? field.defaultChecked : (value === true || value === 1 || value === '1');
            } else if (field.type === 'radio') {
                field.checked = value !== null && String(field.value) === String(value);
            } else if (field.tagName === 'SELECT' && field.multiple) {
                var values = [].concat(value || []).map(String);
                Array.prototype.forEach.call(field.options, function (o) { o.selected = values.indexOf(o.value) !== -1; });
            } else {
                field.value = value === null || value === undefined ? (field.defaultValue || '') : value;
            }
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-hms-drawer]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        var drawer = document.querySelector(trigger.getAttribute('data-hms-drawer'));
        if (!drawer || !window.bootstrap) {
            return;
        }
        var form = drawer.querySelector('form');
        var record = trigger.getAttribute('data-record');

        form.reset();
        form.action = trigger.getAttribute('data-action') || form.getAttribute('data-default-action') || form.action;

        var method = form.querySelector('input[name="_method"]');
        if (method) {
            method.value = trigger.getAttribute('data-method') || 'POST';
        }

        fillForm(form, record ? JSON.parse(record) : null);

        var title = drawer.querySelector('[data-drawer-title]');
        if (title) {
            title.textContent = trigger.getAttribute('data-title') || title.textContent;
        }

        window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
    });

    /* ---------------- Centered detail modal ---------------- */

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-hms-show]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        var modalEl = document.getElementById('hms-show-modal');
        if (!modalEl || !window.bootstrap) {
            return;
        }
        var body = modalEl.querySelector('.modal-body');
        modalEl.querySelector('.modal-title').textContent = trigger.getAttribute('data-title') || 'Detail';
        body.innerHTML = '<div class="text-center py-10"><span class="spinner-border text-primary"></span></div>';
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();

        fetch(trigger.getAttribute('data-hms-show'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(response.status);
            }
            return response.text();
        }).then(function (html) {
            body.innerHTML = html;
        }).catch(function () {
            body.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat detail.</div>';
        });
    });
})(window, document);
