<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  // List all programs
  case "list":
    $sql = "
      SELECT p.*, d.dept_name
      FROM tblprogram p
      LEFT JOIN tbldepartment d ON p.dept_id = d.dept_id
      WHERE p.is_deleted = 0
      ORDER BY p.program_id ASC";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Get departments for dropdown
  case "get_departments":
    $res = $conn->query("
      SELECT dept_id, CONCAT(dept_name, ' (', dept_code, ')') AS department
      FROM tbldepartment
      WHERE is_deleted = 0
      ORDER BY dept_name ASC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Add program
  case "add":
    $code = $_POST['program_code'];
    $name = $_POST['program_name'];
    $dept = $_POST['dept_id'];

    try {
      $stmt = $conn->prepare("
        INSERT INTO tblprogram (program_code, program_name, dept_id, is_deleted)
        VALUES (?, ?, ?, 0)
      ");
      $stmt->bind_param("ssi", $code, $name, $dept);
      $stmt->execute();

      // Return newly added program
      $new_id = $conn->insert_id;
      $stmt2 = $conn->prepare("
        SELECT 
          p.program_id,
          p.program_code,
          p.program_name,
          CONCAT(d.dept_name, ' (', d.dept_code, ')') AS department
        FROM tblprogram p
        INNER JOIN tbldepartment d ON p.dept_id = d.dept_id
        WHERE p.program_id = ?
      ");
      $stmt2->bind_param("i", $new_id);
      $stmt2->execute();
      $result = $stmt2->get_result();
      $new_program = $result->fetch_assoc();

      echo json_encode(["success" => true, "program" => $new_program]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Program code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => "Unexpected error: " . $e->getMessage()]);
      }
    }
    break;

  // Edit program
  case "edit":
    try {
      $stmt = $conn->prepare("
        UPDATE tblprogram 
        SET program_code = ?, program_name = ?, dept_id = ?
        WHERE program_id = ? AND is_deleted = 0
      ");
      $stmt->bind_param(
        "ssii",
        $_POST['program_code'],
        $_POST['program_name'],
        $_POST['dept_id'],
        $_POST['program_id']
      );
      $stmt->execute();

      echo json_encode(["success" => true]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Program code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
      }
    }
    break;

  // Soft delete program
  case "delete":
    $stmt = $conn->prepare("UPDATE tblprogram SET is_deleted = 1 WHERE program_id = ?");
    $stmt->bind_param("i", $_POST['program_id']);
    echo json_encode(["success" => $stmt->execute()]);
    break;

  // Search program
  case "search":
    $q = "%" . ($_GET['q'] ?? "") . "%";
    $stmt = $conn->prepare("
      SELECT 
        p.program_id,
        p.program_code,
        p.program_name,
        CONCAT(d.dept_name, ' (', d.dept_code, ')') AS department
      FROM tblprogram p
      INNER JOIN tbldepartment d ON p.dept_id = d.dept_id
      WHERE p.is_deleted = 0
        AND (p.program_code LIKE ? OR p.program_name LIKE ?)
      ORDER BY p.program_id ASC
    ");
    $stmt->bind_param("ss", $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Export to Excel / PDF
  case "export":
    $type = $_GET['type'] ?? 'excel';
    $sql = "
      SELECT 
        p.program_code AS 'Program Code',
        p.program_name AS 'Program Name',
        CONCAT(d.dept_name, ' (', d.dept_code, ')') AS 'Department'
      FROM tblprogram p
      INNER JOIN tbldepartment d ON p.dept_id = d.dept_id
      WHERE p.is_deleted = 0
      ORDER BY p.program_id ASC
    ";
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    if (!$data) { echo 'No data found.'; exit; }

    if ($type === 'excel') {
      // 📊 Excel Export
      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=programs.xls");

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
              Program List | Date: {$date} | Page: 1
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
      $headers = ['Program Code', 'Program Name', 'Department'];
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

      $pdf->Output('I', 'programs.pdf');
    }
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>