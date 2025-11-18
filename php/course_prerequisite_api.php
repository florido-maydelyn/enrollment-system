<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
header("Content-Type: application/json");

switch ($action) {

    // LIST
    case "list":

        $sql = "
            SELECT 
                cp.course_id,
                cp.prereq_course_id,
                c1.course_code AS course_code,
                c1.course_title AS course_title,
                c2.course_code AS prereq_code,
                c2.course_title AS prereq_title
            FROM tblcourse_prerequisite cp
            LEFT JOIN tblcourse c1 ON cp.course_id = c1.course_id
            LEFT JOIN tblcourse c2 ON cp.prereq_course_id = c2.course_id
            WHERE cp.is_deleted = 0
            ORDER BY c1.course_code ASC, c2.course_code ASC
        ";
        $res = $conn->query($sql);
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;

    // ADD
    case "add":
        $course_id = $_POST['course_id'];
        $prereq_id = $_POST['prereq_course_id'];

        if ($course_id === $prereq_id) {
             echo json_encode(["success" => false, "error" => "A course cannot be a prerequisite of itself."]);
             break;
        }

        // Check if this pair exists (even if soft-deleted)
        $stmt_check = $conn->prepare("
            SELECT is_deleted 
            FROM tblcourse_prerequisite 
            WHERE course_id = ? AND prereq_course_id = ?
        ");
        $stmt_check->bind_param("ii", $course_id, $prereq_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        try {
            if ($result->num_rows > 0) {
                // It exists. Check if it's deleted.
                $row = $result->fetch_assoc();
                if ($row['is_deleted'] == 0) {
                    // It's already active. Throw an error.
                    echo json_encode(["success" => false, "error" => "This prerequisite relationship already exists."]);
                } else {
                    // It's soft-deleted. "Undelete" it.
                    $stmt_update = $conn->prepare("
                        UPDATE tblcourse_prerequisite SET is_deleted = 0 
                        WHERE course_id = ? AND prereq_course_id = ?
                    ");
                    $stmt_update->bind_param("ii", $course_id, $prereq_id);
                    $stmt_update->execute();
                    echo json_encode(["success" => true, "restored" => true]);
                }
            } else {
                // It doesn't exist at all. Insert it.
                $stmt_insert = $conn->prepare("
                    INSERT INTO tblcourse_prerequisite (course_id, prereq_course_id)
                    VALUES (?, ?)
                ");
                $stmt_insert->bind_param("ii", $course_id, $prereq_id);
                $stmt_insert->execute();
            }

            // If we inserted or restored, fetch the full row data to send back
            if (!isset($row) || $row['is_deleted'] == 1) {
                $stmt_new = $conn->prepare("
                    SELECT 
                        cp.course_id, cp.prereq_course_id,
                        c1.course_code AS course_code, c1.course_title AS course_title,
                        c2.course_code AS prereq_code, c2.course_title AS prereq_title
                    FROM tblcourse_prerequisite cp
                    LEFT JOIN tblcourse c1 ON cp.course_id = c1.course_id
                    LEFT JOIN tblcourse c2 ON cp.prereq_course_id = c2.course_id
                    WHERE cp.course_id = ? AND cp.prereq_course_id = ?
                ");
                $stmt_new->bind_param("ii", $course_id, $prereq_id);
                $stmt_new->execute();
                $new_prereq = $stmt_new->get_result()->fetch_assoc();
                echo json_encode(["success" => true, "prerequisite" => $new_prereq]);
            }

        } catch (mysqli_sql_exception $e) {
            echo json_encode(["success" => false, "error" => "Error: " . $e->getMessage()]);
        }
        break;

    // EDIT
    case "edit":
        // We must identify the *original* row using its composite key
        $orig_course_id = $_POST['orig_course_id'];
        $orig_prereq_id = $_POST['orig_prereq_course_id'];
        
        // These are the *new* values from the form
        $new_course_id = $_POST['course_id'];
        $new_prereq_id = $_POST['prereq_course_id'];

        if ($new_course_id === $new_prereq_id) {
             echo json_encode(["success" => false, "error" => "A course cannot be a prerequisite of itself."]);
             break;
        }

        // Check if the *new* combination already exists
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) FROM tblcourse_prerequisite 
            WHERE course_id = ? AND prereq_course_id = ? AND is_deleted = 0
        ");
        $stmt_check->bind_param("ii", $new_course_id, $new_prereq_id);
        $stmt_check->execute();
        $count = $stmt_check->get_result()->fetch_row()[0];

        if ($count > 0) {
            echo json_encode(["success" => false, "error" => "This prerequisite relationship already exists."]);
            break;
        }
        
        // Proceed with the update
        try {
            $stmt = $conn->prepare("
                UPDATE tblcourse_prerequisite
                SET course_id = ?, prereq_course_id = ?
                WHERE course_id = ? AND prereq_course_id = ? AND is_deleted = 0
            ");
            $stmt->bind_param(
                "iiii",
                $new_course_id,
                $new_prereq_id,
                $orig_course_id,  // <-- Original key
                $orig_prereq_id   // <-- Original key
            );
            $stmt->execute();
            echo json_encode(["success" => true]);
        } catch (mysqli_sql_exception $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        break;

    //DELETE (soft)
    case "delete":
        // We must use *both* keys to identify the row to delete
        $stmt = $conn->prepare("
            UPDATE tblcourse_prerequisite 
            SET is_deleted = 1 
            WHERE course_id = ? AND prereq_course_id = ?
        ");
        $stmt->bind_param("ii", $_POST['course_id'], $_POST['prereq_course_id']);
        echo json_encode(["success" => $stmt->execute()]);
        break;

    // SEARCH
    case "search":
        $q = "%" . ($_GET['q'] ?? "") . "%";
        $stmt = $conn->prepare("
            SELECT 
                cp.course_id,
                cp.prereq_course_id,
                c1.course_code AS course_code,
                c1.course_title AS course_title,
                c2.course_code AS prereq_code,
                c2.course_title AS prereq_title
            FROM tblcourse_prerequisite cp
            LEFT JOIN tblcourse c1 ON cp.course_id = c1.course_id
            LEFT JOIN tblcourse c2 ON cp.prereq_course_id = c2.course_id
            WHERE cp.is_deleted = 0
            AND (c1.course_code LIKE ? OR c1.course_title LIKE ? OR c2.course_code LIKE ? OR c2.course_title LIKE ?)
            ORDER BY c1.course_code ASC
        ");
        $stmt->bind_param("ssss", $q, $q, $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    // DROPDOWN COURSES
    case "courses":
        $res = $conn->query("
            SELECT course_id, CONCAT(course_code, ' - ', course_title) AS name
            FROM tblcourse
            WHERE is_deleted = 0
            ORDER BY course_title ASC
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;
    
// ===== EXPORT =====
// EXPORT
    case "export":
        $type = $_GET['type'] ?? 'excel';

        $sql = "
            SELECT 
                c1.course_code AS 'Course Code',
                c1.course_title AS 'Course Title',
                c2.course_code AS 'Prerequisite Code',
                c2.course_title AS 'Prerequisite Title'
            FROM tblcourse_prerequisite cp
            LEFT JOIN tblcourse c1 ON cp.course_id = c1.course_id
            LEFT JOIN tblcourse c2 ON cp.prereq_course_id = c2.course_id
            WHERE cp.is_deleted = 0
            ORDER BY c1.course_code ASC
        ";

        $res = $conn->query($sql);
        $data = $res->fetch_all(MYSQLI_ASSOC);

        if (!$data) { echo 'No data found.'; exit; }

        if ($type === 'excel') {
            // EXCEL EXPORT
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=course_prerequisites.xls");

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
                    .header { background-color: #800000; color: white; font-weight: bold; text-align: center; font-size: 16pt; padding: 10px; }
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
                        <td colspan='4' class='header'>
                            Polytechnic University of the Philippines - Taguig Campus
                        </td>
                    </tr>
                    <tr>
                        <td colspan='4' class='sub-header' >
                            Course Prerequisite List | Date: {$date} | Page: 1
                        </td>
                    </tr>
                    <tr>";
            foreach (array_keys($data[0]) as $col) echo "<th>$col</th>";
            
            echo "</tr>";

            foreach ($data as $row) {
                echo "<tr>";
                foreach ($row as $cell) echo "<td>" . htmlspecialchars($cell) . "</td>";
                echo "</tr>";
            }
            echo "</table></body></html>";
        }

        elseif ($type === 'pdf') {
            require_once('../fpdf186/fpdf.php');

            class PDF extends FPDF {
                function Header() {
                    $this->SetTextColor(128, 0, 0);
                    $this->SetFont('Arial', 'B', 14);
                    $this->Cell(0, 10, 'Polytechnic University of the Philippines - Taguig Campus', 0, 1, 'C');
                    $this->SetDrawColor(0, 0, 0);
                    $this->SetLineWidth(0.5); // Added line width
                    $this->Line(10, $this->GetY(), 287, $this->GetY()); // Landscape width
                    $this->Ln(8);
                    $this->SetTextColor(0, 0, 0);
                }

                function Footer() {
                    $this->SetY(-15);
                    $this->SetFont('Arial', 'I', 8);
                    $this->Cell(0, 5, 'Page ' . $this->PageNo(), 0, 1, 'C');
                    $this->Cell(0, 5, 'Date Created: ' .date('F d, Y'), 0, 0, 'L');
                }

                function Row($data, $widths) {
                    $nb = 0;
                    for ($i = 0; $i < count($data); $i++) $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
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

            $pdf = new PDF('L', 'mm', 'A4'); // Set to Landscape
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Course Prerequisite List', 0, 1, 'C'); // Title

            // Adjusted column widths for 4 columns in Landscape
            $widths = [60, 80, 60, 80]; // Total 280mm
            $headers = array_keys($data[0]);

            $pdf->SetFillColor(128, 0, 0); // PUP Maroon
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 10);
            foreach ($headers as $i => $h) {
                $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 10);
            foreach ($data as $row) {
                $pdf->Row(array_values($row), $widths);
            }

            $pdf->Output('I', 'course_prerequisites.pdf');
        }
        
        break; // ✅ THIS WAS THE MISSING PIECE

    default:
        echo json_encode(["error" => "Invalid action"]);
}
?>