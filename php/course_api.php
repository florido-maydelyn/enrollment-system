<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  // List all courses
  case "list":
    $sql = "
      SELECT c.*, d.dept_name
      FROM tblcourse c
      LEFT JOIN tbldepartment d ON c.dept_id = d.dept_id
      WHERE c.is_deleted = 0
      ORDER BY c.course_id ASC";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Get departments for dropdown
  case "get_departments":
    $res = $conn->query("SELECT dept_id, dept_name 
                          FROM tbldepartment 
                          WHERE is_deleted = 0 
                          ORDER BY dept_name ASC");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // Add course
  case "add":
    $course_code = $_POST['course_code'];
    $course_title = $_POST['course_title'];
    $units = $_POST['units'];
    $lecture_hours = $_POST['lecture_hours'];
    $lab_hours = $_POST['lab_hours'];
    $dept_id = $_POST['dept_id'];

    try {
        $stmt = $conn->prepare("
            INSERT INTO tblcourse (course_code, course_title, units, lecture_hours, lab_hours, dept_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssddii", $course_code, $course_title, $units, $lecture_hours, $lab_hours, $dept_id);
        $stmt->execute();

        // Get the last inserted course to return
        $new_id = $conn->insert_id;
        $sql = "
            SELECT c.*, d.dept_name
            FROM tblcourse c
            LEFT JOIN tbldepartment d ON c.dept_id = d.dept_id
            WHERE c.course_id = ?
        ";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param("i", $new_id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $new_course = $result->fetch_assoc();

        echo json_encode(["success" => true, "course" => $new_course]);
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), "Duplicate entry")) {
            echo json_encode(["success" => false, "error" => "Course code already exists."]);
        } else {
            echo json_encode(["success" => false, "error" => "Unexpected error: " . $e->getMessage()]);
        }
    }
    break;

  // Edit course
  case "edit":
    try {
      $stmt = $conn->prepare("
        UPDATE tblcourse 
        SET course_code=?, course_title=?, units=?, lecture_hours=?, lab_hours=?, dept_id=? 
        WHERE course_id=?
      ");
      $stmt->bind_param(
        "ssdiiii", 
        $_POST['course_code'], 
        $_POST['course_title'], 
        $_POST['units'], 
        $_POST['lecture_hours'], 
        $_POST['lab_hours'], 
        $_POST['dept_id'], 
        $_POST['course_id']
      );
      $stmt->execute();

      echo json_encode(["success" => true]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Course code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
      }
    }
    break;

  // Delete course
  case "delete":
    $stmt = $conn->prepare("UPDATE tblcourse SET is_deleted = 1 WHERE course_id = ?");
    $stmt->bind_param("i", $_POST['course_id']);
    echo json_encode(["success" => $stmt->execute()]);
    break;

  // Search
  case "search":
    $q = "%" . ($_GET['q'] ?? "") . "%";
    $stmt = $conn->prepare("
      SELECT c.*, d.dept_name
      FROM tblcourse c
      LEFT JOIN tbldepartment d ON c.dept_id = d.dept_id
      WHERE c.is_deleted = 0
        AND (c.course_code LIKE ? OR c.course_title LIKE ?)
      ORDER BY c.course_id ASC
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
        c.course_code AS 'Course Code',
        c.course_title AS 'Title',
        c.units AS 'Units',
        c.lecture_hours AS 'Lecture Hours',
        c.lab_hours AS 'Lab Hours',
        d.dept_name AS 'Department'
      FROM tblcourse c
      LEFT JOIN tbldepartment d ON c.dept_id = d.dept_id
      WHERE c.is_deleted = 0
      ORDER BY c.course_id ASC";
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    if (!$data) { echo 'No data found.'; exit; }

    if ($type === 'excel') {
      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=courses.xls");

      // Get current date/time
      date_default_timezone_set('Asia/Manila');
      $date = date("F d, Y - h:i A");

      echo "
      <html>
      <head>
        <meta charset='UTF-8'>
        <style>
          body { font-family: Arial, sans-serif; }
          table {
            border-collapse: collapse;
            width: 100%;
          }
          th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
          }
          th {
            background-color: #f2f2f2;
          }
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
            <td colspan='6' class='header'>
              Polytechnic University of the Philippines - Taguig Campus
            </td>
          </tr>
          <tr>
            <td colspan='6' class='sub-header'>
              Course List | Date: {$date} | Page: 1
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
          if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
          $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
          $s = str_replace("\r", '', $txt);
          $nb = strlen($s);
          if ($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
          $sep = -1;
          $i = 0;
          $j = 0;
          $l = 0;
          $nl = 1;
          while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
              $i++;
              $sep = -1;
              $j = $i;
              $l = 0;
              $nl++;
              continue;
            }
            if ($c == ' ')
              $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
              if ($sep == -1) {
                if ($i == $j)
                  $i++;
              } else
                $i = $sep + 1;
              $sep = -1;
              $j = $i;
              $l = 0;
              $nl++;
            } else
              $i++;
          }
          return $nl;
        }
      }

      $pdf = new PDF();
      $pdf->AliasNbPages();
      $pdf->AddPage();
      $pdf->SetFont('Arial', 'B', 12);

      // Table Header
      $widths = [30, 50, 15, 25, 25, 45];
      $headers = ['Course Code','Title','Units','Lecture','Lab','Department'];

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

      $pdf->Output('I', 'courses.pdf');
    }
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>
