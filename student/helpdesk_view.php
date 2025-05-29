<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }
if (!isset($_SESSION['student_id'])) { header("Location: index.php?page=student_login"); exit; }

if (!isset($_GET['id'])) { header("Location: index.php?page=helpdesk"); exit; }

$ticket_db_id = (int)$_GET['id'];

// กำหนดค่า UPLOAD_DIR หากยังไม่ได้ define (อาจจะ include จาก helpdesk_create.php หรือ config กลาง)
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', 'uploads/ticket_attachments/');
}

// ดึงข้อมูล Ticket และ Replies
try {
    // ดึงข้อมูล Ticket, ชื่อผู้แจ้ง, และข้อมูลไฟล์แนบ
    $query_ticket = "SELECT t.*, s.firstname, s.lastname 
                     FROM helpdesk_tickets t 
                     JOIN students s ON t.student_id = s.id 
                     WHERE t.id = :id AND t.student_id = :student_id";
    $stmt_ticket = $db->prepare($query_ticket);
    $stmt_ticket->bindParam(':id', $ticket_db_id);
    $stmt_ticket->bindParam(':student_id', $_SESSION['student_id']);
    $stmt_ticket->execute();

    if ($stmt_ticket->rowCount() == 0) {
        throw new Exception("ไม่พบ Ticket หรือคุณไม่มีสิทธิ์เข้าถึง");
    }
    $ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

    // ดึงข้อมูล Replies
    $query_replies = "SELECT r.*, s.firstname as student_fname, s.lastname as student_lname, a.name as admin_name
                      FROM helpdesk_replies r
                      LEFT JOIN students s ON r.user_id = s.id AND r.user_type = 'student'
                      LEFT JOIN admins a ON r.user_id = a.id AND r.user_type = 'admin'
                      WHERE r.ticket_id = :ticket_id ORDER BY r.created_at ASC";
    $stmt_replies = $db->prepare($query_replies);
    $stmt_replies->bindParam(':ticket_id', $ticket_db_id);
    $stmt_replies->execute();
    $replies = $stmt_replies->fetchAll(PDO::FETCH_ASSOC);

    // ตรวจสอบว่าเคยให้คะแนนหรือยัง
    $query_rating = "SELECT id FROM helpdesk_ratings WHERE ticket_id = :ticket_id AND student_id = :student_id";
    $stmt_rating = $db->prepare($query_rating);
    $stmt_rating->bindParam(':ticket_id', $ticket_db_id);
    $stmt_rating->bindParam(':student_id', $_SESSION['student_id']);
    $stmt_rating->execute();
    $has_rated = $stmt_rating->rowCount() > 0;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=helpdesk");
    exit;
}

// จัดการการตอบกลับ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_message'])) {
    $message = $database->sanitize($_POST['reply_message']);
    if (!empty($message) && $ticket['status'] != 'Closed') {
        try {
            $insert_query = "INSERT INTO helpdesk_replies (ticket_id, user_id, user_type, message, created_at) VALUES (:ticket_id, :user_id, 'student', :message, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':ticket_id', $ticket_db_id);
            $insert_stmt->bindParam(':user_id', $_SESSION['student_id']);
            $insert_stmt->bindParam(':message', $message);
            $insert_stmt->execute();

            // อัพเดทสถานะ Ticket เป็น 'Open' หรือ 'Answered' (ถ้าก่อนหน้าเป็น 'In Progress' โดย Admin)
            // เพื่อให้แอดมินรู้ว่ามีการตอบกลับ หรือเปลี่ยนเป็น 'Open' หากนักศึกษาตอบกลับหลังจาก 'Answered'
            $new_status_after_reply = ($ticket['status'] == 'Answered') ? 'Open' : 'Open'; // หรืออาจจะใช้ 'Answered by Student'
            if($ticket['status'] != 'Open'){ // ถ้าสถานะเดิมไม่ใช่ Open ค่อยอัปเดต
                 $update_query = "UPDATE helpdesk_tickets SET status = :status, updated_at = NOW() WHERE id = :id";
                 $update_stmt = $db->prepare($update_query);
                 $update_stmt->bindParam(':status', $new_status_after_reply);
                 $update_stmt->bindParam(':id', $ticket_db_id);
                 $update_stmt->execute();
            }


            $_SESSION['success_message'] = 'ส่งข้อความตอบกลับสำเร็จ';
            header("Location: ?page=helpdesk_view&id=" . $ticket_db_id);
            exit;
        } catch (Exception $e) {
            $error_message = "ไม่สามารถส่งข้อความได้: " . $e->getMessage();
        }
    }
}

