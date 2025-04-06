<?php
session_start();

// التحقق من جلسة المستخدم
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

// إعدادات الاتصال بقاعدة البيانات
$servername = "localhost";
$username = "root";
$password = "Mmina@12345";
$dbname = "ai_management";

// دالة للاتصال بقاعدة البيانات
function connectToDatabase($server, $user, $pass, $db) {
    $conn = new mysqli($server, $user, $pass, $db);
    if ($conn->connect_error) {
        die("فشل الاتصال: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$conn = connectToDatabase($servername, $username, $password, $dbname);

// دالة لجلب بيانات الطالب
function getStudentData($conn, $student_id) {
    $sql = "SELECT GPA, AcademicYear FROM Students WHERE StudentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    if (!$student) {
        die("لم يتم العثور على الطالب");
    }
    return $student;
}

// دالة لجلب إعدادات التسجيل
function getRegistrationSettings($conn, $academic_level) {
    $sql = "SELECT IsRegistrationOpen, AllowedCourses FROM RegistrationSettings WHERE AcademicLevel = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $academic_level);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc() ?: ['IsRegistrationOpen' => 0, 'AllowedCourses' => ''];
    $stmt->close();
    return $settings;
}

// دالة لحساب الساعات المسجلة
function getCurrentCredits($conn, $student_id) {
    $sql = "SELECT SUM(C.Credits) AS TotalCredits FROM Registration R JOIN Courses C ON R.CourseID = C.CourseID WHERE R.StudentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['TotalCredits'] ?? 0;
}

// دالة لجلب المواد المتاحة
function getAvailableCourses($conn, $academic_level, $allowed_courses, $student_id) {
    $placeholder = $allowed_courses ? implode(',', array_fill(0, count($allowed_courses), '?')) : '0';
    $sql = "SELECT C.CourseID, C.CourseName, C.Credits 
            FROM Courses C 
            WHERE C.AcademicLevel = ? 
            AND C.CourseID IN ($placeholder) 
            AND C.CourseID NOT IN (SELECT CourseID FROM Registration WHERE StudentID = ?)";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$academic_level], $allowed_courses, [$student_id]);
    $stmt->bind_param(str_repeat('s', count($allowed_courses) + 2), ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// دالة لجلب جدول المواد المسجلة
function getRegisteredSchedule($conn, $student_id) {
    $sql = "SELECT R.CourseID, C.CourseName, S.Time, S.GroupNumber, S.SectionNumber, S.DAY 
            FROM Registration R 
            JOIN Courses C ON R.CourseID = C.CourseID 
            JOIN Schedule S ON S.CourseID = R.CourseID 
            WHERE R.StudentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result();
}

// دالة لجلب المواد المسجلة لعرض زر الإلغاء
function getRegisteredCourses($conn, $student_id) {
    $sql = "SELECT R.CourseID, C.CourseName, C.Credits 
            FROM Registration R 
            JOIN Courses C ON R.CourseID = C.CourseID 
            WHERE R.StudentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result();
}

// استرجاع البيانات
$student_id = (int)$_SESSION['id'];
$student = getStudentData($conn, $student_id);
$gpa = $student['GPA'];
$academic_year = $student['AcademicYear'];
$academic_level = "Level" . $academic_year;

$reg_settings = getRegistrationSettings($conn, $academic_level);
$is_registration_open = $reg_settings['IsRegistrationOpen'];
$allowed_courses = $reg_settings['AllowedCourses'] ? explode(',', $reg_settings['AllowedCourses']) : [];

$max_credits = ($gpa < 2.0) ? 12 : 19;
$current_credits = getCurrentCredits($conn, $student_id);
$available_courses = getAvailableCourses($conn, $academic_level, $allowed_courses, $student_id);
$schedule_result = getRegisteredSchedule($conn, $student_id);
$registered_courses = getRegisteredCourses($conn, $student_id);

// معالجة التسجيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['course_id']) && $is_registration_open) {
    $course_id = (int)$_POST['course_id'];
    $sql = "SELECT Credits FROM Courses WHERE CourseID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($course && in_array($course_id, $allowed_courses)) {
        $course_credits = $course['Credits'];
        if (($current_credits + $course_credits) <= $max_credits) {
            $sql = "INSERT INTO Registration (StudentID, CourseID) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $student_id, $course_id);
            if ($stmt->execute()) {
                $success_message = "تم تسجيل المادة بنجاح!";
                header("Refresh:0");
            } else {
                $error_message = "خطأ في التسجيل: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "لا يمكنك تجاوز الحد الأقصى للساعات ($max_credits ساعة).";
        }
    } else {
        $error_message = "هذه المادة غير متاحة للتسجيل!";
    }
}

// معالجة إلغاء التسجيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_course_id']) && $is_registration_open) {
    $course_id = (int)$_POST['cancel_course_id'];
    $sql = "DELETE FROM Registration WHERE StudentID = ? AND CourseID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $course_id);
    if ($stmt->execute()) {
        $success_message = "تم إلغاء تسجيل المادة بنجاح!";
        header("Refresh:0");
    } else {
        $error_message = "خطأ في إلغاء التسجيل: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل المواد</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a3c34;
            --secondary-color: #00a896;
            --accent-color: #f4a261;
            --success-color: #2a9d8f;
            --error-color: #e76f51;
            --background-light: #f8f9fa;
            --text-dark: #264653;
            --text-light: #ffffff;
            --shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            --border-radius: 16px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            min-height: 100vh;
            margin: 0;
            padding: 30px;
            direction: rtl;
            color: var(--text-dark);
            line-height: 1.8;
        }

        .header {
            background: var(--primary-color);
            color: var(--text-light);
            padding: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: var(--background-light);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        h2, h3 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
        }

        h2 {
            font-size: 2.2rem;
        }

        h3 {
            font-size: 1.6rem;
        }

        .info-box {
            background: var(--text-light);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .info-item {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            text-align: center;
        }

        .course-list, .registered-list {
            margin: 30px 0;
        }

        .course-box, .registered-box {
            background: var(--text-light);
            border: 2px solid var(--secondary-color);
            border-radius: var(--border-radius);
            margin: 20px 0;
            padding: 25px;
            position: relative;
            transition: var(--transition);
            cursor: pointer;
        }

        .course-box:hover, .registered-box:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow);
        }

        .course-box.pending {
            border-color: var(--accent-color);
            background: #fff4e6;
        }

        .course-title {
            color: var(--primary-color);
            font-size: 1.4rem;
            margin: 0 0 15px 0;
            font-weight: 600;
        }

        .course-options {
            display: none;
            background: var(--background-light);
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .course-options.active {
            display: block;
        }

        button {
            background: var(--secondary-color);
            color: var(--text-light);
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
            font-weight: 600;
        }

        button:hover {
            background: var(--primary-color);
            transform: scale(1.05);
        }

        .cancel-btn {
            background: var(--error-color);
        }

        .cancel-btn:hover {
            background: #d9480f;
        }

        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-top: 40px;
            background: var(--background-light);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .schedule-table th {
            background: var(--primary-color);
            color: var(--text-light);
            padding: 18px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .schedule-table td {
            background: var(--text-light);
            padding: 20px;
            border-radius: 10px;
            vertical-align: top;
            min-height: 120px;
            font-size: 1rem;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .event {
            background: var(--success-color);
            color: var(--text-light);
            padding: 12px;
            border-radius: 8px;
            margin: 8px 0;
            font-size: 0.95rem;
            line-height: 1.5;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .event:hover {
            transform: scale(1.02);
        }

        .message {
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 500;
            box-shadow: var(--shadow);
        }

        .success {
            background: var(--success-color);
            color: var(--text-light);
        }

        .error {
            background: var(--error-color);
            color: var(--text-light);
        }

        .warning {
            background: var(--accent-color);
            color: var(--text-dark);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            .course-box, .registered-box {
                padding: 20px;
            }
            .schedule-table th, .schedule-table td {
                padding: 12px;
                font-size: 0.9rem;
            }
            .info-box {
                grid-template-columns: 1fr;
            }
            button {
                width: 100%;
                padding: 12px;
            }
            .header {
                font-size: 1.2rem;
                padding: 20px;
            }
        }

        .logout-section {
            text-align: center;
            margin-top: 30px;
            padding: 20px 0;
            border-top: 1px solid #eee;
        }
        
        .logout-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background-color:rgb(232, 219, 218);
        }
        
        .logout-btn i {
            margin-right: 8px;
        }
        
    </style>
</head>
<body>
    <div class="header">
        <h1>نظام إدارة التعلم - تسجيل المواد</h1>
    </div>

    <div class="container">
        <h2>تسجيل المواد</h2>

        <?php 
        if (isset($success_message)) echo "<div class='message success'>$success_message</div>";
        if (isset($error_message)) echo "<div class='message error'>$error_message</div>";
        if (!$is_registration_open) echo "<div class='message error'>التسجيل مغلق حاليًا</div>";
        ?>

        <div class="info-box">
            <span class="info-item">المعدل التراكمي: <?php echo number_format($gpa, 2); ?></span>
            <span class="info-item">الحد الأقصى للساعات: <?php echo $max_credits; ?> ساعة</span>
            <span class="info-item">الساعات المسجلة: <?php echo $current_credits; ?> ساعة</span>
        </div>

        <!-- قائمة المواد المتاحة للتسجيل -->
        <div class="course-list">
            <h3>المواد المتاحة</h3>
            <?php if ($is_registration_open): ?>
                <?php if ($available_courses->num_rows > 0): ?>
                    <?php while ($course = $available_courses->fetch_assoc()): ?>
                        <div class="course-box" onclick="toggleOptions(this)">
                            <div class="course-title"><?php echo htmlspecialchars($course['CourseName']) . " (" . $course['Credits'] . " ساعة)"; ?></div>
                            <div class="course-options">
                                <form method="POST">
                                    <input type="hidden" name="course_id" value="<?php echo $course['CourseID']; ?>">
                                    <p>ستظهر جميع المجموعات والسكاشن المتاحة في الجدول بعد التسجيل.</p>
                                    <button type="submit">تسجيل</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="message error">لا توجد مواد متاحة للتسجيل حاليًا</div>
                <?php endif; ?>
            <?php else: ?>
                <?php while ($course = $available_courses->fetch_assoc()): ?>
                    <div class="course-box pending">
                        <div class="course-title"><?php echo htmlspecialchars($course['CourseName']) . " (" . $course['Credits'] . " ساعة)"; ?></div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- قائمة المواد المسجلة مع زر الإلغاء -->
        <div class="registered-list">
            <h3>المواد المسجلة</h3>
            <?php if ($registered_courses->num_rows > 0): ?>
                <?php while ($course = $registered_courses->fetch_assoc()): ?>
                    <div class="registered-box">
                        <div class="course-title"><?php echo htmlspecialchars($course['CourseName']) . " (" . $course['Credits'] . " ساعة)"; ?></div>
                        <?php if ($is_registration_open): ?>
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="cancel_course_id" value="<?php echo $course['CourseID']; ?>">
                                <button type="submit" class="cancel-btn">إلغاء التسجيل</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="message warning">لم تقم بتسجيل أي مواد بعد</div>
            <?php endif; ?>
        </div>

        <!-- جدول المواد المسجلة -->
        <table class="schedule-table">
            <thead>
                <tr>
                    <?php 
                    $days = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
                    foreach ($days as $day): ?>
                        <th><?php echo $day; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php 
                    $day_keys = [0, 1, 2, 3, 4, 5, 6]; // Assuming DAY is 0-6 (Saturday to Friday)
                    foreach ($day_keys as $day_index): ?>
                        <td>
                            <?php 
                            $schedule_result->data_seek(0);
                            while ($schedule = $schedule_result->fetch_assoc()):
                                if ((int)$schedule['DAY'] === $day_index):
                                    $time_display = $schedule['Time'] ? htmlspecialchars($schedule['Time']) : "غير محدد";
                                    echo "<div class='event'>" 
                                        . htmlspecialchars($schedule['CourseName']) 
                                        . " (مجموعة: " . $schedule['GroupNumber'] 
                                        . ", سكشن: " . $schedule['SectionNumber'] . ")<br>" 
                                        . $time_display 
                                        . "</div>";
                                endif;
                            endwhile;
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="logout-section">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    <script>
        function toggleOptions(element) {
            const options = element.querySelector('.course-options');
            if (options) {
                const isActive = options.classList.contains('active');
                document.querySelectorAll('.course-options').forEach(opt => opt.classList.remove('active'));
                if (!isActive) {
                    options.classList.add('active');
                }
            }
        }
    </script>
</body>
</html>

<?php 
$conn->close();
?>