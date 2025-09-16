<?php
require_once 'init.php';

$user_id = get_user_id();
require_login();

$csrf_token = generate_csrf_token();

// پردازش AJAX برای تکمیل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_completion'])) {
    header('Content-Type: application/json');
    
    if (!check_csrf_token($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $type = sanitize_input($_POST['type']);
    $id = intval($_POST['id']);
    $completed = intval($_POST['completed']);

    try {
        $table = '';
        if ($type == 'book_chapter') $table = 'book_chapters';
        elseif ($type == 'course_chapter') $table = 'course_chapters';
        elseif ($type == 'task') $table = 'tasks';

        $stmt = $pdo->prepare("UPDATE $table SET completed = ?, completed_date = ? WHERE id = ? AND user_id = ?");
        $completed_date = $completed ? date('Y-m-d') : null;
        $stmt->execute([$completed, $completed_date, $id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'وضعیت بروز شد']);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// دریافت رویدادها برای FullCalendar
try {
    $stmt = $pdo->prepare("
        SELECT 'book_chapter' as type, bc.id, bc.title, bc.study_date as start, b.title as book_title, bc.completed
        FROM book_chapters bc JOIN books b ON bc.book_id = b.id WHERE bc.user_id = ?
        UNION
        SELECT 'course_chapter' as type, cc.id, cc.title, cc.study_date as start, c.title as course_title, cc.completed
        FROM course_chapters cc JOIN courses c ON cc.course_id = c.id WHERE cc.user_id = ?
        UNION
        SELECT 'task' as type, t.id, t.title, t.due_date as start, '' as extra, t.completed
        FROM tasks t WHERE t.user_id = ?
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "خطا در دریافت رویدادها: " . $e->getMessage();
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقویم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fa.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Vazirmatn', sans-serif;
        }
        #calendar {
            max-width: 900px;
            margin: 0 auto;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            background: white;
        }
        .fc-event {
            border-radius: 5px;
        }
        .fc-event.book {
            background-color: #4e73df;
        }
        .fc-event.course {
            background-color: #1cc88a;
        }
        .fc-event.task {
            background-color: #e74a3b;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2 class="mb-4"><i class="bi bi-calendar-event me-2"></i>تقویم برنامه‌ها</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div id="calendar"></div>
    </div>

    <!-- مودال جزئیات رویداد -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="eventDesc"></p>
                    <form id="toggleForm" method="POST">
                        <input type="hidden" id="toggle_type" name="toggle_completion" value="1">
                        <input type="hidden" id="toggle_id" name="id">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="completed" name="completed">
                            <label class="form-check-label" for="completed">تکمیل شده</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="button" class="btn btn-primary" id="saveToggle">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'fa',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($events as $event): ?>
                    {
                        id: '<?php echo $event['type'] . '_' . $event['id']; ?>',
                        title: '<?php echo htmlspecialchars($event['title']); ?>',
                        start: '<?php echo $event['start']; ?>',
                        classNames: ['<?php echo $event['type']; ?>'],
                        extendedProps: {
                            type: '<?php echo $event['type']; ?>',
                            itemId: '<?php echo $event['id']; ?>',
                            desc: '<?php echo isset($event['book_title']) ? $event['book_title'] : (isset($event['course_title']) ? $event['course_title'] : ''); ?>',
                            completed: <?php echo $event['completed'] ? 'true' : 'false'; ?>
                        }
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    document.getElementById('eventTitle').textContent = info.event.title;
                    document.getElementById('eventDesc').textContent = info.event.extendedProps.desc;
                    document.getElementById('toggle_id').value = info.event.extendedProps.itemId;
                    document.getElementById('toggle_type').name = info.event.extendedProps.type;
                    document.getElementById('completed').checked = info.event.extendedProps.completed;
                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                }
            });
            calendar.render();
            
            // ذخیره تیک تکمیل با AJAX
            document.getElementById('saveToggle').addEventListener('click', function() {
                const formData = new FormData(document.getElementById('toggleForm'));
                formData.append('completed', document.getElementById('completed').checked ? 1 : 0);

                fetch('calendar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.error);
                    }
                });
            });
        });
    </script>
</body>
</html>