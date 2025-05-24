// Custom JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // เพิ่ม active class ให้กับ navbar ตาม URL ปัจจุบัน
    const currentUrl = window.location.href;
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentUrl.includes(href) && href !== 'index.php') {
            link.classList.add('active');
        }
    });
    
    // Timeout สำหรับการแสดง Alert (เฉพาะ alert ที่ไม่ใช่ alert ถาวร)
    // แก้ไข: ไม่ให้ alert แบบ alert-info หายไปอัตโนมัติ
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent):not(.alert-info)');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                // ตรวจสอบว่า alert ยังอยู่ในหน้าเว็บหรือไม่ก่อนที่จะปิด
                if (alert && alert.parentNode) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    }
    
    // Timeout สำหรับ alert ประเภท success และ warning เท่านั้น
    const autoCloseAlerts = document.querySelectorAll('.alert.alert-success, .alert.alert-warning, .alert.alert-danger');
    if (autoCloseAlerts.length > 0) {
        autoCloseAlerts.forEach(alert => {
            // เฉพาะ alert ที่ไม่มี class "alert-permanent" เท่านั้นที่จะหายไปอัตโนมัติ
            if (!alert.classList.contains('alert-permanent')) {
                setTimeout(() => {
                    if (alert && alert.parentNode) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            }
        });
    }
    
    // ตรวจสอบและแสดง Error message จาก session (ถ้ามี)
    if (typeof sessionErrorMessage !== 'undefined' && sessionErrorMessage) {
        Swal.fire({
            title: 'ข้อผิดพลาด!',
            text: sessionErrorMessage,
            icon: 'error',
            confirmButtonText: 'ตกลง'
        });
    }
    
    // ตรวจสอบการกรอกรหัสบัตรประชาชน
    const idCardInputs = document.querySelectorAll('input[name="id_card"], input[name="new_id_card"]');
    idCardInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            // อนุญาตเฉพาะตัวเลขเท่านั้น
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // จำกัดความยาวไม่เกิน 13 หลัก
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
    });
    
    // Bootstrap Tooltip
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// ฟังก์ชันสำหรับการ confirm ก่อนลบข้อมูล
function confirmDelete(message = 'คุณแน่ใจหรือไม่ที่จะลบรายการนี้?') {
    return Swal.fire({
        title: 'ยืนยันการลบ',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        return result.isConfirmed;
    });
}