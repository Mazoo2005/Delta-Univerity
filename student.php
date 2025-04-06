<?php
session_start();

// التحقق من جلسة المستخدم
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

// الاتصال بقاعدة البيانات
$servername = "localhost"; // اسم السيرفر
$username = "root"; // اسم المستخدم
$password = "Mmina@12345"; // كلمة المرور
$dbname = "ai_management"; // اسم قاعدة البيانات

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// استرجاع بيانات الطالب
$student_id = $_SESSION['id'];
$sql = "SELECT * FROM Students WHERE StudentID = $student_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc(); // بيانات الطالب
} else {
    die("Student not found.");
}

// Define the total hours required for graduation
$total_hours = 142; // Total hours required for graduation

// Get the completed credits (hours) from the database
$completed_credits = $student['CompletedCredits'] ?? 0; // Number of completed hours

// Calculate the progress percentage based on completed credits
$progress_percentage = ($completed_credits / $total_hours) * 100;

// Calculate remaining hours
$remaining_hours = $total_hours - $completed_credits;

// تحويل المستوى الأكاديمي إلى الصيغة الصحيحة
$academic_year = $student['AcademicYear'];
$target_level = 'level' . $academic_year;

// إضافة تصحيح لطباعة قيمة $target_level للتأكد من صحتها
error_log("Target Level: $target_level");

// استرجاع الإشعارات
$notifications_query = "SELECT * FROM Notifications 
                        WHERE (TargetLevel = 'all' OR TargetLevel = '$target_level')
                        AND IsActive = TRUE 
                        AND (ExpiryDate IS NULL OR ExpiryDate > NOW())
                        ORDER BY CreatedAt DESC 
                        LIMIT 5";

// إضافة تصحيح لطباعة الاستعلام للتحقق من صحته
error_log("Notifications Query: $notifications_query");

$notifications_result = $conn->query($notifications_query);
$notification_count = $notifications_result ? $notifications_result->num_rows : 0;

// Profile picture (adjust based on your database or file structure)
$profile_picture = $student['ProfilePicture'];
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>نظام إدارة التعلم</title>
    <link rel="stylesheet" href="main.css">
    <style>
        /* إخفاء جميع الأقسام الداخلية في tab-container */
        .tab-content {
            display: none;
        }
        /* عرض القسم النشط فقط */
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
    <!-- Left Section: Menu Toggle, Logo, and Title -->
    <button class="menu-toggle">☰</button>
    <img src="logo.png" alt="Delta University Logo" class="logo">
    <h1>Student Learning Management System</h1>

    <!-- Right Section: Notifications, Profile, and Dropdown -->
    <div class="header-right">
        <!-- Notification Bell -->
        <div class="notification">
            <span class="notification-icon">🔔</span>
            <?php if ($notification_count > 0): ?>
                <span class="notification-count"><?php echo $notification_count; ?></span>
            <?php endif; ?>
        </div>

        <!-- User Profile with Dropdown -->
        <div class="user-profile" id="userProfile">
            <img src="<?php echo $profile_picture; ?>" alt="User Profile" class="profile-picture">
            <span class="user-id"><?php echo $student_id; ?></span>
            <span class="dropdown-arrow">▼</span>

            <!-- Dropdown Menu -->
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="#">Account Settings</a>
                <a href="#">Events Calendar</a>
                <a href="#">Announcements</a>
                <a href="#">Messages</a>
                <a href="#" class="notification-item">
                    Notifications
                    <?php if ($notification_count > 0): ?>
                        <span class="sidebar-notification-count"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="#">Help & Suggestions</a>
                <a href="#">Product Videos</a>
                <a href="#">Lock Screen</a>
                <a href="logout.php">Logout</a>
                </div>
        </div>
    </div>
