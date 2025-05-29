<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }
if (!isset($_SESSION['admin_id'])) { header("Location: index.php?page=admin_login"); exit; }

if (!isset($_GET['id'])) { header("Location: index.php?page=admin_helpdesk"); exit; }

$ticket_db_id = (int)$_GET['id'];
$admin_id_current_session = $_SESSION['admin_id'];

// กำหนดค่า UPLOAD_DIR (ควรจะมาจาก config กลาง)
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', 'uploads/ticket_attachments/');
}

// ดึงข้อมูล Ticket และ Replies
try {
    // เพิ่ม s.id as student_db_id เพื่อใช้สร้างลิงก์ไปยัง view_user
    $query_ticket = "SELECT t.*, s.id as student_db_id, s.student_id as student_code, s.firstname, s.lastname, s.email as student_email, a.name as assigned_admin_name
                     FROM helpdesk_tickets t
                     JOIN students s ON t.student_id = s.id
                     LEFT JOIN admins a ON t.admin_id = a.id
                     WHERE t.id = :id";
    $stmt_ticket = $db->prepare($query_ticket);
    $stmt_ticket->bindParam(':id', $ticket_db_id);
    $stmt_ticket->execute();

    if ($stmt_ticket->rowCount() == 0) { throw new Exception("ไม่พบ Ticket"); }
    $ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

    if (empty($ticket['admin_id']) && $ticket['status'] == 'Open') {
        $claim_query = "UPDATE helpdesk_tickets SET admin_id = :admin_id, status = 'In Progress', updated_at = NOW() WHERE id = :id AND admin_id IS NULL";
        $claim_stmt = $db->prepare($claim_query);
        $claim_stmt->bindParam(':admin_id', $admin_id_current_session);
        $claim_stmt->bindParam(':id', $ticket_db_id);
        if ($claim_stmt->execute()) {
            $stmt_ticket->execute();
            $ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);
            $_SESSION['success_message'] = 'คุณได้รับ Ticket นี้เข้าระบบแล้ว และสถานะถูกเปลี่ยนเป็น "In Progress"';
        } else {
             $_SESSION['error_message'] = 'ไม่สามารถรับ Ticket นี้ได้ กรุณาลองใหม่';
        }
    }

    $query_replies = "SELECT r.*, s.firstname as student_fname, s.lastname as student_lname, a.name as reply_admin_name
                      FROM helpdesk_replies r
                      LEFT JOIN students s ON r.user_id = s.id AND r.user_type = 'student'
                      LEFT JOIN admins a ON r.user_id = a.id AND r.user_type = 'admin'
                      WHERE r.ticket_id = :ticket_id ORDER BY r.created_at ASC";
    $stmt_replies = $db->prepare($query_replies);
    $stmt_replies->bindParam(':ticket_id', $ticket_db_id);
    $stmt_replies->execute();
    $replies = $stmt_replies->fetchAll(PDO::FETCH_ASSOC);

    $rating_info = null;
    if ($ticket['status'] == 'Closed') {
        $query_rating_info = "SELECT * FROM helpdesk_ratings WHERE ticket_id = :ticket_id";
        $stmt_rating_info = $db->prepare($query_rating_info);
        $stmt_rating_info->bindParam(':ticket_id', $ticket_db_id);
        $stmt_rating_info->execute();
        $rating_info = $stmt_rating_info->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_helpdesk");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_reply_message'])) {
    $message = $database->sanitize($_POST['admin_reply_message']);
    $new_status = $database->sanitize($_POST['status_update']);

    if (!empty($message) && $ticket['status'] != 'Closed') {
        try {
            $db->beginTransaction();

            $insert_query = "INSERT INTO helpdesk_replies (ticket_id, user_id, user_type, message, created_at) VALUES (:ticket_id, :user_id, 'admin', :message, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':ticket_id', $ticket_db_id);
            $insert_stmt->bindParam(':user_id', $admin_id_current_session);
            $insert_stmt->bindParam(':message', $message);
            $insert_stmt->execute();

            $update_sql = "UPDATE helpdesk_tickets SET status = :status, admin_id = :admin_id, updated_at = NOW()";
            if ($new_status == 'Closed') {
                 $update_sql .= ", closed_at = NOW()";
            }
            $update_sql .= " WHERE id = :id";

            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bindParam(':status', $new_status);
            $update_stmt->bindParam(':admin_id', $admin_id_current_session);
            $update_stmt->bindParam(':id', $ticket_db_id);
            $update_stmt->execute();

            $db->commit();
            $_SESSION['success_message'] = 'ส่งข้อความตอบกลับและอัปเดตสถานะ Ticket สำเร็จ';
            header("Location: ?page=admin_helpdesk_view&id=" . $ticket_db_id);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error_message_form'] = "เกิดข้อผิดพลาดในการตอบกลับ: " . $e->getMessage();
        }
    } elseif (empty($message)){
        $_SESSION['error_message_form'] = "กรุณาพิมพ์ข้อความตอบกลับ";
    }
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
function getPriorityBadge($priority) {
    switch ($priority) {
        case 'Low': return 'bg-light text-dark';
        case 'Medium': return 'bg-warning text-dark';
        case 'High': return 'bg-danger';
        case 'Critical': return 'bg-dark';
        default: return 'bg-secondary';
    }
}
?>

<style>
    .chat-bubble { max-width: 75%; padding: 10px 15px; border-radius: 20px; margin-bottom: 10px; word-wrap: break-word; }
    .chat-bubble.student { background-color: #e9f5ff; color: #333; margin-right: auto; border-bottom-left-radius: 5px;}
    .chat-bubble.admin { background-color: #d4edda; color: #155724; margin-left: auto; border-bottom-right-radius: 5px;}
    .chat-meta { font-size: 0.75rem; color: #888; margin-top: 5px; }
    .ticket-attachment img {
        max-width: 100%;
        max-height: 300px;
        height: auto;
        border-radius: 5px;
        margin-top: 10px;
        border: 1px solid #ddd;
        padding: 5px;
        cursor: pointer;
    }
     .attachment-link { display: block; margin-top: 5px; font-size: 0.9em; }
    .rating-display .fa-star { color: #f5c518; }
    .rating-display .far.fa-star { color: #ddd; }
</style>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Ticket ID: <?php echo htmlspecialchars($ticket['ticket_id']); ?></h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_helpdesk" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>กลับไปรายการ Ticket</a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message_form'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message_form']; unset($_SESSION['error_message_form']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-ticket-alt me-2"></i>รายละเอียด Ticket</h5></div>
            <div class="card-body">
                <p><strong>รหัสนักศึกษา:</strong> <?php echo htmlspecialchars($ticket['student_code']); ?></p>
                <p><strong>ผู้แจ้ง:</strong> <?php echo htmlspecialchars($ticket['firstname'] . ' ' . $ticket['lastname']); ?></p>
                <p><strong>อีเมลนักศึกษา:</strong> <?php echo htmlspecialchars($ticket['student_email']); ?>
                    <?php if (!empty($ticket['student_db_id'])): ?>
                        <a href="?page=admin_view_user&id=<?php echo base64_encode($ticket['student_db_id']); ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-outline-info ms-2 py-0 px-1" 
                           title="ดูข้อมูลนักศึกษา <?php echo htmlspecialchars($ticket['firstname'] . ' ' . $ticket['lastname']); ?>">
                            <i class="fas fa-user"></i> <span class="visually-hidden">ดูโปรไฟล์</span>
                        </a>
                    <?php endif; ?>
                </p>
                <hr>
                <p><strong>หัวข้อ:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
                <p><strong>สถานะ:</strong> <span class="badge <?php echo getStatusBadge($ticket['status']); ?> fs-6"><?php echo htmlspecialchars($ticket['status']); ?></span></p>
                <p><strong>หมวดหมู่:</strong> <?php echo htmlspecialchars($ticket['category']); ?></p>
                <p><strong>ความสำคัญ:</strong> <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>"><?php echo htmlspecialchars($ticket['priority']); ?></span></p>
                <p><strong>ผู้รับผิดชอบ:</strong> <?php echo htmlspecialchars($ticket['assigned_admin_name'] ?: 'ยังไม่ได้มอบหมาย'); ?></p>
                <p><strong>วันที่แจ้ง:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
                <p><strong>อัปเดตล่าสุด:</strong> <?php echo $ticket['updated_at'] ? date('d/m/Y H:i', strtotime($ticket['updated_at'])) : date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
                <?php if($ticket['status'] == 'Closed' && $ticket['closed_at']): ?>
                    <p><strong>วันที่ปิด:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['closed_at'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($rating_info): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark"><h5 class="card-title mb-0"><i class="fas fa-star me-2"></i>การให้คะแนนจากผู้ใช้</h5></div>
            <div class="card-body">
                <div class="rating-display mb-2">
                    <strong>คะแนน:</strong>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="<?php echo ($i <= $rating_info['rating']) ? 'fas' : 'far'; ?> fa-star"></i>
                    <?php endfor; ?>
                    (<?php echo $rating_info['rating']; ?>/5)
                </div>
                <p><strong>ความคิดเห็น:</strong> <?php echo !empty($rating_info['comment']) ? nl2br(htmlspecialchars($rating_info['comment'])) : '<em>ไม่มีความคิดเห็น</em>'; ?></p>
                <small class="text-muted">ให้คะแนนเมื่อ: <?php echo date('d/m/Y H:i', strtotime($rating_info['created_at'])); ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-comments me-2"></i>การสนทนา</h5></div>
             <div class="card-body" style="max-height: 500px; overflow-y: auto;" id="chatHistoryAdmin">
                <div class="chat-bubble student">
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                     
                    <?php if (!empty($ticket['attachment_path'])): ?>
                        <div class="ticket-attachment">
                             <a href="<?php echo htmlspecialchars(UPLOAD_DIR . $ticket['attachment_path']); ?>" target="_blank" class="attachment-link">
                                <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($ticket['attachment_filename'] ?: 'ดูไฟล์แนบ'); ?>
                            </a>
                            <img src="<?php echo htmlspecialchars(UPLOAD_DIR . $ticket['attachment_path']); ?>" alt="<?php echo htmlspecialchars($ticket['attachment_filename']); ?>" onclick="openImageModal('<?php echo htmlspecialchars(UPLOAD_DIR . $ticket['attachment_path']); ?>')">
                        </div>
                    <?php endif; ?>

                    <div class="chat-meta text-start">นักศึกษา (<?php echo htmlspecialchars($ticket['firstname']); ?>) - <?php echo date('d/m/y H:i', strtotime($ticket['created_at'])); ?></div>
                </div>

                <?php foreach ($replies as $reply): ?>
                    <div class="chat-bubble <?php echo $reply['user_type']; ?>">
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                        <div class="chat-meta <?php echo $reply['user_type'] == 'admin' ? 'text-end' : 'text-start'; ?>">
                            <?php echo $reply['user_type'] == 'admin' ? 'คุณ (' . htmlspecialchars($reply['reply_admin_name'] ?: 'Admin') . ')' : 'นักศึกษา (' . htmlspecialchars($reply['student_fname']) . ')'; ?>
                             - <?php echo date('d/m/y H:i', strtotime($reply['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($ticket['status'] != 'Closed'): ?>
            <div class="card-footer">
                <form action="?page=admin_helpdesk_view&id=<?php echo $ticket_db_id; ?>" method="post">
                     <div class="mb-3">
                        <label for="admin_reply_message" class="form-label">ข้อความตอบกลับ:</label>
                        <textarea name="admin_reply_message" id="admin_reply_message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                             <label for="status_update" class="form-label">อัปเดตสถานะ Ticket:</label>
                             <select name="status_update" id="status_update" class="form-select">
                                <option value="Open" <?php if($ticket['status'] == 'Open') echo 'selected'; ?>>Open</option>
                                <option value="In Progress" <?php if($ticket['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                                <option value="Answered" <?php if($ticket['status'] == 'Answered' || ($ticket['status'] != 'Closed' && $ticket['status'] != 'In Progress' && $ticket['status'] != 'Open' )) echo 'selected'; ?>>Answered</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                         <div class="col-md-5 d-flex align-items-end mb-3">
                             <button class="btn btn-primary w-100" type="submit"><i class="fas fa-paper-plane me-2"></i>ส่งและอัปเดต</button>
                         </div>
                    </div>
                </form>
            </div>
             <?php else: ?>
                <div class="card-footer text-center text-muted">
                    Ticket นี้ถูกปิดแล้ว <?php if($ticket['closed_at']) echo 'เมื่อ '.date('d/m/Y H:i', strtotime($ticket['closed_at'])); ?> โดย <?php echo htmlspecialchars($ticket['assigned_admin_name'] ?: 'Admin'); ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModalAdmin" tabindex="-1" aria-labelledby="imagePreviewModalAdminLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imagePreviewModalAdminLabel">ดูรูปภาพ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalImageAdmin" class="img-fluid" alt="Preview">
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatHistoryAdmin = document.getElementById('chatHistoryAdmin');
    if (chatHistoryAdmin) {
        chatHistoryAdmin.scrollTop = chatHistoryAdmin.scrollHeight;
    }
});

function openImageModal(imageSrc) {
    document.getElementById('modalImageAdmin').src = imageSrc;
    var imageModal = new bootstrap.Modal(document.getElementById('imagePreviewModalAdmin'));
    imageModal.show();
}
</script>