// จัดการการให้คะแนน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rating'])) {
    $rating = (int)$_POST['rating'];
    $comment = $database->sanitize($_POST['comment']);

    if ($rating >= 1 && $rating <= 5 && !$has_rated && $ticket['status'] == 'Closed') {
        try {
            $rate_query = "INSERT INTO helpdesk_ratings (ticket_id, student_id, rating, comment, created_at) VALUES (:ticket_id, :student_id, :rating, :comment, NOW())";
            $rate_stmt = $db->prepare($rate_query);
            $rate_stmt->bindParam(':ticket_id', $ticket_db_id);
            $rate_stmt->bindParam(':student_id', $_SESSION['student_id']);
            $rate_stmt->bindParam(':rating', $rating);
            $rate_stmt->bindParam(':comment', $comment);
            $rate_stmt->execute();
            $has_rated = true; // อัปเดตสถานะการให้คะแนน
            $_SESSION['success_message'] = "ขอบคุณสำหรับการให้คะแนน!"; // ใช้ session message
            header("Location: ?page=helpdesk_view&id=" . $ticket_db_id); // รีเฟรชหน้า
            exit;
        } catch (Exception $e) {
            $error_message = "ไม่สามารถบันทึกคะแนนได้: " . $e->getMessage();
        }
    } elseif ($has_rated) {
        $error_message = "คุณได้ให้คะแนนสำหรับ Ticket นี้ไปแล้ว";
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
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
    .chat-bubble { max-width: 75%; padding: 10px 15px; border-radius: 20px; margin-bottom: 10px; word-wrap: break-word; }
    .chat-bubble.student { background-color: #e9f5ff; color: #333; margin-left: auto; border-bottom-right-radius: 5px;}
    .chat-bubble.admin { background-color: #f1f0f0; color: #333; margin-right: auto; border-bottom-left-radius: 5px;}
    .chat-meta { font-size: 0.75rem; color: #888; margin-top: 5px; }
    .rating-stars .fa-star { font-size: 1.5rem; color: #ddd; cursor: pointer; transition: color 0.2s; }
    .rating-stars .fa-star.selected, .rating-stars .fa-star:hover { color: #f5c518; }
    .ticket-attachment img {
        max-width: 100%;
        max-height: 300px; /* จำกัดความสูงสูงสุดของรูปภาพที่แสดงผล */
        height: auto;
        border-radius: 5px;
        margin-top: 10px;
        border: 1px solid #ddd;
        padding: 5px;
        cursor: pointer; /* เพิ่ม cursor pointer เพื่อให้รู้ว่าคลิกได้ */
    }
    .attachment-link { display: block; margin-top: 5px; font-size: 0.9em; }
</style>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Ticket ID: <?php echo htmlspecialchars($ticket['ticket_id']); ?></h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=helpdesk" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>กลับไปรายการ Ticket</a>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header"><h5 class="card-title mb-0">รายละเอียด Ticket</h5></div>
            <div class="card-body">
                <p><strong>หัวข้อ:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
                <p><strong>สถานะ:</strong> <span class="badge <?php echo getStatusBadge($ticket['status']); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></p>
                <p><strong>หมวดหมู่:</strong> <?php echo htmlspecialchars($ticket['category']); ?></p>
                <p><strong>ความสำคัญ:</strong> <?php echo htmlspecialchars($ticket['priority']); ?></p>
                <p><strong>วันที่แจ้ง:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
                <p><strong>อัปเดตล่าสุด:</strong> <?php echo $ticket['updated_at'] ? date('d/m/Y H:i', strtotime($ticket['updated_at'])) : date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header"><h5 class="card-title mb-0">ประวัติการสนทนา</h5></div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;" id="chatHistory">
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

                    <div class="chat-meta text-end">คุณ (<?php echo htmlspecialchars($ticket['firstname']); ?>) - <?php echo date('d/m/y H:i', strtotime($ticket['created_at'])); ?></div>
                </div>

                <?php foreach ($replies as $reply): ?>
                    <div class="chat-bubble <?php echo $reply['user_type']; ?>">
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                        <div class="chat-meta <?php echo $reply['user_type'] == 'student' ? 'text-end' : 'text-start'; ?>">
                            <?php echo $reply['user_type'] == 'student' ? 'คุณ (' . htmlspecialchars($reply['student_fname']) . ')' : 'ผู้ดูแลระบบ (' . htmlspecialchars($reply['admin_name']) . ')'; ?>
                             - <?php echo date('d/m/y H:i', strtotime($reply['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($ticket['status'] != 'Closed'): ?>
            <div class="card-footer">
                <form action="?page=helpdesk_view&id=<?php echo $ticket_db_id; ?>" method="post">
                    <div class="input-group">
                        <textarea name="reply_message" class="form-control" placeholder="พิมพ์ข้อความตอบกลับ..." rows="3" required></textarea>
                        <button class="btn btn-primary" type="submit" title="ส่งข้อความ"><i class="fas fa-paper-plane"></i> ส่ง</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="card-footer text-center text-muted">Ticket นี้ถูกปิดแล้ว <?php if($ticket['closed_at']) echo 'เมื่อ '.date('d/m/Y H:i', strtotime($ticket['closed_at'])); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($ticket['status'] == 'Closed' && !$has_rated): ?>
        <div class="card shadow">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-star me-2"></i>ให้คะแนนความพึงพอใจ</h5></div>
            <div class="card-body text-center">
                <form action="?page=helpdesk_view&id=<?php echo $ticket_db_id; ?>" method="post">
                    <div class="rating-stars mb-3">
                        <i class="far fa-star" data-value="1" title="แย่มาก"></i>
                        <i class="far fa-star" data-value="2" title="แย่"></i>
                        <i class="far fa-star" data-value="3" title="พอใช้"></i>
                        <i class="far fa-star" data-value="4" title="ดี"></i>
                        <i class="far fa-star" data-value="5" title="ดีมาก"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" required>
                    <div class="mb-3">
                        <textarea name="comment" class="form-control" placeholder="ความคิดเห็นเพิ่มเติม (ถ้ามี)" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-2"></i>ส่งคะแนน</button>
                </form>
            </div>
        </div>
        <?php elseif ($ticket['status'] == 'Closed' && $has_rated): ?>
        <div class="alert alert-info text-center"><i class="fas fa-check-circle me-2"></i>คุณได้ให้คะแนนสำหรับ Ticket นี้แล้ว ขอบคุณครับ/ค่ะ</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imagePreviewModalLabel">ดูรูปภาพ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalImage" class="img-fluid" alt="Preview">
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto scroll chat history to bottom
    const chatHistory = document.getElementById('chatHistory');
    if (chatHistory) {
        chatHistory.scrollTop = chatHistory.scrollHeight;
    }

    const stars = document.querySelectorAll('.rating-stars .fa-star');
    const ratingInput = document.getElementById('ratingValue');

    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            if (ratingInput.value === '') { // Only highlight on hover if not clicked yet
                resetStarsVisual();
                const val = this.dataset.value;
                for(let i = 0; i < val; i++) {
                    stars[i].classList.add('selected');
                }
            }
        });
        star.addEventListener('mouseout', function() {
            if (ratingInput.value === '') { // Only reset hover if not clicked yet
                 resetStarsVisual();
            }
        });
        star.addEventListener('click', function() {
            const clickedValue = this.dataset.value;
            ratingInput.value = clickedValue;
            stars.forEach((s, index) => {
                if (index < clickedValue) {
                    s.classList.add('selected');
                    s.classList.replace('far', 'fas');
                } else {
                    s.classList.remove('selected');
                    s.classList.replace('fas', 'far');
                }
            });
        });
    });

    function resetStarsVisual() {
        // This function is for hover effect only if no star is clicked
        const currentRating = ratingInput.value || 0;
        stars.forEach((star, index) => {
            if (index < currentRating) {
                star.classList.add('selected');
                 star.classList.replace('far', 'fas');
            } else {
                star.classList.remove('selected');
                star.classList.replace('fas', 'far');
            }
        });
    }
});

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    var imageModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    imageModal.show();
}
</script>