</header>

        <!-- Sidebar -->
        <nav class="nav-primary sidebar hidden-xs">
        <ul class="nav side-menu">
            <!-- Home -->
            <li>
                <a href="#">
                    <i class="fa fa-home icon"><b style="background-color: #9a9a9a;"></b></i>
                    <span>Home</span>
                </a>
            </li>

            <!-- Personal Information -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-user icon"><b style="background-color: #ffc333;"></b></i>
                    <span>Personal Information</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Graduation Data</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Students Update Data</a></li>
                </ul>
            </li>

            <!-- Document Requests -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-file-alt icon"><b style="background-color: #4cc0c1;"></b></i>
                    <span>Document Requests</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Certificates Request</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Request a New Document</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Documents Requests History</a></li>
                </ul>
            </li>

            <!-- Study History -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-history icon"><b style="background-color: #fb6b5b;"></b></i>
                    <span>Study History</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Program Chart</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Course History</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> GPA Progress</a></li>
                </ul>
            </li>

            <!-- Surveys & Announcements -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-bullhorn icon"><b style="background-color: #bd7ad3;"></b></i>
                    <span>Surveys & Announcements</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Events Calendar</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Announcements</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Messages</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Notifications</a></li>
                </ul>
            </li>

            <!-- Exams & Grades -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-graduation-cap icon"><b style="background-color: #ab3fce;"></b></i>
                    <span>Exams & Grades</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Exams Schedule</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Final Result</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Grades Book</a></li>
                </ul>
            </li>

            <!-- Financial -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-dollar-sign icon"><b style="background-color: #c18f76;"></b></i>
                    <span>Financial</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Payment Permissions</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Balance Statement</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Balance</a></li>
                </ul>
            </li>

            <!-- Placements -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-briefcase icon"><b style="background-color: #c18f76;"></b></i>
                    <span>Placements</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Placement Test</a></li>
                </ul>
            </li>

            <!-- Coursera Accounts -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-laptop-code icon"><b style="background-color: #f257dc;"></b></i>
                    <span>Coursera Accounts</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Coursera</a></li>
                </ul>
            </li>

            <!-- E-Learning -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-globe icon"><b style="background-color: #bd7ad3;"></b></i>
                    <span>E-Learning</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Quizzes</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Exams</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Assignments</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Discussions</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Meetings</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Files & Materials</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Question Bank</a></li>
                </ul>
            </li>

            <!-- Academic Registration -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-university icon"><b style="background-color: #c18f76;"></b></i>
                    <span>Academic Registration</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="course.php"><i class="fa fa-angle-right"></i> Course Registration</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Registration Proposal</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Transcript</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Studying Schedule</a></li>
                </ul>
            </li>

            <!-- Semester Works -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-flask icon"><b style="background-color: #f257dc;"></b></i>
                    <span>Semester Works</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Lectures Absence</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Absence Warnings</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Lecture Attendance</a></li>
                </ul>
            </li>

            <!-- Student Activities -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-users icon"><b style="background-color: #f257dc;"></b></i>
                    <span>Student Activities</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Activities Registration</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Trips Reservations</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Sports Reservations</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Graduation Party Tickets</a></li>
                </ul>
            </li>

            <!-- Others -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-cog icon"><b style="background-color: #ab3fce;"></b></i>
                    <span>Others</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Department Desires</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Questionnaires</a></li>
                    <li><a href="#"><i class="fa fa-angle-right"></i> Course Specifications</a></li>
                </ul>
            </li>

            <!-- Military Service -->
            <li class="last">
                <a class="c-p">
                    <i class="fa fa-angle-down icon"><b style="background-color: #f257dc;"></b></i>
                    <span>Military Service</span>
                    <i class="fa fa-angle-down dropdown-toggle"></i>
                    <i class="fa fa-angle-up dropdown-toggle" style="display: none;"></i>
                </a>
                <ul class="nav lt">
                    <li><a href="#"><i class="fa fa-angle-right"></i> Military Service</a></li>
                </ul>
            </li>
        </ul>
    </nav>
    <!-- Sidebar -->

    <!-- Main Content -->
    <main class="content">
        <section class="welcome-section">
            <h2>Welcome back, <span id="student-name"><?php echo $student['FirstName'] . ' ' . $student['LastName']; ?></span></h2>
            <p>Congratulations, You are now on the electronic portal of Delta University for Science and Technology on its cloud servers.</p>
            <p>We're glad you're here, and will be happy to help you.</p>
        </section>

        <!-- Tab Container -->
        <div class="tab-container">
            <div class="tab active" data-tab="files"><i class="fas fa-file"></i> Files</div>
            <div class="tab" data-tab="events"><i class="fas fa-calendar-alt"></i> Today Events</div>
            <div class="tab" data-tab="notifications"><i class="fas fa-bell"></i> Latest Notifications <span class="notification-badge"><?php echo $notification_count > 0 ? $notification_count : ''; ?></span></div>
            <div class="tab" data-tab="messages"><i class="fas fa-comment"></i> Latest Messages</div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content active" id="files">
            <section class="files-section">
                <div class="file-icon">
                    <i class="far fa-file-alt"></i>
                </div>
                <h3>Files</h3>
                <p>No Files found for you.</p>
            </section>
        </div>

        <div class="tab-content" id="events">
            <section class="events-section">
                <h3>Today Events</h3>
                <p>No events scheduled for today.</p>
            </section>
        </div>

        <div class="tab-content" id="notifications">
            <section class="notifications-section">
                <h3>Latest Notifications</h3>
                <?php if ($notification_count > 0): ?>
                    <div class="notifications-list">
                        <?php 
                        // إعادة تشغيل الاستعلام
                        $notifications_result = $conn->query($notifications_query);
                        while ($notification = $notifications_result->fetch_assoc()): ?>
                            <div class="notification-item <?php 
                                switch($notification['NotificationType']) {
                                    case 'urgent': echo 'urgent-notification'; break;
                                    case 'academic': echo 'academic-notification'; break;
                                    case 'administrative': echo 'admin-notification'; break;
                                    default: echo 'general-notification'; break;
                                }
                            ?>">
                                <div class="notification-header">
                                    <span class="notification-title"><?php echo htmlspecialchars($notification['Title']); ?></span>
                                    <span class="notification-date"><?php echo date('d M Y H:i', strtotime($notification['CreatedAt'])); ?></span>
                                </div>
                                <div class="notification-content">
                                    <?php echo htmlspecialchars($notification['Content']); ?>
                                </div>
                                <?php if ($notification['NotificationType'] == 'urgent'): ?>
                                    <div class="notification-priority">
                                        <i class="fas fa-exclamation-triangle"></i> Urgent
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>No new notifications.</p>
                <?php endif; ?>
            </section>
        </div>

        <div class="tab-content" id="messages">
            <section class="messages-section">
                <h3>Latest Messages</h3>
                <p>No messages available.</p>
            </section>
        </div>
    </main>

    <!-- Profile Section -->
