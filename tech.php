<?php
session_start();

// User authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff' || $_SESSION['staff_type'] !== 'teach') {
    header("Location: login.php");
    exit();
}

// Database connection details
$servername = "localhost";
$username_db = "root";
$password_db = "Mmina@12345";
$dbname = "AI_Management";

// Establish database connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection success
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate session ID
if (!isset($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    die("Error: Invalid session ID.");
}

$facultyID = (int)$_SESSION['id']; // Convert to integer

// Function to execute queries safely
function safeQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('i', count($params)); // All values are integers
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Retrieve faculty member data
$facultyData = safeQuery($conn, "SELECT FirstName, LastName, Department, OfficeHours, FacultyRank FROM Faculty WHERE FacultyID = ?", [$facultyID])->fetch_assoc();

$facultyName = $facultyData ? htmlspecialchars($facultyData['FirstName'] . ' ' . $facultyData['LastName']) : 'Faculty Member';
$userRole = $facultyData['FacultyRank'] ?? 'Professor';
$department = $facultyData['Department'] ?? 'Not Specified';
$officeHours = $facultyData['OfficeHours'] ?? 'Not Available';

// User statistics
$myCourses = safeQuery($conn, "SELECT COUNT(*) as count FROM Courses WHERE Instructor = ?", [$facultyID])->fetch_assoc()['count'] ?? 0;
$myStudents = safeQuery($conn, "SELECT COUNT(DISTINCT r.StudentID) as count FROM Registration r
                                JOIN Courses c ON r.CourseID = c.CourseID
                                WHERE c.Instructor = ?", [$facultyID])->fetch_assoc()['count'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_notification'])) {
    $title = $_POST['notification_title'] ?? '';
    $content = $_POST['notification_content'] ?? '';
    $target_level = $_POST['target_level'] ?? 'all';
    $notification_type = $_POST['notification_type'] ?? 'general';
    $expiry_date = $_POST['expiry_date'] ?? NULL;

    if (!empty($title) && !empty($content)) {
        $sql = "INSERT INTO Notifications 
                (Title, Content, SenderID, SenderType, TargetLevel, NotificationType, ExpiryDate) 
                VALUES (?, ?, ?, 'faculty', ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssisss', 
            $title, 
            $content, 
            $facultyID, 
            $target_level, 
            $notification_type, 
            $expiry_date
        );

        if ($stmt->execute()) {
            $notification_success = "تم إضافة الإشعار بنجاح";
        } else {
            $notification_error = "حدث خطأ أثناء إضافة الإشعار: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="v.css">
</head>
<body>
    <header>
        <h1>Learning Management System</h1>
        <p>Faculty Dashboard</p>
    </header>

    <div class="container">
        <div class="user-welcome">
            <div class="user-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="welcome-text">
                <h2>Welcome, <?php echo $facultyName; ?>!</h2>
                <p><?php echo $userRole; ?> at the Learning Management System</p>
                <p>Department: <?php echo $department; ?></p>
                <p>Office Hours: <?php echo $officeHours; ?></p>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-box">
                <i class="fas fa-book fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo $myCourses; ?></div>
                <div class="stat-label">My Courses</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-user-graduate fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo $myStudents; ?></div>
                <div class="stat-label">Enrolled Students</div>
            </div>
        </div>

        <div class="staff-dashboard">
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-book-open"></i> Course Management
                </div>
                <div class="card-body">
                    <a href="my_courses.php" class="dashboard-link">
                        <i class="fas fa-list"></i> My Courses
                    </a>
                    <a href="upload_materials.php" class="dashboard-link">
                        <i class="fas fa-file-upload"></i> Upload Materials
                    </a>
                    <a href="course_content.php" class="dashboard-link">
                        <i class="fas fa-book-reader"></i> Course Content
                    </a>
                </div>
            </div>
            <div class="dashboard-card">
            <div class="card-header">
                <i class="fas fa-user-graduate"></i> Academic Guidance
            </div>
            <div class="card-body">
                <a href="academic_guidance.php" class="dashboard-link">
                    <i class="fas fa-check-circle"></i> Confirm Student Registrations
                </a>
            </div>
        </div>
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Student Management
                </div>
                <div class="card-body">
                    <a href="student_list.php" class="dashboard-link">
                        <i class="fas fa-list"></i> Student List
                    </a>
                    <a href="attendance.php" class="dashboard-link">
                        <i class="fas fa-clipboard-check"></i> Attendance
                    </a>
                    <a href="grades_entry.php" class="dashboard-link">
                        <i class="fas fa-chart-line"></i> Enter Grades
                    </a>
                    <a href="student_progress.php" class="dashboard-link">
                        <i class="fas fa-graduation-cap"></i> Student Progress
                    </a>
                </div>
            </div>

            <?php if($userRole == 'faculty'): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-tasks"></i> Task Management
                </div>
                <div class="card-body">
                    <a href="exam_creation.php" class="dashboard-link">
                        <i class="fas fa-file-alt"></i> Create Exams
                    </a>
                    <a href="assignment_management.php" class="dashboard-link">
                        <i class="fas fa-pencil-alt"></i> Manage Assignments
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
            <div class="dashboard-card">
        <div class="card-header">
            <i class="fas fa-bell"></i> إدارة الإشعارات
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="notification_title">عنوان الإشعار</label>
                    <input type="text" id="notification_title" name="notification_title" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="notification_content">محتوى الإشعار</label>
                    <textarea id="notification_content" name="notification_content" required class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="target_level">المستوى المستهدف</label>
                    <select id="target_level" name="target_level" class="form-control">
                        <option value="all">الكل</option>
                        <option value="level1">المستوى الأول</option>
                        <option value="level2">المستوى الثاني</option>
                        <option value="level3">المستوى الثالث</option>
                        <option value="level4">المستوى الرابع</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notification_type">نوع الإشعار</label>
                    <select id="notification_type" name="notification_type" class="form-control">
                        <option value="general">عام</option>
                        <option value="academic">أكاديمي</option>
                        <option value="administrative">إداري</option>
                        <option value="urgent">عاجل</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="expiry_date">تاريخ انتهاء الإشعار</label>
                    <input type="datetime-local" id="expiry_date" name="expiry_date" class="form-control">
                </div>
                <button type="submit" name="add_notification" class="btn btn-primary">
                    <i class="fas fa-plus"></i> إضافة إشعار
                </button>
            </form>

            <?php 
            if (isset($notification_success)) {
                echo "<div class='alert alert-success'>$notification_success</div>";
            }
            if (isset($notification_error)) {
                echo "<div class='alert alert-danger'>$notification_error</div>";
            }
            ?>
        </div>
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
        <div class="logout-section">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded: ' + new Date().toLocaleDateString());
        });
        const whatsappIcon = document.getElementById('whatsappIcon');
        const teamList = document.getElementById('teamList');

        if (whatsappIcon && teamList) {
            whatsappIcon.addEventListener('click', function() {
                teamList.classList.toggle('active');
            });
        }
    </script>
    
</body>
</html>
