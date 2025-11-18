<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // ★★★ MODIFIED: Added s.is_regular ★★★
    case "list":
        $sql = "
            SELECT 
                e.enrollment_id, e.student_id, e.section_id, e.date_enrolled, e.status, e.letter_grade,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                sec.section_code,
                s.is_regular 
            FROM tblenrollment e
            LEFT JOIN tblstudent s ON e.student_id = s.student_id
            LEFT JOIN tblsection sec ON e.section_id = sec.section_id
            WHERE e.is_deleted = 0
            ORDER BY e.enrollment_id ASC
        ";
        $res = $conn->query($sql);
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    // ★★★ NEW: Checks if a student is regular or irregular ★★★
    case "getStudentStatus":
        $student_id = $_GET['id'];
        $stmt = $conn->prepare("SELECT is_regular FROM tblstudent WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        echo json_encode($student); // e.g., {"is_regular": 1}
        break;

    // Gets ALL students for the first dropdown
    case "students":
        $res = $conn->query("
            SELECT student_id, CONCAT(student_no, ' - ', first_name, ' ', last_name) AS student_name 
            FROM tblstudent 
            WHERE is_deleted = 0
            ORDER BY last_name ASC
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;
    
    // Gets formatted sections for IRREGULAR modal
    case "getSectionsForIrregular":
        $res = $conn->query("
            SELECT 
                s.section_id,
                CONCAT(
                    c.course_code, ' - ', c.course_title, ' (',
                    s.day_pattern, ' ', 
                    TIME_FORMAT(s.start_time, '%h:%i%p'), '-', 
                    TIME_FORMAT(s.end_time, '%h:%i%p'), ')'
                ) AS section_name
            FROM tblsection s
            JOIN tblcourse c ON s.course_id = c.course_id
            WHERE s.is_deleted = 0
            ORDER BY c.course_code
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    // Gets terms for REGULAR modal
    case "getTerms":
        $res = $conn->query("
            SELECT term_id, term_code 
            FROM tblterm 
            WHERE is_deleted = 0 
            ORDER BY start_date DESC
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    // Gets block details for REGULAR modal
    case "getBlockDetails":
        $student_id = $_GET['student_id'];
        $term_id = $_GET['term_id'];

        // --- Business Logic to find block code ---
        $student_info_stmt = $conn->prepare("
            SELECT p.program_code, s.year_level 
            FROM tblstudent s
            JOIN tblprogram p ON s.program_id = p.program_id
            WHERE s.student_id = ?
        ");
        $student_info_stmt->bind_param("i", $student_id);
        $student_info_stmt->execute();
        $student_info = $student_info_stmt->get_result()->fetch_assoc();
        
        // This is the logic we fixed before to build 'DIT-TG-3-1'
        $block_code = $student_info['program_code'] . '-' . $student_info['year_level'] . '-1';
        // --- End of business logic ---

        $courses_stmt = $conn->prepare("
            SELECT 
                c.course_code, 
                c.course_title, 
                s.day_pattern, 
                TIME_FORMAT(s.start_time, '%l:%i %p') AS start_time_f,
                TIME_FORMAT(s.end_time, '%l:%i %p') AS end_time_f,
                CONCAT(i.first_name, ' ', i.last_name) AS instructor_name,
                r.room_code
            FROM tblsection s
            JOIN tblcourse c ON s.course_id = c.course_id
            JOIN tblinstructor i ON s.instructor_id = i.instructor_id
            JOIN tblroom r ON s.room_id = r.room_id
            WHERE s.section_code = ? AND s.term_id = ? AND s.is_deleted = 0
            ORDER BY c.course_code
        ");
        $courses_stmt->bind_param("si", $block_code, $term_id);
        $courses_stmt->execute();
        $courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['section_code' => $block_code, 'courses' => $courses]);
        break;

    // Enrolls student in all sections of a block
    case "enrollBlock":
        $student_id = $_POST['student_id'];
        $term_id = $_POST['term_id'];
        $section_code = $_POST['section_code'];
        $date_enrolled = date('Y-m-d'); // Use current date

        // 1. Check if student is ALREADY enrolled in this term
        $check_stmt = $conn->prepare("
            SELECT COUNT(e.enrollment_id) 
            FROM tblenrollment e
            JOIN tblsection s ON e.section_id = s.section_id
            WHERE e.student_id = ? AND s.term_id = ? AND e.is_deleted = 0
        ");
        $check_stmt->bind_param("ii", $student_id, $term_id);
        $check_stmt->execute();
        $count = $check_stmt->get_result()->fetch_row()[0];

        if ($count > 0) {
            echo json_encode(["success" => false, "error" => "This student is already enrolled in one or more sections for this term. Clear their schedule before block enrolling."]);
            break;
        }

        // 2. If clear, enroll in all sections
        try {
            $insert_stmt = $conn->prepare("
                INSERT INTO tblenrollment (student_id, section_id, date_enrolled, status, is_deleted)
                SELECT ? AS student_id, s.section_id, ? AS date_enrolled, 'Enrolled' AS status, 0 AS is_deleted
                FROM tblsection s
                WHERE s.section_code = ? AND s.term_id = ?
            ");
            $insert_stmt->bind_param("issi", $student_id, $date_enrolled, $section_code, $term_id);
            $insert_stmt->execute();
            $affected_rows = $insert_stmt->affected_rows;

            if ($affected_rows > 0) {
                echo json_encode(["success" => true, "count" => $affected_rows]);
            } else {
                echo json_encode(["success" => false, "error" => "No sections found for that block code and term."]);
            }

        } catch (mysqli_sql_exception $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        break;


    // ADD (for Irregular)
    case "add":
        // ... (Same as your original file)
        // Check for an *active* duplicate (is_deleted = 0)
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM tblenrollment 
            WHERE student_id = ? AND section_id = ? AND is_deleted = 0
        ");
        $check_stmt->bind_param("ii", $_POST['student_id'], $_POST['section_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_row()[0];

        if ($count > 0) {
            echo json_encode(["success" => false, "error" => "This student is already enrolled in this section."]);
            break; 
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO tblenrollment (student_id, section_id, date_enrolled, status, letter_grade)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisss", $_POST['student_id'], $_POST['section_id'], $_POST['date_enrolled'], $_POST['status'], $_POST['letter_grade']);
            $stmt->execute();
            $new_id = $conn->insert_id;
            
            // ... (Get new enrollment record - same as your original file)
            $sql = "
                SELECT 
                    e.enrollment_id, e.student_id, e.section_id, e.date_enrolled, e.status, e.letter_grade,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    sec.section_code,
                    s.is_regular
                FROM tblenrollment e
                LEFT JOIN tblstudent s ON e.student_id = s.student_id
                LEFT JOIN tblsection sec ON e.section_id = sec.section_id
                WHERE e.enrollment_id = ?
            ";
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param("i", $new_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $new_enrollment = $result->fetch_assoc();

            echo json_encode(["success" => true, "enrollment" => $new_enrollment]);
        } catch (mysqli_sql_exception $e) {
            // ... (Error handling - same as your original file)
            if (str_contains($e->getMessage(), "Duplicate entry")) {
                echo json_encode(["success" => false, "error" => "This student is already enrolled in this section."]);
            } else {
                echo json_encode(["success" => false, "error" => "Unexpected error: " . $e->getMessage()]);
            }
        }
        break;

    // EDIT
    case "edit":
        // ... (Same as your original file)
        $student_id = $_POST['student_id'];
        $section_id = $_POST['section_id'];
        $enrollment_id = $_POST['enrollment_id'];

        $check_stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM tblenrollment 
            WHERE student_id = ? AND section_id = ? AND is_deleted = 0 AND enrollment_id != ? 
        ");
        $check_stmt->bind_param("iii", $student_id, $section_id, $enrollment_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_row()[0];

        if ($count > 0) {
            echo json_encode(["success" => false, "error" => "This student is already enrolled in this section."]);
            break; 
        }

        try {
            $stmt = $conn->prepare("
                UPDATE tblenrollment
                SET student_id=?, section_id=?, date_enrolled=?, status=?, letter_grade=?
                WHERE enrollment_id=?
            ");
            $stmt->bind_param("iisssi", $student_id, $section_id, $_POST['date_enrolled'], $_POST['status'], $_POST['letter_grade'], $enrollment_id);
            $stmt->execute();
            echo json_encode(["success" => true]);
        } catch (mysqli_sql_exception $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        break;

    // DELETE
    case "delete":
        // ... (Same as your original file)
        $stmt = $conn->prepare("UPDATE tblenrollment SET is_deleted = 1 WHERE enrollment_id = ?");
        $stmt->bind_param("i", $_POST['enrollment_id']);
        echo json_encode(["success" => $stmt->execute()]);
        break;

    // SEARCH
    case "search":
        // ... (Same as your original file)
        $q = "%" . ($_GET['q'] ?? "") . "%";
        $stmt = $conn->prepare("
            SELECT 
                e.enrollment_id, e.student_id, e.section_id, e.date_enrolled, e.status, e.letter_grade,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                sec.section_code,
                s.is_regular
            FROM tblenrollment e
            LEFT JOIN tblstudent s ON e.student_id = s.student_id
            LEFT JOIN tblsection sec ON e.section_id = sec.section_id
            WHERE e.is_deleted = 0
              AND (s.first_name LIKE ? OR s.last_name LIKE ? OR sec.section_code LIKE ? OR e.status LIKE ?)
            ORDER BY e.enrollment_id ASC
        ");
        $stmt->bind_param("ssss", $q, $q, $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    // EXPORT
    case "export":
        // ... (Same as your original file)
        break;

    default:
        echo json_encode(["error" => "Invalid action"]);
}
?>