<!-- Profile Section -->
<aside class="profile">
    <div class="profile-details">
        <h3><?php echo $student['FirstName'] . ' ' . $student['LastName']; ?></h3>
        <p>ID: <?php echo $student['StudentID']; ?></p>
        <div class="stats">
        <div>
            <span class="number"><?php echo $student['AcademicYear']; ?></span>
            <span class="label">LEVEL</span>
        </div>
        <div>
            <span class="number"><?php echo $student['GPA']; ?></span>
            <span class="label">CGPA</span>
        </div>
        <div>
            <span class="number"><?php echo $completed_credits; ?></span>
            <span class="label">TPH</span>
        </div>
    </div>
        <div class="about-me">
            <h4>About Me</h4>
             <div class="info-row">
                <span class="label">Faculty:</span>
                <span class="value">Artificial Intelligence</span>
            </div>
            <div class="info-row">
                <span class="label">Program:</span>
                <span class="value"><?php echo !empty($student['Major']) ? $student['Major'] : 'General'; ?></span>
            </div>

            <div class="info-row">
                <span class="label">Guide:</span>
                <span class="value"><?php echo $student['Guide']; ?></span>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span class="value">Regular</span>
            </div>
        </div>
        

    <div class="graduation-info">
    <h4>GRADUATION INFO.</h4>
    <div class="progress-circle" style="--progress: <?php echo $progress_percentage; ?>%;">
        <span class="progress-text"><?php echo $remaining_hours; ?> HRs<br>to be Graduated</span>
    </div>
