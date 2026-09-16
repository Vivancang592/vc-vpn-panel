// Gắn thẳng các hàm vào window scope để gọi được từ bất kỳ đâu
window.closeAlert = function (btn) {
    const alertBox = btn.closest('.alert');
    if (alertBox) {
        alertBox.style.display = 'none';
    }
};

window.togglePasswordVisibility = function (btn) {
    const targetId = btn.getAttribute('data-target');
    const input = targetId ? document.getElementById(targetId) : btn.parentElement.querySelector('input');
    if (input) {
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁️';
        }
    }
};

window.sendOtpCode = function (customEndpoint) {
    const emailInput = document.getElementById('email');
    const btnSend = document.getElementById('btnSendOtp');
    const csrfTokenEl = document.getElementById('csrf_token');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';
    const alertBox = document.getElementById('ajax-alert');

    let endpoint = customEndpoint;
    if (!endpoint || typeof endpoint !== 'string') {
        const isRegister = !!document.getElementById('registerForm');
        endpoint = isRegister ? '/register/send-otp' : '/forgot-password/send-otp';
    }

    if (!emailInput || !emailInput.value || !emailInput.checkValidity()) {
        if (alertBox) {
            alertBox.className = 'alert alert-error';
            alertBox.style.display = 'block';
            alertBox.innerHTML = 'Vui lòng nhập địa chỉ Email hợp lệ trước khi lấy mã OTP. <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>';
        }
        if (emailInput) emailInput.focus();
        return;
    }

    btnSend.disabled = true;
    btnSend.textContent = 'Đang gửi...';

    const formData = new FormData();
    formData.append('email', emailInput.value);
    formData.append('csrf_token', csrfToken);

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (alertBox) {
            alertBox.style.display = 'block';
            if (data.success) {
                alertBox.className = 'alert alert-success';
                alertBox.innerHTML = data.message + ' <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>';
                startOtpCountdown(60);
            } else {
                alertBox.className = 'alert alert-error';
                alertBox.innerHTML = (data.message || 'Có lỗi xảy ra.') + ' <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>';
                btnSend.disabled = false;
                btnSend.textContent = 'Gửi mã';
            }
        }
    })
    .catch(error => {
        if (alertBox) {
            alertBox.style.display = 'block';
            alertBox.className = 'alert alert-error';
            alertBox.innerHTML = 'Không thể kết nối đến máy chủ. Vui lòng thử lại sau. <button type="button" class="alert-close" onclick="closeAlert(this)">&times;</button>';
            btnSend.disabled = false;
            btnSend.textContent = 'Gửi mã';
        }
    });
};

window.startOtpCountdown = function (seconds) {
    const btnSend = document.getElementById('btnSendOtp');
    if (!btnSend) return;
    let left = seconds;
    btnSend.disabled = true;

    const timer = setInterval(() => {
        if (left <= 0) {
            clearInterval(timer);
            btnSend.disabled = false;
            btnSend.textContent = 'Gửi mã';
        } else {
            btnSend.textContent = left + 's';
            left--;
        }
    }, 1000);
};

document.addEventListener('DOMContentLoaded', function () {
    // Validation Form & Loading Indicator
    const authForms = document.querySelectorAll('.auth-form');
    authForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="password_confirm"], input[name="confirm_password"]');
            const submitBtn = form.querySelector('button[type="submit"]');

            if (password && confirmPassword && password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không trùng khớp. Vui lòng kiểm tra lại!');
                confirmPassword.focus();
                return false;
            }

            if (password && password.value.length < 6) {
                e.preventDefault();
                alert('Mật khẩu phải chứa ít nhất 6 ký tự!');
                password.focus();
                return false;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'wait';
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '⏳ Đang xử lý...';

                setTimeout(function () {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.innerHTML = originalText;
                }, 8000);
            }
        });
    });
});