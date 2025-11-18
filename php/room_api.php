<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

  // ===================== LIST =====================
  case "list":
    $res = $conn->query("
      SELECT room_id, building, room_code, capacity
      FROM tblroom
      WHERE is_deleted = 0
      ORDER BY room_id ASC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    break;

  // ===================== ADD =====================
  case "add":
    $building = $_POST['building'];
    $room_code = $_POST['room_code'];
    $capacity = $_POST['capacity'];

    try {
      $stmt = $conn->prepare("
        INSERT INTO tblroom (building, room_code, capacity, is_deleted)
        VALUES (?, ?, ?, 0)
      ");
      $stmt->bind_param("ssi", $building, $room_code, $capacity);
      $stmt->execute();

      $new_id = $conn->insert_id;
      $stmt2 = $conn->prepare("SELECT * FROM tblroom WHERE room_id = ?");
      $stmt2->bind_param("i", $new_id);
      $stmt2->execute();
      $result = $stmt2->get_result();
      $new_room = $result->fetch_assoc();

      echo json_encode(["success" => true, "room" => $new_room]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Room code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
      }
    }
    break;

  // ===================== EDIT =====================
  case "edit":
    try {
      $stmt = $conn->prepare("
        UPDATE tblroom
        SET building = ?, room_code = ?, capacity = ?
        WHERE room_id = ? AND is_deleted = 0
      ");
      $stmt->bind_param(
        "ssii",
        $_POST['building'],
        $_POST['room_code'],
        $_POST['capacity'],
        $_POST['room_id']
      );
      $stmt->execute();

      echo json_encode(["success" => true]);
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate entry")) {
        echo json_encode(["success" => false, "error" => "Room code already exists."]);
      } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
      }
    }
    break;

  // ===================== SOFT DELETE =====================
  case "delete":
    $id = $_POST['room_id'];
    $stmt = $conn->prepare("
      UPDATE tblroom 
      SET is_deleted = 1 
      WHERE room_id = ?
    ");
    $stmt->bind_param("i", $id);
    echo json_encode(["success" => $stmt->execute()]);
    break;

  // ===================== SEARCH =====================
  case "search":
    $q = "%" . ($_GET['q'] ?? "") . "%";
    $stmt = $conn->prepare("
      SELECT room_id, building, room_code, capacity
      FROM tblroom
      WHERE is_deleted = 0
        AND (building LIKE ? OR room_code LIKE ?)
      ORDER BY room_id ASC
    ");
    $stmt->bind_param("ss", $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    break;

  // ===================== EXPORT =====================
  case "export":
    $type = $_GET['type'] ?? 'excel';
    $sql = "
      SELECT 
        building AS 'Building',
        room_code AS 'Room Code',
        capacity AS 'Capacity'
      FROM tblroom
      WHERE is_deleted = 0
      ORDER BY room_id ASC
    ";
    $res = $conn->query($sql);
    $data = $res->fetch_all(MYSQLI_ASSOC);

    if (!$data) { echo 'No data found.'; exit; }

    // ===================== EXPORT EXCEL =====================
    if ($type === 'excel') {
      header("Content-Type: application/vnd.ms-excel");
      header("Content-Disposition: attachment; filename=rooms.xls");

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
              Room List | Date: {$date} | Page: 1
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

    // ===================== EXPORT PDF =====================
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
          $this->Cell(0, 5, 'Date Created: ' . date('F d, Y'), 0, 0, 'L');
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
          if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
          $sep = -1;
          $i = 0; $j = 0; $l = 0; $nl = 1;
          while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
              if ($sep == -1) {
                if ($i == $j) $i++;
              } else $i = $sep + 1;
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

      $widths = [60, 60, 60];
      $headers = ['Building', 'Room Code', 'Capacity'];

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

      $pdf->Output('I', 'rooms.pdf');
    }

    else {
      echo json_encode(["error" => "Invalid export type"]);
    }

    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>