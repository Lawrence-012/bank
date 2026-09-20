// Global Toast Helper Function
function showToast(message, type = 'success') {
    const isSuccess = type === 'success';
    const bgClass = isSuccess ? 'bg-navy text-white' : 'bg-danger text-white';
    const iconClass = isSuccess ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning';
    const title = isSuccess ? 'System Notification' : 'Action Failed';

    const toastHtml = `
        <div class="toast align-items-center border-0 shadow-lg rounded-4 ${bgClass} show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex p-2 align-items-center">
                <div class="toast-body d-flex align-items-center gap-2 fs-7">
                    <i class="bi ${iconClass} fs-5"></i>
                    <div>
                        <strong class="d-block text-capitalize mb-0">${title}</strong>
                        <span class="small">${message}</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    const $toast = $(toastHtml);
    $('#toastContainer').append($toast);

    setTimeout(function () {
        $toast.fadeOut(400, function () {
            $(this).remove();
        });
    }, 3200);
}

$(document).ready(function () {
    // 1. Authentication Handlers
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'api/auth.php?action=login',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status) {
                    window.location.href = 'dashboard.php';
                } else {
                    showToast(res.message, 'error');
                }
            }
        });
    });

    $('#registerForm').on('submit', function (e) {
        e.preventDefault();

        const pass = $('#reg_password').val();
        const confirmPass = $('#reg_confirm_password').val();

        if (pass !== confirmPass) {
            showToast('Passwords do not match! Please check and try again.', 'error');
            return;
        }

        $.ajax({
            url: 'api/auth.php?action=register',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status) {
                    showToast(res.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Registration failed: ' + res.message, 'error');
                }
            }
        });
    });

    $('#logoutBtn').on('click', function () {
        $.ajax({
            url: 'api/auth.php?action=logout',
            type: 'GET',
            dataType: 'json',
            success: function () {
                window.location.href = 'index.php';
            }
        });
    });

    // 2. Account Holder Operations
    $('#transactForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'api/account_action.php?action=transact',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                showToast(res.message, res.status ? 'success' : 'error');
                if (res.status) {
                    $('#transactModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                }
            }
        });
    });

    // 3. Change Password Handler
    $('#changePasswordForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'api/account_action.php?action=change_password',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status) {
                    showToast(res.message, 'success');
                    $('#changePasswordForm')[0].reset();
                } else {
                    showToast(res.message, 'error');
                }
            },
            error: function () {
                showToast('An error occurred while updating your password.', 'error');
            }
        });
    });

    // 4. Loan Application Submission
    $('#loanForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'api/loan_action.php?action=apply',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                showToast(res.message, res.status ? 'success' : 'error');
                if (res.status) {
                    $('#loanModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                }
            }
        });
    });
});