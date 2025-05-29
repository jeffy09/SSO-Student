<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php?page=student_login");
    exit;
}

try {
    // ดึงข้อมูล Tickets ของนักศึกษาคนนี้
    $query = "SELECT * FROM helpdesk_tickets WHERE student_id = :student_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['student_id']);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

function getStatusBadge($status) {
    switch ($status) {
        case 'Open': return 'bg-success';
        case 'In Progress': return 'bg-info text-dark';
        case 'Answered': return 'bg-primary';
        case 'Closed': return 'bg-secondary';
        default: return 'bg-light text-dark';
    }
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2><i class="fas fa-life-ring me-2"></i>Helpdesk - แจ้งปัญหา</h2>
        <p>ติดตามและจัดการปัญหาการใช้งานของคุณได้ที่นี่</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=helpdesk_create" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>เปิด Ticket ใหม่</a>
    </div>
</div>

<?php if(isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-header">
        <h5 class="card-title mb-0">ประวัติการแจ้งปัญหาของคุณ</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>หัวข้อ</th>
                        <th>หมวดหมู่</th>
                        <th>สถานะ</th>
                        <th>วันที่แจ้ง</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ยังไม่มีประวัติการแจ้งปัญหา</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['ticket_id']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['category']); ?></td>
                                <td><span class="badge <?php echo getStatusBadge($ticket['status']); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></td>
                                <td>
                                    <a href="?page=helpdesk_view&id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> ดูรายละเอียด</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