</div>
</div>
</div>
</div>
</div>
    </div>
</aside>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
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
    </div>

    <footer>
        &copy; 2023 Team. All rights reserved.
    </footer>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    // ✅ تبديل التبويبات
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            const targetTab = tab.getAttribute('data-tab');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    // ✅ تبديل قائمة فريق الواتساب
    const whatsappIcon = document.getElementById('whatsappIcon');
    if (whatsappIcon) {
        whatsappIcon.addEventListener('click', function () {
            document.getElementById('teamList').classList.toggle('active');
        });
    } else {
        console.log('خطأ: whatsappIcon غير موجود');
    }

    // ✅ فتح محادثة واتساب مع عضو الفريق
    function openWhatsApp(phoneNumber, memberName) {
        const message = `Hello ${memberName}, I need assistance with the Smart College Management System.`;
        const url = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
        window.open(url, '_blank');
    }

    // ✅ تبديل الشريط الجانبي (Sidebar)
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.nav-primary.sidebar');
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('الكليك على menuToggle شغال!');
            sidebar.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        });
    } else {
        console.log('خطأ: menuToggle أو sidebar مش موجودين');
    }

    // ✅ تبديل القوائم الفرعية داخل الشريط الجانبي
    const menuItems = document.querySelectorAll('.sidebar .last');
    if (menuItems.length > 0) {
        menuItems.forEach(item => {
            const mainLink = item.querySelector('a.c-p'); // الرابط الرئيسي
            mainLink.addEventListener('click', function (e) {
                e.preventDefault(); // منع التنقل الافتراضي
                if (item.classList.contains('active')) {
                    item.classList.remove('active');
                } else {
                    menuItems.forEach(i => i.classList.remove('active')); // إغلاق القوائم الأخرى
                    item.classList.add('active');
                }
                console.log('تم النقر على القائمة الرئيسية:', item);
            });

            // معالجة الروابط الفرعية
            const subLinks = item.querySelectorAll('.nav.lt a');
            subLinks.forEach(subLink => {
                subLink.addEventListener('click', function (e) {
                    e.stopPropagation(); // منع التفاعل مع الحدث الرئيسي
                    const href = this.getAttribute('href');
                    if (href && href !== '#') {
                        console.log('تنقل إلى:', href);
                        window.location.href = href; // التنقل للرابط
                    } else {
                        console.log('الرابط غير محدد أو #');
                    }
                });
            });
        });
    } else {
        console.log('خطأ: لا توجد عناصر .sidebar .last');
    }

    // ✅ فتح القائمة المنسدلة للمستخدم مع إغلاقها عند النقر خارجها
    const userProfile = document.getElementById('userProfile');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if (userProfile && dropdownMenu) {
        userProfile.addEventListener('click', function (e) {
            e.stopPropagation(); // منع إغلاق القائمة عند النقر عليها
            dropdownMenu.classList.toggle('active');
        });

        dropdownMenu.addEventListener('click', function (e) {
            e.stopPropagation(); // منع إغلاق القائمة عند النقر داخلها
        });

        document.addEventListener('click', function (e) {
            if (!userProfile.contains(e.target)) {
                dropdownMenu.classList.remove('active');
            }
        });
    } else {
        console.log('خطأ: userProfile أو dropdownMenu غير موجودين');
    }

    // ✅ التأكد من أن زر تسجيل الخروج يعمل
    const logoutButton = document.querySelector('.dropdown-menu a[href="logout.php"]');
    if (logoutButton) {
        logoutButton.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('تم النقر على زر تسجيل الخروج');
            window.location.href = 'logout.php';
        });
    } else {
        console.log('خطأ: زر تسجيل الخروج غير موجود');
    }
});


</script>
</body>
</html>
<?php
$conn->close();
?>