<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json"); // Ensures all JSON responses are correct
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // ===== LIST ALL ENROLLMENTS =====
    case "list":
        $sql = "
            SELECT 
                e.enrollment_id, e.student_id, e.section_id, e.date_enrolled, e.status, e.letter_grade,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                sec.section_code
            FROM tblenrollment e
            LEFT JOIN tblstudent s ON e.student_id = s.student_id
            LEFT JOIN tblsection sec ON e.section_id = sec.section_id
            WHERE e.is_deleted = 0
            ORDER BY e.enrollment_id ASC
        ";
        $res = $conn->query($sql);
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    // ===== DROPDOWNS =====
    case "students":
        $res = $conn->query("
            SELECT student_id, CONCAT(student_no, ' - ', first_name, ' ', last_name) AS student_name 
            FROM tblstudent 
            WHERE is_deleted = 0
            ORDER BY last_name ASC
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    case "sections":
        $res = $conn->query("
            SELECT section_id, section_code 
            FROM tblsection 
            WHERE is_deleted = 0
            ORDER BY section_code ASC
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    // ===== ADD ENROLLMENT =====
    case "add":
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

        // If a duplicate is found (count > 0), stop and send an error
        if ($count > 0) {
            echo json_encode(["success" => false, "error" => "This student is already enrolled in this section."]);
            break; // Stop execution for this case
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO tblenrollment (student_id, section_id, date_enrolled, status, letter_grade)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iisss",
                $_POST['student_id'],
                $_POST['section_id'],
                $_POST['date_enrolled'],
                $_POST['status'],
                $_POST['letter_grade']
            );
            $stmt->execute();

            //  Get the last inserted enrollment to return
            $new_id = $conn->insert_id;
            $sql = "
                SELECT 
                    e.enrollment_id, e.student_id, e.section_id, e.date_enrolled, e.status, e.letter_grade,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    sec.section_code
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
            if (str_contains($e->getMessage(), "Duplicate entry")) {
                echo json_encode(["success" => false, "error" => "This student is already enrolled in this section."]);
            } else {
                echo json_encode(["success" => false, "error" => "Unexpected error: " . $e->getMessage()]);
            }
        }
        break;

// ===== EDIT ENROLLMENT =====
    case "edit":
        $student_id = $_POST['student_id'];
        $section_id = $_POST['section_id'];
        $enrollment_id = $_POST['enrollment_id'];

        // Check for an *active* duplicate that is NOT this enrollment
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM tblenrollment 
            WHERE student_id = ? 
              AND section_id = ? 
              AND is_deleted = 0
              AND enrollment_id != ? 
        ");
        $check_stmt->bind_param("iii", $student_id, $section_id, $enrollment_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_row()[0];

        // If a duplicate is found, stop and send an error
        if ($count > 0) {
            echo json_encode(["success" => false, "error" => "This student is already enrolled in this section."]);
            break; // Stop execution
        }

        // If no duplicate was found, proceed with the update
        try {
            $stmt = $conn->prepare("
                UPDATE tblenrollment
                SET student_id=?, section_id=?, date_enrolled=?, status=?, letter_grade=?
                WHERE enrollment_id=?
            ");
            $stmt->bind_param(
                "iisssi",
                $student_id,
                $section_id,
                $_POST['date_enrolled'],
                $_POST['status'],
                $_POST['letter_grade'],
                $enrollment_id
            );
            $stmt->execute();
            echo json_encode(["success" => true]);
        } catch (mysqli_sql_exception $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        break;

    // ===== DELETE ENROLLMENT (SOFT DELETE) =====
    case "delete":
        $stmt = $conn->prepare("UPDATE tblenrollment SET is_deleted = 1 WHERE enrollment_id = ?");
        $stmt->bind_param("i", $_POST['enrollment_id']);
        echo json_encode(["success" => $stmt->execute()]);
        break;

    // ===== SEARCH ENROLLMENTS =====
    case "search":
        $q = "%" . ($_GET['q'] ?? "") . "%";
        $stmt = $conn->prepare("
            SELECT 
                e.enrollment_id, e.student_id, e.section_id, e.date_enrolled, e.status, e.letter_grade,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                sec.section_code
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

    // ===== EXPORT =====
    case "export":
        $type = $_GET['type'] ?? 'excel';
        $sql = "
            SELECT 
                CONCAT(s.first_name, ' ', s.last_name) AS 'Student Name',
                sec.section_code AS 'Section',
                e.date_enrolled AS 'Date Enrolled',
                e.status AS 'Status',
                e.letter_grade AS 'Grade'
            FROM tblenrollment e
            LEFT JOIN tblstudent s ON e.student_id = s.student_id
            LEFT JOIN tblsection sec ON e.section_id = sec.section_id
            WHERE e.is_deleted = 0
            ORDER BY s.last_name ASC, s.first_name ASC
        ";
        $res = $conn->query($sql);
        $data = $res->fetch_all(MYSQLI_ASSOC);

        if (!$data) {
            echo 'No data found.';
            exit;
        }

        if ($type === 'excel') {
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=enrollments.xls");

            date_default_timezone_set('Asia/Manila');
            $date = date("F d, Y - h:i A");

            echo "
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #555; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .header {
                        background-color: #800000;
                        color: white;
                        font-weight: bold;
                        text-align: center;
                        font-size: 16pt;
                        padding: 10px;
                    }
                    .sub-header {
                        text-align: center;
                        font-size: 10pt;
                        color: #333;
                        background-color: #f9f9f9;
                    }
                </style>
            </head>
            <body>
                <table>
                    <tr>
                        <td colspan='5' class='header'>
                            Polytechnic University of the Philippines - Taguig Campus
                        </td>
                    </tr>
                    <tr>
                        <td colspan='5' class='sub-header'>
                            Enrollment List | Date: {$date} | Page: 1
                        </td>
                    </tr>
                    <tr>";

            // Table headers
            foreach (array_keys($data[0]) as $col) {
                echo "<th>$col</th>";
            }
            echo "</tr>";

            // Table rows
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($row as $cell) echo "<td>" . htmlspecialchars($cell) . "</td>";
                echo "</tr>";
            }

            echo "
                </table>
            </body>
            </html>";
        } elseif ($type === 'pdf') {
            require_once('../fpdf186/fpdf.php');

            // Copied directly from course_api.php
            class PDF extends FPDF {
                function Header() {
                    $this->SetTextColor(128, 0, 0);
                    $this->SetFont('Arial', 'B', 14);
                    $this->Cell(0, 10, 'Polytechnic University of the Philippines - Taguig Campus', 0, 1, 'C');
                    $this->SetDrawColor(0, 0, 0);
                    $this->SetLineWidth(0.5);
                    $this->Line(10, $this->GetY(), 200, $this->GetY()); // 200 for Portrait
                    $this->Ln(8);
                    $this->SetTextColor(0, 0, 0);
                }

                function Footer() {
                    $this->SetY(-15);
                    $this->SetFont('Arial', 'I', 8);
                    $this->Cell(0, 5, $this->PageNo(), 0, 1, 'C');
                    $this->Cell(0, 5, 'Date Created: ' .date('F d, Y'), 0, 0, 'L');
                }

                function Row($data, $widths) {
                    $nb = 0;
                    for ($i = 0; $i < count($data); $i++) {
                        $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
                    }
                    $h = 6 * $nb;
                    $this->CheckPageBreak($h);
                    for ($i = 0; $i < count($data); $i++) {
                        $w = $widths[$i];
                        $x = $this->GetX();
                        $y = $this->GetY();
                        $this->Rect($x, $y, $w, $h);
                        $this->MultiCell($w, 6, $data[$i], 0, 'L');
                        $this->SetXY($x + $w, $y);
                    }
                    $this->Ln($h);
                }

                function CheckPageBreak($h) {
                    if ($this->GetY() + $h > $this->PageBreakTrigger)
                        $this->AddPage($this->CurOrientation);
                }

                function NbLines($w, $txt) {
                    $cw = &$this->CurrentFont['cw'];
                    if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
                    $s = str_replace("\r", '', $txt);
                    $nb = strlen($s);
                    if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
                    $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
                    while ($i < $nb) {
                        $c = $s[$i];
                        if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
                        if ($c == ' ') $sep = $i;
                        $l += $cw[$c];
                        if ($l > $wmax) {
                            if ($sep == -1) { if ($i == $j) $i++; } 
                            else $i = $sep + 1;
                            $sep = -1; $j = $i; $l = 0; $nl++;
                        } else $i++;
                    }
                    return $nl;
                }
            }

            $pdf = new PDF(); // Default Portrait
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Enrollment List', 0, 1, 'C'); // Title

            // Table Header
            $widths = [70, 40, 30, 25, 25]; // Total 190 (fits Portrait)
            $headers = ['Student Name', 'Section', 'Date Enrolled', 'Status', 'Grade'];

            $pdf->SetFillColor(128, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 10);
            foreach ($headers as $i => $h) {
                $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();

            // Table Body
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 9);
            foreach ($data as $row) {
                $pdf->Row(array_values($row), $widths);
            }

            $pdf->Output('I', 'enrollments.pdf');
        }
        break;

    default:
        echo json_encode(["error" => "Invalid action"]);
}
?>