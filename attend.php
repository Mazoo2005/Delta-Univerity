<?php
include 'db.php'; // ملف الاتصال بقاعدة البيانات

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = $_POST['course_id'];
    $date = date("Y-m-d");

    foreach ($_POST['attendance'] as $student_id => $status) {
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, course_id, date, status) VALUES (?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE status = ?");
        $stmt->bind_param("iisss", $student_id, $course_id, $date, $status, $status);
        $stmt->execute();
    }

    echo "تم تسجيل الغياب بنجاح!";
}
?>

<form method="post">
    <select name="course_id">
        <?php
        $result = $conn->query("SELECT * FROM courses");
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select>

    <table>
        <tr>
            <th>الطالب</th>
            <th>حاضر</th>
            <th>غايب</th>
        </tr>
        <?php
        $students = $conn->query("SELECT * FROM students");
        while ($student = $students->fetch_assoc()) {
            echo "<tr>
                    <td>{$student['name']}</td>
                    <td><input type='radio' name='attendance[{$student['id']}]' value='present' required></td>
                    <td><input type='radio' name='attendance[{$student['id']}]' value='absent' required></td>
                </tr>";
        }
        ?>
    </table>

    <button type="submit">تسجيل الغياب</button>
</form>



#صفحة عرض الغياب للطلاب
#هتكون صفحة يقدر الطالب يشوف فيها عدد غياباته.

<?php
include 'db.php';
$student_id = 1; // هتجيب الـ ID من الـ session بعد تسجيل الدخول

$result = $conn->query("SELECT courses.name, COUNT(attendance.id) AS absences 
                        FROM attendance 
                        JOIN courses ON attendance.course_id = courses.id
                        WHERE attendance.student_id = $student_id AND attendance.status = 'absent'
                        GROUP BY courses.id");

echo "<table><tr><th>المادة</th><th>عدد الغيابات</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['name']}</td><td>{$row['absences']}</td></tr>";
}
echo "</table>";
?>