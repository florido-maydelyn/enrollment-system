<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  // List all sections
  case "list":
    $sql = "
      SELECT 
        s.section_id,
        s.section_code,
        s.course_id,
        s.term_id,
        s.instructor_id,
        s.day_pattern,
        s.start_time,
        s.end_time,
        s.room_id,
        s.max_capacity,
        c.course_title AS course_name,
        t.term_code AS term_name,
        CONCAT(i.first_name, ' ', i.last_name) AS instructor_name,
        r.room_code
      FROM tblsection s
      LEFT JOIN tblcourse c ON s.course_id = c.course_id
      LEFT JOIN tblterm t ON s.term_id = t.term_id
      LEFT JOIN tblinstructor i ON s.instructor_id = i.instructor_id
      LEFT JOIN tblroom r ON s.room_id = r.room_id
      WHERE s.is_deleted = 0
      ORDER BY s.section_id ASC
    ";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Add section
  case "add":
    try {
      $stmt = $conn->prepare("
        INSERT INTO tblsection 
          (section_code, course_id, term_id, instructor_id, day_pattern, start_time, end_time, room_id, max_capacity, is_deleted)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
      ");
      $stmt->bind_param(
        "siiisssii",
        $_POST['section_code'],
        $_POST['course_id'],
        $_POST['term_id'],
        $_POST['instructor_id'],
        $_POST['day_pattern'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['room_id'],
        $_POST['max_capacity']
      );
      $stmt->execute();
      $new_id = $conn->insert_id;

      $stmt2 = $conn->prepare("
        SELECT 
          s.section_id,
          s.section_code,
          c.course_title AS course_name,
          t.term_code AS term_name,
          CONCAT(i.first_name, ' ', i.last_name) AS instructor_name,
          s.day_pattern,
          s.start_time,
          s.end_time,
          r.room_code,
          s.max_capacity
        FROM tblsection s
        LEFT JOIN tblcourse c ON s.course_id = c.course_id
        LEFT JOIN tblterm t ON s.term_id = t.term_id
        LEFT JOIN tblinstructor i ON s.instructor_id = i.instructor_id
        LEFT JOIN tblroom r ON s.room_id = r.room_id
        WHERE s.section_id = ?
      ");
      $stmt2->bind_param("i", $new_id);
      $stmt2->execute();
      $result = $stmt2->get_result();
      $new_section = $result->fetch_assoc();

      echo json_encode(["success" => true, "section" => $new_section]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Section code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => "Unexpected error: " . $e->getMessage()]);
      }
    }
    break;

  // Edit section
  case "edit":
    try {
      $stmt = $conn->prepare("
        UPDATE tblsection
        SET section_code=?, course_id=?, term_id=?, instructor_id=?, day_pattern=?, start_time=?, end_time=?, room_id=?, max_capacity=?
        WHERE section_id=? AND is_deleted = 0
      ");
      $stmt->bind_param(
        "siiisssiii",
        $_POST['section_code'],
        $_POST['course_id'],
        $_POST['term_id'],
        $_POST['instructor_id'],
        $_POST['day_pattern'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['room_id'],
        $_POST['max_capacity'],
        $_POST['section_id']
      );
      $stmt->execute();
      echo json_encode(["success" => true]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Section code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
      }
    }
    break;

  //Soft delete section
  case "delete":
    $stmt = $conn->prepare("UPDATE tblsection SET is_deleted = 1 WHERE section_id = ?");
    $stmt->bind_param("i", $_POST['section_id']);
    echo json_encode(["success" => $stmt->execute()]);
    break;

  // Search section
  case "search":
    $q = "%" . ($_GET['q'] ?? "") . "%";
    $stmt = $conn->prepare("
      SELECT 
        s.section_id,
        s.section_code,
        c.course_title AS course_name,
        t.term_code AS term_name,
        CONCAT(i.first_name, ' ', i.last_name) AS instructor_name,
        s.day_pattern,
        s.start_time,
        s.end_time,
        r.room_code,
        s.max_capacity
      FROM tblsection s
      LEFT JOIN tblcourse c ON s.course_id = c.course_id
      LEFT JOIN tblterm t ON s.term_id = t.term_id
      LEFT JOIN tblinstructor i ON s.instructor_id = i.instructor_id
      LEFT JOIN tblroom r ON s.room_id = r.room_id
      WHERE s.is_deleted = 0
      AND (s.section_code LIKE ? OR c.course_title LIKE ? OR t.term_code LIKE ?)
      ORDER BY s.section_id ASC
    ");
    $stmt->bind_param("sss", $q, $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    break;

  // Dropdowns
  case "courses":
    $res = $conn->query("SELECT course_id, CONCAT(course_code, ' - ', course_title) AS name FROM tblcourse WHERE is_deleted = 0 ORDER BY course_title ASC");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  case "terms":
    $res = $conn->query("SELECT term_id, term_code AS name FROM tblterm WHERE is_deleted = 0 ORDER BY term_id DESC");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  case "instructors":
    $res = $conn->query("SELECT instructor_id, CONCAT(first_name, ' ', last_name) AS name FROM tblinstructor WHERE is_deleted = 0 ORDER BY first_name ASC");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  case "rooms":
    $res = $conn->query("SELECT room_id, room_code AS name FROM tblroom WHERE is_deleted = 0 ORDER BY room_code ASC");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Export Section List
  case "export":
    $type = $_GET['type'] ?? 'excel';
    $sql = "
      SELECT 
        s.section_code AS 'Section Code',
        c.course_title AS 'Course',
        t.term_code AS 'Term',
        CONCAT(i.first_name, ' ', i.last_name) AS 'Instructor',
        s.day_pattern AS 'Day Pattern',
        s.start_time AS 'Start Time',
        s.end_time AS 'End Time',
        r.room_code AS 'Room',
        s.max_capacity AS 'Max Capacity'
      FROM tblsection s
      LEFT JOIN tblcourse c ON s.course_id = c.course_id
      LEFT JOIN tblterm t ON s.term_id = t.term_id
      LEFT JOIN tblinstructor i ON s.instructor_id = i.instructor_id
      LEFT JOIN tblroom r ON s.room_id = r.room_id
      WHERE s.is_deleted = 0
      ORDER BY s.section_id ASC
    ";
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    if (!$data) { echo 'No data found.'; exit; }

    if ($type === 'excel') {
      // Excel Export
      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=sections.xls");

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
          .sub-header { text-align: center; font-size: 10pt; color: #333; background-color: #f9f9f9; }
        </style>
      </head>
      <body>
        <table>
          <tr>
            <td colspan='9' class='header'>
              Polytechnic University of the Philippines - Taguig Campus
            </td>
          </tr>
          <tr>
            <td colspan='9' class='sub-header'>
              Section List | Date: {$date} | Page: 1
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
          $this->Line(10, $this->GetY(), 287, $this->GetY());
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

      $pdf = new PDF('L', 'mm', 'A4');
      $pdf->AliasNbPages();
      $pdf->AddPage();
      $pdf->SetFont('Arial', 'B', 12);

      $widths = [30, 50, 25, 40, 30, 25, 25, 30, 25];
      $headers = array_keys($data[0]);
      $pdf->SetFillColor(128, 0, 0);
      $pdf->SetTextColor(255);
      foreach ($headers as $i => $h) $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
      $pdf->Ln();

      $pdf->SetTextColor(0);
      $pdf->SetFont('Arial', '', 10);
      foreach ($data as $row) $pdf->Row(array_values($row), $widths);

      $pdf->Output('I', 'sections.pdf');
    }
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>
