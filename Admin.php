<?php
session_start();

// التحقق من أن المستخدم هو موظف
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

// بيانات الاتصال بقاعدة البيانات
$servername = "localhost";
$username_db = "root";
$password_db = "Mmina@12345";
$dbname = "AI_Management";

// إنشاء اتصال بقاعدة البيانات
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// التحقق من نجاح الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// دالة لتنفيذ الاستعلامات بأمان باستخدام Prepared Statements
function safeQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// الحصول على معلومات الموظف من قاعدة البيانات
$staffName = "Staff Member"; // قيمة افتراضية
$staffPosition = "Unknown";
$staffHireDate = "N/A";

if (isset($_SESSION['id'])) {
    $staff_id = $_SESSION['id'];
    $result = safeQuery($conn, "SELECT name, position, hire_date FROM staff WHERE staff_id = ?", [$staff_id]);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $staffName = htmlspecialchars($row['name']);
        $staffPosition = htmlspecialchars($row['position']);
        $staffHireDate = htmlspecialchars($row['hire_date']);
    }
}

// الحصول على الإحصائيات من قاعدة البيانات
$studentCount = safeQuery($conn, "SELECT COUNT(*) as count FROM Students")->fetch_assoc()['count'] ?? 0;
$courseCount = safeQuery($conn, "SELECT COUNT(*) as count FROM Courses")->fetch_assoc()['count'] ?? 0;
$paymentCount = 0; // يمكنك تعديل هذا الجزء إذا كنت تملك جدولًا للدفع
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | AI Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="v.css">
</head>
<body>
    <header>
        <h1>AI Management System</h1>
        <p>Staff Dashboard for Database Administration</p>
    </header>
    <div class="container">
        <div class="user-welcome">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="welcome-text">
                <h2>Welcome, <?php echo $staffName; ?>!</h2>
                <p>Position: <?php echo $staffPosition; ?></p>
                <p>Hire Date: <?php echo $staffHireDate; ?></p>
                <p>Access and manage all system resources from this dashboard.</p>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-box">
                <i class="fas fa-user-graduate fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo $studentCount; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-book fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo $courseCount; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-money-bill-wave fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo number_format($paymentCount, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <div class="staff-dashboard">
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Student Management
                </div>
                <div class="card-body">
                    <a href="view_students.php" class="dashboard-link">
                        <i class="fas fa-list"></i> View All Students
                    </a>
                    <a href="add_student.php" class="dashboard-link">
                        <i class="fas fa-user-plus"></i> Add New Student
                    </a>
                    <a href="student_attendance.php" class="dashboard-link">
                        <i class="fas fa-clipboard-check"></i> Student Attendance
                    </a>
                    <a href="student_progress.php" class="dashboard-link">
                        <i class="fas fa-chart-line"></i> Progress Reports
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-book"></i> Course Management
                </div>
                <div class="card-body">
                    <a href="view_courses.php" class="dashboard-link">
                        <i class="fas fa-list"></i> View All Courses
                    </a>
                    <a href="add_course.php" class="dashboard-link">
                        <i class="fas fa-plus-circle"></i> Add New Course
                    </a>
                    <a href="course_schedule.php" class="dashboard-link">
                        <i class="fas fa-calendar-alt"></i> Course Schedule
                    </a>
                    <a href="course_materials.php" class="dashboard-link">
                        <i class="fas fa-file-alt"></i> Course Materials
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-money-bill-wave"></i> Financial Management
                </div>
                <div class="card-body">
                    <a href="view_finances.php" class="dashboard-link">
                        <i class="fas fa-list"></i> View Financial Records
                    </a>
                    <a href="payments.php" class="dashboard-link">
                        <i class="fas fa-credit-card"></i> Process Payments
                    </a>
                    <a href="invoices.php" class="dashboard-link">
                        <i class="fas fa-file-invoice-dollar"></i> Generate Invoices
                    </a>
                    <a href="financial_reports.php" class="dashboard-link">
                        <i class="fas fa-chart-pie"></i> Financial Reports
                    </a>
                </div>
            </div>
        </div>

        <div class="logout-section">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    <!-- WhatsApp Support -->
    <div class="support-section">
        <div class="whatsapp-icon" id="whatsappIcon">
            <i class="fab fa-whatsapp"></i>
        </div>
        <div class="team-list" id="teamList">
            <h3>Support Team</h3>
            <div class="team-member" onclick="openWhatsApp('+201032286185', 'Technical Support')">
                <div class="img">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">Technical Support</div>
                    <div class="team-member-role">System Issues</div>
                </div>
                <div class="team-member-status"></div>
            </div>
            <div class="team-member" onclick="openWhatsApp('+201222746697', 'Student Affairs')">
                <div class="img">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">Student Affairs</div>
                    <div class="team-member-role">Registration & Admin</div>
                </div>
                <div class="team-member-status"></div>
            </div>
            <div class="team-member" onclick="openWhatsApp('+201140650201', 'IT Department')">
                <div class="img">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">IT Department</div>
                    <div class="team-member-role">Account Issues</div>
                </div>
                <div class="team-member-status"></div>
            </div>
            <div class="team-member" onclick="openWhatsApp('1234567893', 'Financial Aid')">
                <div class="img">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">Financial Aid</div>
                    <div class="team-member-role">Payments & Fees</div>
                </div>
                <div class="team-member-status"></div>
            </div>
        </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Dashboard loaded: ' + new Date().toLocaleDateString());

        // التأكد من وجود العنصر قبل إضافة المستمع
        const whatsappIcon = document.getElementById('whatsappIcon');
        const teamList = document.getElementById('teamList');

        if (whatsappIcon && teamList) {
            whatsappIcon.addEventListener('click', function() {
                teamList.classList.toggle('active');
            });
        }
    });

    // فتح محادثة واتساب
    function openWhatsApp(phoneNumber, memberName) {
        const message = `Hello ${memberName}, I need assistance with the Smart College Management System.`;
        const url = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
        window.open(url, '_blank');
    }
</script>


</body>
</html>
