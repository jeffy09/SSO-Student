<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }
if (!isset($_SESSION['admin_id'])) { header("Location: index.php?page=admin_login"); exit; }

// การกรองและค้นหา
$status_filter = isset($_GET['status']) ? $database->sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? $database->sanitize($_GET['search']) : '';

try {
    $query = "SELECT t.*, s.firstname, s.lastname FROM helpdesk_tickets t
              JOIN students s ON t.student_id = s.id
              WHERE 1=1";
    $params = [];

    if (!empty($status_filter)) {
        $query .= " AND t.status = :status";
        $params[':status'] = $status_filter;
    }
    if (!empty($search)) {
        $query .= " AND (t.ticket_id LIKE :search OR t.subject LIKE :search OR s.firstname LIKE :search OR s.lastname LIKE :search)";
        $params[':search'] = "%" . $search . "%";
    }

    $query .= " ORDER BY t.status = 'Open' DESC, t.updated_at DESC, t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
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
    <div class="col">
        <h2><i class="fas fa-tasks me-2"></i>จัดการ Helpdesk Tickets</h2>
    </div>
</div>

<?php if(isset($error_message)): ?> <div class="alert alert-danger"><?php echo $error_message; ?></div> <?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="" method="get">
            <input type="hidden" name="page" value="admin_helpdesk">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหา Ticket ID, หัวข้อ, ชื่อ..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                     <select name="status" class="form-select">
                        <option value="">-- ทุกสถานะ --</option>
                        <option value="Open" <?php if($status_filter == 'Open') echo 'selected'; ?>>Open</option>
                        <option value="In Progress" <?php if($status_filter == 'In Progress') echo 'selected'; ?>>In Progress</option>
                        <option value="Answered" <?php if($status_filter == 'Answered') echo 'selected'; ?>>Answered</option>
                        <option value="Closed" <?php if($status_filter == 'Closed') echo 'selected'; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">กรอง</button>
                    <a href="?page=admin_helpdesk" class="btn btn-secondary w-100 mt-2">ล้างตัวกรอง</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>หัวข้อ</th>
                        <th>นักศึกษา</th>
                        <th>สถานะ</th>
                        <th>อัปเดตล่าสุด</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="6" class="text-center">ไม่พบ Tickets</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                             <tr class="<?php if($ticket['status'] == 'Open') echo 'table-success'; ?>">
                                <td><?php echo htmlspecialchars($ticket['ticket_id']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['firstname'] . ' ' . $ticket['lastname']); ?></td>
                                <td><span class="badge <?php echo getStatusBadge($ticket['status']); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'] ?: $ticket['created_at'])); ?></td>
                                <td>
                                    <a href="?page=admin_helpdesk_view&id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> จัดการ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
