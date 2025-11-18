<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  // List all students
  case "list":
    $sql = "
      SELECT 
        s.student_id,
        s.student_no,
        s.last_name,
        s.first_name,
        s.email,
        s.gender,
        s.birthdate,
        s.year_level,
        s.program_id,
        CONCAT(p.program_name, ' (', p.program_code, ')') AS program_name
      FROM tblstudent s
      INNER JOIN tblprogram p ON s.program_id = p.program_id
      WHERE s.is_deleted = 0
      ORDER BY s.student_id ASC
    ";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Get programs for dropdown
  case "get_programs":
    $res = $conn->query("
      SELECT program_id, CONCAT(program_name, ' (', program_code, ')') AS program_name
      FROM tblprogram
      WHERE is_deleted = 0
      ORDER BY program_name ASC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Add student
  case "add":
    try {
      $stmt = $conn->prepare("
        INSERT INTO tblstudent 
        (student_no, last_name, first_name, email, gender, birthdate, year_level, program_id, is_deleted)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
      ");
      $stmt->bind_param(
        "ssssssii",
        $_POST['student_no'],
        $_POST['last_name'],
        $_POST['first_name'],
        $_POST['email'],
        $_POST['gender'],
        $_POST['birthdate'],
        $_POST['year_level'],
        $_POST['program_id']
      );
      $stmt->execute();

      $new_id = $conn->insert_id;
      $stmt2 = $conn->prepare("
        SELECT 
          s.student_id,
          s.student_no,
          s.last_name,
          s.first_name,
          s.email,
          s.gender,
          s.birthdate,
          s.year_level,
          s.program_id,
          CONCAT(p.program_name, ' (', p.program_code, ')') AS program_name
        FROM tblstudent s
        INNER JOIN tblprogram p ON s.program_id = p.program_id
        WHERE s.student_id = ?
      ");
      $stmt2->bind_param("i", $new_id);
      $stmt2->execute();
      $result = $stmt2->get_result();
      $new_student = $result->fetch_assoc();

      echo json_encode(["success" => true, "student" => $new_student]);
    } catch (mysqli_sql_exception $e) {
      $errorMessage = $e->getMessage();

      if (str_contains($errorMessage, "Duplicate entry")) {
        if (str_contains($errorMessage, "student_no")) {
          echo json_encode(["success" => false, "error" => "Student No. already exists."]);
        } elseif (str_contains($errorMessage, "email")) {
          echo json_encode(["success" => false, "error" => "Email already exists."]);
        } else {
          echo json_encode(["success" => false, "error" => "Duplicate entry found."]);
        }
      } else {
        echo json_encode(["success" => false, "error" => "Unexpected error: " . $errorMessage]);
      }
    }
    break;

  // Edit student
  case "edit":
    try {
      $stmt = $conn->prepare("
        UPDATE tblstudent
        SET student_no = ?, last_name = ?, first_name = ?, email = ?, gender = ?, birthdate = ?, year_level = ?, program_id = ?
        WHERE student_id = ? AND is_deleted = 0
      ");
      $stmt->bind_param(
        "ssssssiii",
        $_POST['student_no'],
        $_POST['last_name'],
        $_POST['first_name'],
        $_POST['email'],
        $_POST['gender'],
        $_POST['birthdate'],
        $_POST['year_level'],
        $_POST['program_id'],
        $_POST['student_id']
      );
      $stmt->execute();

      echo json_encode(["success" => true]);
    } catch (mysqli_sql_exception $e) {
      echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    break;

  //Soft delete student
  case "delete":
    $stmt = $conn->prepare("UPDATE tblstudent SET is_deleted = 1 WHERE student_id = ?");
    $stmt->bind_param("i", $_POST['student_id']);
    echo json_encode(["success" => $stmt->execute()]);
    break;

  // Search student
  case "search":
    $q = "%" . ($_GET['q'] ?? "") . "%";
    $stmt = $conn->prepare("
      SELECT 
        s.student_id,
        s.student_no,
        s.last_name,
        s.first_name,
        s.email,
        s.gender,
        s.birthdate,
        s.year_level,
        s.program_id,
        CONCAT(p.program_name, ' (', p.program_code, ')') AS program_name
      FROM tblstudent s
      INNER JOIN tblprogram p ON s.program_id = p.program_id
      WHERE s.is_deleted = 0
        AND (s.last_name LIKE ? OR s.first_name LIKE ? OR s.student_no LIKE ?)
      ORDER BY s.student_id ASC
    ");
    $stmt->bind_param("sss", $q, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Export to Excel / PDF
  case "export":
    $type = $_GET['type'] ?? 'excel';
    $sql = "
      SELECT 
        s.student_no AS 'Student No',
        CONCAT(s.last_name, ', ', s.first_name) AS 'Full Name',
        s.email AS 'Email',
        s.gender AS 'Gender',
        s.birthdate AS 'Birthdate',
        s.year_level AS 'Year Level',
        CONCAT(p.program_name, ' (', p.program_code, ')') AS 'Program'
      FROM tblstudent s
      INNER JOIN tblprogram p ON s.program_id = p.program_id
      WHERE s.is_deleted = 0
      ORDER BY s.student_id ASC
    ";
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    if (!$data) { echo 'No data found.'; exit; }

    if ($type === 'excel') {
      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=students.xls");

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
            <td colspan='7' class='header'>
              Polytechnic University of the Philippines - Taguig Campus
            </td>
          </tr>
          <tr>
            <td colspan='7' class='sub-header'>
              Student List | Date: {$date} | Page: 1
            </td>
          </tr>
          <tr>";
          foreach (array_keys($data[0]) as $col) {
            echo "<th>$col</th>";
          }
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
          $this->SetLineWidth(0.5);
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

      $pdf = new PDF('L', 'mm', 'A4');
      $pdf->AliasNbPages();
      $pdf->AddPage();
      $pdf->SetFont('Arial', 'B', 12);

      $widths = [35, 45, 55, 20, 25, 25, 70];
      $headers = ['Student No', 'Full Name', 'Email', 'Gender', 'Birthdate', 'Year Level', 'Program'];

      $pdf->SetFillColor(128, 0, 0);
      $pdf->SetTextColor(255, 255, 255);
      foreach ($headers as $i => $h) {
        $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
      }
      $pdf->Ln();

      $pdf->SetTextColor(0, 0, 0);
      $pdf->SetFont('Arial', '', 10);
      foreach ($data as $row) {
        $pdf->Row(array_values($row), $widths);
      }

      $pdf->Output('I', 'students.pdf');
    }
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>