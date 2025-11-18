<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  // Get Departments for Dropdown
  case "get_departments":
    $res = $conn->query("
      SELECT dept_id, CONCAT(dept_name, ' (', dept_code, ')') AS department
      FROM tbldepartment
      WHERE is_deleted = 0
      ORDER BY dept_name ASC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // List All Instructors
  case "list":
    $sql = "
      SELECT 
        i.instructor_id,
        i.first_name,
        i.last_name,
        i.email,
        i.dept_id,
        CONCAT(d.dept_name, ' (', d.dept_code, ')') AS department
      FROM tblinstructor i
      INNER JOIN tbldepartment d ON i.dept_id = d.dept_id
      WHERE i.is_deleted = 0
      ORDER BY i.instructor_id ASC
    ";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Add Instructor
  case "add":
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];
    $dept = $_POST['dept_id'];

    try {
      $stmt = $conn->prepare("
        INSERT INTO tblinstructor (first_name, last_name, email, dept_id, is_deleted)
        VALUES (?, ?, ?, ?, 0)
      ");
      $stmt->bind_param("sssi", $first, $last, $email, $dept);
      $stmt->execute();

      // Return newly added instructor
      $new_id = $conn->insert_id;
      $stmt2 = $conn->prepare("
        SELECT 
          i.instructor_id,
          i.first_name,
          i.last_name,
          i.email,
          i.dept_id,
          CONCAT(d.dept_name, ' (', d.dept_code, ')') AS department
        FROM tblinstructor i
        INNER JOIN tbldepartment d ON i.dept_id = d.dept_id
        WHERE i.instructor_id = ?
      ");
      $stmt2->bind_param("i", $new_id);
      $stmt2->execute();
      $result = $stmt2->get_result();
      $new_instructor = $result->fetch_assoc();

      echo json_encode(["success" => true, "instructor" => $new_instructor]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Email already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => "Unexpected error: " . $e->getMessage()]);
      }
    }
    break;

  // Edit Instructor
  case "edit":
    try {
      $stmt = $conn->prepare("
        UPDATE tblinstructor 
        SET first_name = ?, last_name = ?, email = ?, dept_id = ?
        WHERE instructor_id = ? AND is_deleted = 0
      ");
      $stmt->bind_param("sssii",
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['email'],
        $_POST['dept_id'],
        $_POST['instructor_id']
      );
      $stmt->execute();

      echo json_encode(["success" => true]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Email already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
      }
    }
    break;

  //Soft Delete Instructor
  case "delete":
    $stmt = $conn->prepare("UPDATE tblinstructor SET is_deleted = 1 WHERE instructor_id = ?");
    $stmt->bind_param("i", $_POST['instructor_id']);
    echo json_encode(["success" => $stmt->execute()]);
    break;

  // Search Instructors
  case "search":
    $q = "%" . ($_GET['q'] ?? "") . "%";
    $stmt = $conn->prepare("
      SELECT 
        i.instructor_id,
        i.first_name,
        i.last_name,
        i.email,
        i.dept_id,
        CONCAT(d.dept_name, ' (', d.dept_code, ')') AS department
      FROM tblinstructor i
      INNER JOIN tbldepartment d ON i.dept_id = d.dept_id
      WHERE i.is_deleted = 0
        AND (i.first_name LIKE ? OR i.last_name LIKE ? OR i.email LIKE ?)
      ORDER BY i.instructor_id ASC
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
        CONCAT(i.first_name, ' ', i.last_name) AS 'Full Name',
        i.email AS 'Email',
        CONCAT(d.dept_name, ' (', d.dept_code, ')') AS 'Department'
      FROM tblinstructor i
      INNER JOIN tbldepartment d ON i.dept_id = d.dept_id
      WHERE i.is_deleted = 0
      ORDER BY i.instructor_id ASC
    ";
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    if (!$data) { echo 'No data found.'; exit; }

    if ($type === 'excel') {
      //Excel Export
      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=instructors.xls");

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
            <td colspan='3' class='header'>
              Polytechnic University of the Philippines - Taguig Campus
            </td>
          </tr>
          <tr>
            <td colspan='3' class='sub-header'>
              Instructor List | Date: {$date} | Page: 1
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
          $this->Line(10, $this->GetY(), 200, $this->GetY());
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

      $pdf = new PDF();
      $pdf->AliasNbPages();
      $pdf->AddPage();
      $pdf->SetFont('Arial', 'B', 12);

      // Table Header
      $widths = [50, 70, 70];
      $headers = ['Full Name', 'Email', 'Department'];
      $pdf->SetFillColor(128, 0, 0);
      $pdf->SetTextColor(255, 255, 255);
      foreach ($headers as $i => $h) {
        $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
      }
      $pdf->Ln();

      // Table Body
      $pdf->SetTextColor(0, 0, 0);
      $pdf->SetFont('Arial', '', 10);
      foreach ($data as $row) {
        $pdf->Row(array_values($row), $widths);
      }

      $pdf->Output('I', 'instructors.pdf');
    }
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>