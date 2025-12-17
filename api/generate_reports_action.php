<?php
/**
 * generate_reports_action.php
 * Generates various reports in PDF, Excel (CSV), and PowerPoint (PPTX) formats
 * 
 * =====================================================================
 * IMPLEMENTED REPORTS:
 * =====================================================================
 * 1. Booking Summary Report (PDF, Excel, PPTX)
 * 2. Room Usage Report      (PDF, Excel, PPTX)
 * 3. User Activity Report   (PDF, Excel, PPTX)
 * 4. Admin Log Report       (PDF, Excel, PPTX)
 * =====================================================================
 */

session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Security check - Admin only
if (!isset($_SESSION['loggedin']) || strcasecmp($_SESSION['User_Type'], 'Admin') !== 0) {
    exit("Access Denied");
}

$report = $_GET['report'] ?? '';
$format = $_GET['format'] ?? '';
$period = $_GET['period'] ?? '';

// Validate format (ppt = PowerPoint HTML format)
$allowedFormats = ['pdf', 'excel', 'ppt'];
if (!in_array($format, $allowedFormats)) {
    exit("Unsupported File Format. Allowed: pdf, excel, ppt");
}

// Validate report type
$allowedReports = ['adminlog', 'booking', 'room', 'user'];
if (!in_array($report, $allowedReports)) {
    exit("Invalid Report Type. Allowed: adminlog, booking, room, user");
}

// Validate and process time period
$allowedPeriods = ['7days', '30days', '6months', '12months'];
if (!in_array($period, $allowedPeriods)) {
    exit("Invalid Time Period. Please select a valid data collection period.");
}

// Calculate date range based on selected period
$periodLabels = [
    '7days' => 'Last 7 Days',
    '30days' => 'Last 30 Days',
    '6months' => 'Last 6 Months',
    '12months' => 'Last 12 Months'
];
$periodFileNames = [
    '7days' => 'Last_7_Days',
    '30days' => 'Last_30_Days',
    '6months' => 'Last_6_Months',
    '12months' => 'Last_12_Months'
];

$periodLabel = $periodLabels[$period];
$periodFileName = $periodFileNames[$period];

// Calculate start date based on period
$endDate = date('Y-m-d');
switch ($period) {
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case '6months':
        $startDate = date('Y-m-d', strtotime('-6 months'));
        break;
    case '12months':
        $startDate = date('Y-m-d', strtotime('-12 months'));
        break;
}

// Format dates for display
$startDateFormatted = date('d M Y', strtotime($startDate));
$endDateFormatted = date('d M Y', strtotime($endDate));
$dateRangeDisplay = "Data from: $startDateFormatted – $endDateFormatted";

// Include FPDF for PDF generation
require_once(__DIR__ . '/../libs/fpdf/fpdf.php');

/* =====================================================================
   HELPER: Base PDF Class with common header/footer
   ===================================================================== */
class ReportPDF extends FPDF {
    public $reportTitle = 'Report';
    public $periodLabel = '';
    public $dateRange = '';
    public $headers = [];
    public $colWidths = [];
    
    function Header() {
        // Logo placeholder
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, $this->reportTitle, 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, "Generated: " . date('Y-m-d H:i:s'), 0, 1, 'C');
        
        // Add period info
        if (!empty($this->periodLabel)) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(37, 99, 235); // Primary blue
            $this->Cell(0, 6, "Report Period: " . $this->periodLabel, 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 5, $this->dateRange, 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }
        $this->Ln(5);
        
        // Table header
        if (!empty($this->headers)) {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(52, 73, 94); // Dark blue
            $this->SetTextColor(255, 255, 255); // White text
            foreach ($this->headers as $i => $header) {
                $w = isset($this->colWidths[$i]) ? $this->colWidths[$i] : 30;
                $this->Cell($w, 8, $header, 1, 0, 'C', true);
            }
            $this->Ln();
            $this->SetTextColor(0, 0, 0); // Reset to black
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $periodInfo = !empty($this->periodLabel) ? " | " . $this->periodLabel : "";
        $this->Cell(0, 10, "Page " . $this->PageNo() . " | " . $this->reportTitle . $periodInfo . " | Room Reservation System", 0, 0, 'C');
    }
    
    // Helper to calculate row height for multi-line cells
    function NbLines($w, $txt) {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            if (isset($cw[$c])) $l += $cw[$c]; else $l += 500;
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

/* =====================================================================
   HELPER: Export data to CSV (Excel-compatible)
   ===================================================================== */
function exportToCSV($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/* =====================================================================
   HELPER: Export data to real PowerPoint .pptx format with PIE CHARTS
   PPTX is a ZIP file containing XML files - we build it manually
   ===================================================================== */
function exportToPPT($filename, $title, $slides) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Create temp file for the ZIP with unique name
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'pptx_' . uniqid() . '.zip';
    
    // Ensure temp directory exists and is writable
    if (!is_writable($tempDir)) {
        exit("Error: Temp directory is not writable");
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($result !== true) {
        exit("Cannot create PowerPoint file. Error code: " . $result);
    }
    
    // Count slides with charts (pie_chart data)
    $chartCount = 0;
    $slideChartMap = []; // Maps slide number to chart number
    foreach ($slides as $idx => $slide) {
        if (isset($slide['pie_chart']) && !empty($slide['pie_chart'])) {
            $chartCount++;
            $slideChartMap[$idx + 1] = $chartCount;
        }
    }
    
    // [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
    <Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>
    <Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>
    <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>';
    
    for ($i = 1; $i <= count($slides); $i++) {
        $contentTypes .= '
    <Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
    }
    
    // Add chart content types
    for ($i = 1; $i <= $chartCount; $i++) {
        $contentTypes .= '
    <Override PartName="/ppt/charts/chart' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
    }
    
    $contentTypes .= '
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    
    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);
    
    // docProps/core.xml
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>' . htmlspecialchars($title) . '</dc:title>
    <dc:creator>Room Reservation System</dc:creator>
    <dcterms:created xsi:type="dcterms:W3CDTF">' . date('Y-m-d\TH:i:s\Z') . '</dcterms:created>
</cp:coreProperties>';
    $zip->addFromString('docProps/core.xml', $core);
    
    // docProps/app.xml
    $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>Room Reservation System</Application>
    <Slides>' . count($slides) . '</Slides>
</Properties>';
    $zip->addFromString('docProps/app.xml', $app);
    
    // ppt/presentation.xml
    $presentation = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>
    <p:sldIdLst>';
    
    for ($i = 1; $i <= count($slides); $i++) {
        $presentation .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . ($i + 2) . '"/>';
    }
    
    $presentation .= '</p:sldIdLst>
    <p:sldSz cx="9144000" cy="6858000" type="screen4x3"/>
    <p:notesSz cx="6858000" cy="9144000"/>
</p:presentation>';
    $zip->addFromString('ppt/presentation.xml', $presentation);
    
    // ppt/_rels/presentation.xml.rels
    $presRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>';
    
    for ($i = 1; $i <= count($slides); $i++) {
        $presRels .= '
    <Relationship Id="rId' . ($i + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
    }
    
    $presRels .= '
</Relationships>';
    $zip->addFromString('ppt/_rels/presentation.xml.rels', $presRels);
    
    // Theme
    $theme = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">
    <a:themeElements>
        <a:clrScheme name="Office">
            <a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>
            <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
            <a:dk2><a:srgbClr val="44546A"/></a:dk2>
            <a:lt2><a:srgbClr val="E7E6E6"/></a:lt2>
            <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
            <a:accent2><a:srgbClr val="ED7D31"/></a:accent2>
            <a:accent3><a:srgbClr val="A5A5A5"/></a:accent3>
            <a:accent4><a:srgbClr val="FFC000"/></a:accent4>
            <a:accent5><a:srgbClr val="5B9BD5"/></a:accent5>
            <a:accent6><a:srgbClr val="70AD47"/></a:accent6>
            <a:hlink><a:srgbClr val="0563C1"/></a:hlink>
            <a:folHlink><a:srgbClr val="954F72"/></a:folHlink>
        </a:clrScheme>
        <a:fontScheme name="Office">
            <a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>
            <a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>
        </a:fontScheme>
        <a:fmtScheme name="Office">
            <a:fillStyleLst>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
                <a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="50000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"/></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill>
                <a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="50000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"/></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill>
            </a:fillStyleLst>
            <a:lnStyleLst>
                <a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
                <a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
                <a:ln w="25400"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
            </a:lnStyleLst>
            <a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>
            <a:bgFillStyleLst>
                <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
                <a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="40000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"/></a:gs></a:gsLst><a:path path="circle"><a:fillToRect l="50000" t="-80000" r="50000" b="180000"/></a:path></a:gradFill>
                <a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="80000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"/></a:gs></a:gsLst><a:path path="circle"><a:fillToRect l="50000" t="50000" r="50000" b="50000"/></a:path></a:gradFill>
            </a:bgFillStyleLst>
        </a:fmtScheme>
    </a:themeElements>
</a:theme>';
    $zip->addFromString('ppt/theme/theme1.xml', $theme);
    
    // Slide Master
    $slideMaster = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <p:cSld>
        <p:bg><p:bgRef idx="1001"><a:schemeClr val="bg1"/></p:bgRef></p:bg>
        <p:spTree>
            <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
            <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
        </p:spTree>
    </p:cSld>
    <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>
    <p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>
</p:sldMaster>';
    $zip->addFromString('ppt/slideMasters/slideMaster1.xml', $slideMaster);
    
    // Slide Master rels
    $smRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>
</Relationships>';
    $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $smRels);
    
    // Slide Layout
    $slideLayout = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" type="blank">
    <p:cSld name="Blank">
        <p:spTree>
            <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
            <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
        </p:spTree>
    </p:cSld>
    <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sldLayout>';
    $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', $slideLayout);
    
    // Slide Layout rels
    $slRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>
</Relationships>';
    $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $slRels);
    
    // Generate each slide and charts
    $slideNum = 1;
    foreach ($slides as $slide) {
        $hasChart = isset($slide['pie_chart']) && !empty($slide['pie_chart']);
        $chartNum = $hasChart ? $slideChartMap[$slideNum] : 0;
        
        $slideXml = generateSlideXml($slide, $slideNum, count($slides), $hasChart, $chartNum);
        $zip->addFromString('ppt/slides/slide' . $slideNum . '.xml', $slideXml);
        
        // Slide rels - include chart reference if this slide has a chart
        $slideRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>';
        
        if ($hasChart) {
            $slideRels .= '
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart' . $chartNum . '.xml"/>';
            
            // Generate chart XML
            $chartXml = generatePieChartXml($slide['pie_chart'], $slide['title'] ?? 'Chart');
            $zip->addFromString('ppt/charts/chart' . $chartNum . '.xml', $chartXml);
        }
        
        $slideRels .= '
</Relationships>';
        $zip->addFromString('ppt/slides/_rels/slide' . $slideNum . '.xml.rels', $slideRels);
        
        $slideNum++;
    }
    
    // Close the ZIP archive properly
    $closeResult = $zip->close();
    if ($closeResult !== true) {
        exit("Error closing ZIP file");
    }
    
    // Verify the file exists and has content
    if (!file_exists($tempFile) || filesize($tempFile) === 0) {
        exit("Error: Generated file is empty or does not exist");
    }
    
    // Output the file
    $pptxFilename = str_replace('.ppt', '.pptx', $filename);
    if (substr($pptxFilename, -5) !== '.pptx') {
        $pptxFilename .= '.pptx';
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
    header('Content-Disposition: attachment; filename="' . $pptxFilename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Read and output the file
    readfile($tempFile);
    
    // Clean up temp file
    @unlink($tempFile);
    exit;
}

/* =====================================================================
   HELPER: Generate Pie Chart XML with proper styling
   ===================================================================== */
function generatePieChartXml($chartData, $chartTitle) {
    // $chartData should be an associative array: ['Label' => value, ...]
    $labels = array_keys($chartData);
    $values = array_values($chartData);
    $total = array_sum($values);
    
    // Vibrant colors for pie slices
    $colors = ['4472C4', 'ED7D31', 'FFC000', '70AD47', '5B9BD5', '7030A0', 'C00000', '00B0F0', '00B050', 'FF6600'];
    
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <c:date1904 val="0"/>
    <c:lang val="en-US"/>
    <c:roundedCorners val="1"/>
    <c:style val="2"/>
    <c:chart>
        <c:title>
            <c:tx>
                <c:rich>
                    <a:bodyPr rot="0" spcFirstLastPara="1" vertOverflow="ellipsis" vert="horz" wrap="square" anchor="ctr" anchorCtr="1"/>
                    <a:lstStyle/>
                    <a:p>
                        <a:pPr>
                            <a:defRPr sz="2000" b="1" i="0" u="none" strike="noStrike" kern="1200" baseline="0">
                                <a:solidFill><a:srgbClr val="333333"/></a:solidFill>
                                <a:latin typeface="Calibri"/>
                            </a:defRPr>
                        </a:pPr>
                        <a:r>
                            <a:rPr lang="en-US" sz="2000" b="1">
                                <a:solidFill><a:srgbClr val="333333"/></a:solidFill>
                            </a:rPr>
                            <a:t>' . htmlspecialchars($chartTitle) . '</a:t>
                        </a:r>
                    </a:p>
                </c:rich>
            </c:tx>
            <c:layout/>
            <c:overlay val="0"/>
        </c:title>
        <c:autoTitleDeleted val="0"/>
        <c:plotArea>
            <c:layout>
                <c:manualLayout>
                    <c:layoutTarget val="inner"/>
                    <c:xMode val="edge"/>
                    <c:yMode val="edge"/>
                    <c:x val="0.05"/>
                    <c:y val="0.15"/>
                    <c:w val="0.6"/>
                    <c:h val="0.75"/>
                </c:manualLayout>
            </c:layout>
            <c:pieChart>
                <c:varyColors val="1"/>
                <c:ser>
                    <c:idx val="0"/>
                    <c:order val="0"/>
                    <c:tx>
                        <c:v>Data</c:v>
                    </c:tx>
                    <c:explosion val="3"/>';
    
    // Data point colors with explosion effect
    foreach ($values as $idx => $val) {
        $colorIdx = $idx % count($colors);
        $xml .= '
                    <c:dPt>
                        <c:idx val="' . $idx . '"/>
                        <c:bubble3D val="0"/>
                        <c:spPr>
                            <a:solidFill>
                                <a:srgbClr val="' . $colors[$colorIdx] . '"/>
                            </a:solidFill>
                            <a:ln w="19050">
                                <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
                            </a:ln>
                            <a:effectLst>
                                <a:outerShdw blurRad="40000" dist="23000" dir="5400000" rotWithShape="0">
                                    <a:srgbClr val="000000"><a:alpha val="35000"/></a:srgbClr>
                                </a:outerShdw>
                            </a:effectLst>
                        </c:spPr>
                    </c:dPt>';
    }
    
    // Data labels showing category name, value and percentage
    $xml .= '
                    <c:dLbls>
                        <c:spPr>
                            <a:noFill/>
                            <a:ln><a:noFill/></a:ln>
                            <a:effectLst/>
                        </c:spPr>
                        <c:txPr>
                            <a:bodyPr rot="0" spcFirstLastPara="1" vertOverflow="ellipsis" vert="horz" wrap="square" anchor="ctr" anchorCtr="1"/>
                            <a:lstStyle/>
                            <a:p>
                                <a:pPr>
                                    <a:defRPr sz="1100" b="1" i="0" u="none" strike="noStrike" kern="1200" baseline="0">
                                        <a:solidFill><a:srgbClr val="333333"/></a:solidFill>
                                        <a:latin typeface="Calibri"/>
                                    </a:defRPr>
                                </a:pPr>
                                <a:endParaRPr lang="en-US"/>
                            </a:p>
                        </c:txPr>
                        <c:showLegendKey val="0"/>
                        <c:showVal val="1"/>
                        <c:showCatName val="1"/>
                        <c:showSerName val="0"/>
                        <c:showPercent val="1"/>
                        <c:showBubbleSize val="0"/>
                        <c:separator>
</c:separator>
                        <c:showLeaderLines val="1"/>
                        <c:leaderLines>
                            <c:spPr>
                                <a:ln w="9525">
                                    <a:solidFill><a:srgbClr val="666666"/></a:solidFill>
                                </a:ln>
                            </c:spPr>
                        </c:leaderLines>
                    </c:dLbls>
                    <c:cat>
                        <c:strRef>
                            <c:f>Sheet1!$A$1:$A$' . count($labels) . '</c:f>
                            <c:strCache>
                                <c:ptCount val="' . count($labels) . '"/>';
    
    foreach ($labels as $idx => $label) {
        $xml .= '
                                <c:pt idx="' . $idx . '">
                                    <c:v>' . htmlspecialchars($label) . '</c:v>
                                </c:pt>';
    }
    
    $xml .= '
                            </c:strCache>
                        </c:strRef>
                    </c:cat>
                    <c:val>
                        <c:numRef>
                            <c:f>Sheet1!$B$1:$B$' . count($values) . '</c:f>
                            <c:numCache>
                                <c:formatCode>General</c:formatCode>
                                <c:ptCount val="' . count($values) . '"/>';
    
    foreach ($values as $idx => $value) {
        $xml .= '
                                <c:pt idx="' . $idx . '">
                                    <c:v>' . $value . '</c:v>
                                </c:pt>';
    }
    
    $xml .= '
                            </c:numCache>
                        </c:numRef>
                    </c:val>
                </c:ser>
                <c:firstSliceAng val="0"/>
            </c:pieChart>
            <c:spPr>
                <a:noFill/>
                <a:ln><a:noFill/></a:ln>
            </c:spPr>
        </c:plotArea>
        <c:legend>
            <c:legendPos val="r"/>
            <c:layout>
                <c:manualLayout>
                    <c:xMode val="edge"/>
                    <c:yMode val="edge"/>
                    <c:x val="0.7"/>
                    <c:y val="0.25"/>
                    <c:w val="0.28"/>
                    <c:h val="0.5"/>
                </c:manualLayout>
            </c:layout>
            <c:overlay val="0"/>
            <c:spPr>
                <a:noFill/>
                <a:ln><a:noFill/></a:ln>
            </c:spPr>
            <c:txPr>
                <a:bodyPr rot="0" spcFirstLastPara="1" vertOverflow="ellipsis" vert="horz" wrap="square" anchor="ctr" anchorCtr="1"/>
                <a:lstStyle/>
                <a:p>
                    <a:pPr>
                        <a:defRPr sz="1200" b="0" i="0" u="none" strike="noStrike" kern="1200" baseline="0">
                            <a:solidFill><a:srgbClr val="333333"/></a:solidFill>
                            <a:latin typeface="Calibri"/>
                        </a:defRPr>
                    </a:pPr>
                    <a:endParaRPr lang="en-US"/>
                </a:p>
            </c:txPr>
        </c:legend>
        <c:plotVisOnly val="1"/>
        <c:dispBlanksAs val="gap"/>
    </c:chart>
    <c:spPr>
        <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
        <a:ln w="12700" cap="flat" cmpd="sng" algn="ctr">
            <a:solidFill><a:srgbClr val="CCCCCC"/></a:solidFill>
            <a:prstDash val="solid"/>
            <a:round/>
        </a:ln>
        <a:effectLst>
            <a:outerShdw blurRad="50800" dist="38100" dir="2700000" algn="tl" rotWithShape="0">
                <a:srgbClr val="000000"><a:alpha val="23000"/></a:srgbClr>
            </a:outerShdw>
        </a:effectLst>
    </c:spPr>
</c:chartSpace>';
    
    return $xml;
}

/* =====================================================================
   HELPER: Generate XML for a single slide (with optional pie chart support)
   ===================================================================== */
function generateSlideXml($slide, $slideNum, $totalSlides, $hasChart = false, $chartNum = 0) {
    $isTitleSlide = isset($slide['title_slide']) && $slide['title_slide'];
    
    // Colors for different slides
    $colors = ['4472C4', '70AD47', 'ED7D31', '7030A0', 'C00000'];
    $bgColor = $colors[($slideNum - 1) % count($colors)];
    
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">
    <p:cSld>
        <p:bg>
            <p:bgPr>
                <a:solidFill><a:srgbClr val="' . $bgColor . '"/></a:solidFill>
                <a:effectLst/>
            </p:bgPr>
        </p:bg>
        <p:spTree>
            <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
            <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>';
    
    // Title text box
    $titleY = $isTitleSlide ? '2500000' : '300000';
    $titleSize = $isTitleSlide ? '4400' : '3200';
    
    // Adjust title position if chart exists
    if ($hasChart && !$isTitleSlide) {
        $titleY = '200000';
    }
    
    $xml .= '
            <p:sp>
                <p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
                <p:spPr>
                    <a:xfrm><a:off x="500000" y="' . $titleY . '"/><a:ext cx="8144000" cy="800000"/></a:xfrm>
                    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                </p:spPr>
                <p:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p>
                        <a:pPr algn="' . ($isTitleSlide ? 'ctr' : 'l') . '"/>
                        <a:r>
                            <a:rPr lang="en-US" sz="' . $titleSize . '" b="1">
                                <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
                                <a:latin typeface="Calibri Light"/>
                            </a:rPr>
                            <a:t>' . htmlspecialchars($slide['title'] ?? 'Slide') . '</a:t>
                        </a:r>
                    </a:p>
                </p:txBody>
            </p:sp>';
    
    // Subtitle (if exists)
    if (isset($slide['subtitle'])) {
        $subY = $isTitleSlide ? '3400000' : '1000000';
        $xml .= '
            <p:sp>
                <p:nvSpPr><p:cNvPr id="3" name="Subtitle"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
                <p:spPr>
                    <a:xfrm><a:off x="500000" y="' . $subY . '"/><a:ext cx="8144000" cy="500000"/></a:xfrm>
                    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                </p:spPr>
                <p:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p>
                        <a:pPr algn="' . ($isTitleSlide ? 'ctr' : 'l') . '"/>
                        <a:r>
                            <a:rPr lang="en-US" sz="1800">
                                <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
                            </a:rPr>
                            <a:t>' . htmlspecialchars($slide['subtitle']) . '</a:t>
                        </a:r>
                    </a:p>
                </p:txBody>
            </p:sp>';
    }
    
    // Pie Chart (if exists)
    if ($hasChart && $chartNum > 0) {
        // Add white rounded rectangle background for the chart
        $xml .= '
            <p:sp>
                <p:nvSpPr><p:cNvPr id="49" name="ChartBg"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
                <p:spPr>
                    <a:xfrm><a:off x="400000" y="900000"/><a:ext cx="8300000" cy="5600000"/></a:xfrm>
                    <a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 5000"/></a:avLst></a:prstGeom>
                    <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
                    <a:ln w="12700">
                        <a:solidFill><a:srgbClr val="DDDDDD"/></a:solidFill>
                    </a:ln>
                    <a:effectLst>
                        <a:outerShdw blurRad="50800" dist="38100" dir="2700000" algn="tl" rotWithShape="0">
                            <a:srgbClr val="000000"><a:alpha val="20000"/></a:srgbClr>
                        </a:outerShdw>
                    </a:effectLst>
                </p:spPr>
                <p:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p><a:endParaRPr lang="en-US"/></a:p>
                </p:txBody>
            </p:sp>';
        
        // The actual chart
        $xml .= '
            <p:graphicFrame>
                <p:nvGraphicFramePr>
                    <p:cNvPr id="50" name="Chart ' . $chartNum . '"/>
                    <p:cNvGraphicFramePr>
                        <a:graphicFrameLocks noGrp="1"/>
                    </p:cNvGraphicFramePr>
                    <p:nvPr/>
                </p:nvGraphicFramePr>
                <p:xfrm>
                    <a:off x="500000" y="1000000"/>
                    <a:ext cx="8100000" cy="5400000"/>
                </p:xfrm>
                <a:graphic>
                    <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
                        <c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId2"/>
                    </a:graphicData>
                </a:graphic>
            </p:graphicFrame>';
    }
    
    // Statistics (if exists and no chart)
    if (isset($slide['stats']) && !$hasChart) {
        $statX = 500000;
        $statY = $isTitleSlide ? 4200000 : 1600000;
        $statWidth = 1800000;
        $statIdx = 4;
        
        foreach ($slide['stats'] as $label => $value) {
            $xml .= '
            <p:sp>
                <p:nvSpPr><p:cNvPr id="' . $statIdx . '" name="Stat' . $statIdx . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
                <p:spPr>
                    <a:xfrm><a:off x="' . $statX . '" y="' . $statY . '"/><a:ext cx="' . $statWidth . '" cy="1000000"/></a:xfrm>
                    <a:prstGeom prst="roundRect"><a:avLst/></a:prstGeom>
                    <a:solidFill><a:srgbClr val="FFFFFF"><a:alpha val="20000"/></a:srgbClr></a:solidFill>
                </p:spPr>
                <p:txBody>
                    <a:bodyPr anchor="ctr"/>
                    <a:lstStyle/>
                    <a:p>
                        <a:pPr algn="ctr"/>
                        <a:r>
                            <a:rPr lang="en-US" sz="3600" b="1">
                                <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
                            </a:rPr>
                            <a:t>' . htmlspecialchars($value) . '</a:t>
                        </a:r>
                    </a:p>
                    <a:p>
                        <a:pPr algn="ctr"/>
                        <a:r>
                            <a:rPr lang="en-US" sz="1200">
                                <a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>
                            </a:rPr>
                            <a:t>' . htmlspecialchars($label) . '</a:t>
                        </a:r>
                    </a:p>
                </p:txBody>
            </p:sp>';
            $statX += $statWidth + 100000;
            $statIdx++;
            if ($statX > 7000000) {
                $statX = 500000;
                $statY += 1100000;
            }
        }
    }
    
    // Table (if exists)
    if (isset($slide['table'])) {
        $tableY = 1400000;
        $rowCount = count($slide['table']['rows'] ?? []) + 1;
        $colCount = count($slide['table']['headers'] ?? []);
        $tableHeight = min($rowCount * 350000, 4500000);
        $colWidth = intval(8000000 / max($colCount, 1));
        
        $xml .= '
            <p:graphicFrame>
                <p:nvGraphicFramePr><p:cNvPr id="100" name="Table"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr>
                <p:xfrm><a:off x="500000" y="' . $tableY . '"/><a:ext cx="8144000" cy="' . $tableHeight . '"/></p:xfrm>
                <a:graphic>
                    <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table">
                        <a:tbl>
                            <a:tblPr firstRow="1" bandRow="1">
                                <a:tableStyleId>{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}</a:tableStyleId>
                            </a:tblPr>
                            <a:tblGrid>';
        
        for ($i = 0; $i < $colCount; $i++) {
            $xml .= '<a:gridCol w="' . $colWidth . '"/>';
        }
        
        $xml .= '</a:tblGrid>';
        
        // Header row
        if (isset($slide['table']['headers'])) {
            $xml .= '<a:tr h="350000">';
            foreach ($slide['table']['headers'] as $header) {
                $xml .= '
                                <a:tc>
                                    <a:txBody>
                                        <a:bodyPr/>
                                        <a:lstStyle/>
                                        <a:p><a:r><a:rPr lang="en-US" sz="1100" b="1"/><a:t>' . htmlspecialchars($header) . '</a:t></a:r></a:p>
                                    </a:txBody>
                                    <a:tcPr/>
                                </a:tc>';
            }
            $xml .= '</a:tr>';
        }
        
        // Data rows
        if (isset($slide['table']['rows'])) {
            foreach ($slide['table']['rows'] as $row) {
                $xml .= '<a:tr h="300000">';
                foreach ($row as $cell) {
                    $xml .= '
                                <a:tc>
                                    <a:txBody>
                                        <a:bodyPr/>
                                        <a:lstStyle/>
                                        <a:p><a:r><a:rPr lang="en-US" sz="1000"/><a:t>' . htmlspecialchars($cell) . '</a:t></a:r></a:p>
                                    </a:txBody>
                                    <a:tcPr/>
                                </a:tc>';
                }
                $xml .= '</a:tr>';
            }
        }
        
        $xml .= '
                        </a:tbl>
                    </a:graphicData>
                </a:graphic>
            </p:graphicFrame>';
    }
    
    // Slide number
    $xml .= '
            <p:sp>
                <p:nvSpPr><p:cNvPr id="999" name="SlideNum"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
                <p:spPr>
                    <a:xfrm><a:off x="8200000" y="6400000"/><a:ext cx="800000" cy="300000"/></a:xfrm>
                    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                </p:spPr>
                <p:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p>
                        <a:pPr algn="r"/>
                        <a:r>
                            <a:rPr lang="en-US" sz="1000">
                                <a:solidFill><a:srgbClr val="FFFFFF"><a:alpha val="50000"/></a:srgbClr></a:solidFill>
                            </a:rPr>
                            <a:t>' . $slideNum . ' / ' . $totalSlides . '</a:t>
                        </a:r>
                    </a:p>
                </p:txBody>
            </p:sp>';
    
    $xml .= '
        </p:spTree>
    </p:cSld>
    <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sld>';
    
    return $xml;
}


/* =====================================================================
   REPORT 1: ADMIN LOG REPORT
   Description: Shows all admin actions (approve, reject, delete, create)
   ===================================================================== */
if ($report === 'adminlog') {
    
    // Fetch admin log data with date filter
    $sql = "SELECT l.id, u.username AS admin_name, l.action, l.booking_id, l.note, l.ip_address, l.created_at 
            FROM admin_logs l 
            LEFT JOIN users u ON l.admin_id = u.id 
            WHERE DATE(l.created_at) >= ? AND DATE(l.created_at) <= ?
            ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $logs = [];
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();

    // ----- ADMIN LOG: PDF FORMAT -----
    if ($format === 'pdf') {
        $pdf = new ReportPDF();
        $pdf->reportTitle = 'Admin Log Report';
        $pdf->periodLabel = $periodLabel;
        $pdf->dateRange = $dateRangeDisplay;
        $pdf->headers = ['ID', 'Admin', 'Action', 'Booking', 'Details', 'IP Address', 'Date/Time'];
        $pdf->colWidths = [15, 35, 25, 20, 85, 30, 40];
        
        $pdf->SetMargins(10, 10);
        $pdf->AddPage('L'); // Landscape
        $pdf->SetFont('Arial', '', 8);

        foreach ($logs as $log) {
            // Check if new page needed
            if ($pdf->GetY() > 180) {
                $pdf->AddPage('L');
            }
            
            $pdf->Cell(15, 7, $log['id'], 1, 0, 'C');
            $pdf->Cell(35, 7, substr($log['admin_name'] ?? 'N/A', 0, 15), 1, 0, 'L');
            $pdf->Cell(25, 7, ucfirst($log['action']), 1, 0, 'C');
            $pdf->Cell(20, 7, $log['booking_id'], 1, 0, 'C');
            $pdf->Cell(85, 7, substr($log['note'] ?? '', 0, 45), 1, 0, 'L');
            $pdf->Cell(30, 7, $log['ip_address'] ?? '', 1, 0, 'C');
            $pdf->Cell(40, 7, $log['created_at'], 1, 1, 'C');
        }

        // Summary
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 8, "Total Records: " . count($logs), 0, 1);

        $pdf->Output('D', 'Admin_Log_Report_' . $periodFileName . '_' . date('Ymd') . '.pdf');
        exit;
    }
    
    // ----- ADMIN LOG: EXCEL (CSV) FORMAT -----
    if ($format === 'excel') {
        $headers = ['Report Period', $periodLabel, $dateRangeDisplay, '', '', '', ''];
        $data = [];
        $data[] = ['ID', 'Admin', 'Action', 'Booking ID', 'Details', 'IP Address', 'Date/Time'];
        foreach ($logs as $log) {
            $data[] = [
                $log['id'],
                $log['admin_name'] ?? 'N/A',
                ucfirst($log['action']),
                $log['booking_id'],
                $log['note'] ?? '',
                $log['ip_address'] ?? '',
                $log['created_at']
            ];
        }
        $data[] = [];
        $data[] = ['Total Records: ' . count($logs)];
        exportToCSV('Admin_Log_Report_' . $periodFileName . '_' . date('Ymd') . '.csv', $headers, $data);
    }
    
    // ----- ADMIN LOG: POWERPOINT FORMAT -----
    if ($format === 'ppt') {
        $slides = [];
        
        // Count actions by type first (needed for pie chart)
        $actionCounts = [];
        foreach ($logs as $log) {
            $action = ucfirst($log['action']);
            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
        }
        
        // Title slide with period info
        $slides[] = [
            'title' => 'Admin Log Report',
            'subtitle' => $periodLabel . ' | ' . $dateRangeDisplay . "\nGenerated " . date('F j, Y'),
            'title_slide' => true,
            'stats' => ['Total Records' => count($logs)]
        ];
        
        // PIE CHART slide for action types distribution
        if (!empty($actionCounts)) {
            $slides[] = [
                'title' => 'Admin Actions Distribution',
                'pie_chart' => $actionCounts
            ];
        }
        
        // Data slides (10 records per slide)
        $chunks = array_chunk($logs, 10);
        $slideNum = 1;
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $log) {
                $rows[] = [
                    $log['id'],
                    $log['admin_name'] ?? 'N/A',
                    ucfirst($log['action']),
                    $log['booking_id'],
                    substr($log['note'] ?? '', 0, 30),
                    $log['created_at']
                ];
            }
            $slides[] = [
                'title' => 'Admin Actions (Page ' . $slideNum . '/' . count($chunks) . ')',
                'table' => [
                    'headers' => ['ID', 'Admin', 'Action', 'Booking', 'Details', 'Date/Time'],
                    'rows' => $rows
                ]
            ];
            $slideNum++;
        }
        
        // Summary slide
        $slides[] = [
            'title' => 'Summary',
            'stats' => array_merge(['Total Actions' => count($logs)], $actionCounts)
        ];
        
        exportToPPT('Admin_Log_Report_' . $periodFileName . '_' . date('Ymd') . '.ppt', 'Admin Log Report', $slides);
    }
}


/* =====================================================================
   REPORT 2: BOOKING SUMMARY REPORT
   Description: Overview of all bookings with status breakdown
   ===================================================================== */
if ($report === 'booking') {
    
    // Fetch booking data with user and room info - filtered by date
    $sql = "SELECT b.id, b.ticket, u.username, u.Fullname, r.name AS room_name, b.room_id,
                   b.purpose, b.slot_date, b.time_start, b.time_end, b.status, b.created_at
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN rooms r ON b.room_id = r.room_id
            WHERE DATE(b.created_at) >= ? AND DATE(b.created_at) <= ?
            ORDER BY b.slot_date DESC, b.time_start ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $bookings = [];
    while ($row = $res->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
    
    // Calculate statistics
    $stats = ['total' => 0, 'pending' => 0, 'booked' => 0, 'cancelled' => 0, 'rejected' => 0, 'maintenance' => 0];
    foreach ($bookings as $b) {
        $stats['total']++;
        $status = strtolower($b['status']);
        if (isset($stats[$status])) $stats[$status]++;
    }

    // ----- BOOKING SUMMARY: PDF FORMAT -----
    if ($format === 'pdf') {
        $pdf = new ReportPDF();
        $pdf->reportTitle = 'Booking Summary Report';
        $pdf->periodLabel = $periodLabel;
        $pdf->dateRange = $dateRangeDisplay;
        $pdf->headers = ['Ticket', 'User', 'Room', 'Purpose', 'Date', 'Time', 'Status'];
        $pdf->colWidths = [25, 40, 50, 55, 28, 30, 22];
        
        $pdf->SetMargins(10, 10);
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', '', 8);

        foreach ($bookings as $b) {
            if ($pdf->GetY() > 180) {
                $pdf->AddPage('L');
            }
            
            $timeSlot = substr($b['time_start'], 0, 5) . '-' . substr($b['time_end'], 0, 5);
            
            $pdf->Cell(25, 7, $b['ticket'] ?? 'N/A', 1, 0, 'C');
            $pdf->Cell(40, 7, substr($b['Fullname'] ?? $b['username'], 0, 18), 1, 0, 'L');
            $pdf->Cell(50, 7, substr($b['room_name'] ?? $b['room_id'], 0, 25), 1, 0, 'L');
            $pdf->Cell(55, 7, substr($b['purpose'], 0, 28), 1, 0, 'L');
            $pdf->Cell(28, 7, $b['slot_date'], 1, 0, 'C');
            $pdf->Cell(30, 7, $timeSlot, 1, 0, 'C');
            $pdf->Cell(22, 7, ucfirst($b['status']), 1, 1, 'C');
        }

        // Summary Statistics
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, "BOOKING STATISTICS", 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, 7, "Total Bookings: " . $stats['total'], 0, 0);
        $pdf->Cell(50, 7, "Pending: " . $stats['pending'], 0, 0);
        $pdf->Cell(50, 7, "Booked/Approved: " . $stats['booked'], 0, 1);
        $pdf->Cell(50, 7, "Cancelled: " . $stats['cancelled'], 0, 0);
        $pdf->Cell(50, 7, "Rejected: " . $stats['rejected'], 0, 0);
        $pdf->Cell(50, 7, "Maintenance: " . $stats['maintenance'], 0, 1);

        $pdf->Output('D', 'Booking_Summary_Report_' . $periodFileName . '_' . date('Ymd') . '.pdf');
        exit;
    }
    
    // ----- BOOKING SUMMARY: EXCEL (CSV) FORMAT -----
    if ($format === 'excel') {
        $headers = ['Report Period', $periodLabel, $dateRangeDisplay, '', '', '', '', '', '', '', ''];
        $data = [];
        $data[] = ['Ticket', 'Username', 'Full Name', 'Room ID', 'Room Name', 'Purpose', 'Date', 'Time Start', 'Time End', 'Status', 'Created At'];
        foreach ($bookings as $b) {
            $data[] = [
                $b['ticket'] ?? 'N/A',
                $b['username'] ?? '',
                $b['Fullname'] ?? '',
                $b['room_id'],
                $b['room_name'] ?? '',
                $b['purpose'],
                $b['slot_date'],
                $b['time_start'],
                $b['time_end'],
                ucfirst($b['status']),
                $b['created_at']
            ];
        }
        // Add empty row then statistics
        $data[] = [];
        $data[] = ['STATISTICS'];
        $data[] = ['Total Bookings', $stats['total']];
        $data[] = ['Pending', $stats['pending']];
        $data[] = ['Booked/Approved', $stats['booked']];
        $data[] = ['Cancelled', $stats['cancelled']];
        $data[] = ['Rejected', $stats['rejected']];
        $data[] = ['Maintenance', $stats['maintenance']];
        
        exportToCSV('Booking_Summary_Report_' . $periodFileName . '_' . date('Ymd') . '.csv', $headers, $data);
    }
    
    // ----- BOOKING SUMMARY: POWERPOINT FORMAT -----
    if ($format === 'ppt') {
        $slides = [];
        
        // Title slide with overview stats and period info
        $slides[] = [
            'title' => 'Booking Summary Report',
            'subtitle' => $periodLabel . ' | ' . $dateRangeDisplay . "\nGenerated " . date('F j, Y'),
            'title_slide' => true,
            'stats' => [
                'Total Bookings' => $stats['total'],
                'Approved' => $stats['booked'],
                'Pending' => $stats['pending']
            ]
        ];
        
        // PIE CHART slide for booking status distribution
        $pieChartData = [];
        if ($stats['booked'] > 0) $pieChartData['Approved'] = $stats['booked'];
        if ($stats['pending'] > 0) $pieChartData['Pending'] = $stats['pending'];
        if ($stats['cancelled'] > 0) $pieChartData['Cancelled'] = $stats['cancelled'];
        if ($stats['rejected'] > 0) $pieChartData['Rejected'] = $stats['rejected'];
        if ($stats['maintenance'] > 0) $pieChartData['Maintenance'] = $stats['maintenance'];
        
        if (!empty($pieChartData)) {
            $slides[] = [
                'title' => 'Booking Status Distribution',
                'pie_chart' => $pieChartData
            ];
        }
        
        // Statistics slide
        $slides[] = [
            'title' => 'Booking Statistics Overview',
            'stats' => [
                'Total' => $stats['total'],
                'Approved' => $stats['booked'],
                'Pending' => $stats['pending'],
                'Cancelled' => $stats['cancelled'],
                'Rejected' => $stats['rejected'],
                'Maintenance' => $stats['maintenance']
            ]
        ];
        
        // Data slides (8 records per slide)
        $chunks = array_chunk($bookings, 8);
        $slideNum = 1;
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $b) {
                $timeSlot = substr($b['time_start'], 0, 5) . '-' . substr($b['time_end'], 0, 5);
                $rows[] = [
                    $b['ticket'] ?? 'N/A',
                    substr($b['Fullname'] ?? $b['username'], 0, 15),
                    substr($b['room_name'] ?? $b['room_id'], 0, 18),
                    $b['slot_date'],
                    $timeSlot,
                    ucfirst($b['status'])
                ];
            }
            $slides[] = [
                'title' => 'Booking Details (Page ' . $slideNum . '/' . count($chunks) . ')',
                'table' => [
                    'headers' => ['Ticket', 'User', 'Room', 'Date', 'Time', 'Status'],
                    'rows' => $rows
                ]
            ];
            $slideNum++;
        }
        
        exportToPPT('Booking_Summary_Report_' . $periodFileName . '_' . date('Ymd') . '.ppt', 'Booking Summary Report', $slides);
    }
}


/* =====================================================================
   REPORT 3: ROOM USAGE REPORT
   Description: Shows usage statistics per room
   ===================================================================== */
if ($report === 'room') {
    
    // Fetch room usage statistics - filtered by date
    $sql = "SELECT r.room_id, r.name AS room_name, r.capacity,
                   COUNT(b.id) AS total_bookings,
                   SUM(CASE WHEN b.status = 'booked' THEN 1 ELSE 0 END) AS approved_bookings,
                   SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_bookings,
                   SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
                   SUM(CASE WHEN b.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_slots
            FROM rooms r
            LEFT JOIN bookings b ON r.room_id = b.room_id AND DATE(b.created_at) >= ? AND DATE(b.created_at) <= ?
            GROUP BY r.room_id, r.name, r.capacity
            ORDER BY total_bookings DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $rooms = [];
    while ($row = $res->fetch_assoc()) {
        $rooms[] = $row;
    }
    $stmt->close();
    
    // Also get detailed bookings per room within the selected period
    $sql2 = "SELECT r.room_id, r.name AS room_name, b.slot_date, b.time_start, b.time_end, 
                    b.purpose, b.status, u.Fullname
             FROM bookings b
             JOIN rooms r ON b.room_id = r.room_id
             LEFT JOIN users u ON b.user_id = u.id
             WHERE DATE(b.created_at) >= ? AND DATE(b.created_at) <= ?
             ORDER BY r.room_id, b.slot_date DESC";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("ss", $startDate, $endDate);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $recentBookings = [];
    while ($row = $res2->fetch_assoc()) {
        $recentBookings[] = $row;
    }
    $stmt2->close();

    // ----- ROOM USAGE: PDF FORMAT -----
    if ($format === 'pdf') {
        $pdf = new ReportPDF();
        $pdf->reportTitle = 'Room Usage Report';
        $pdf->periodLabel = $periodLabel;
        $pdf->dateRange = $dateRangeDisplay;
        $pdf->headers = ['Room ID', 'Room Name', 'Capacity', 'Total', 'Approved', 'Pending', 'Cancelled', 'Maintenance'];
        $pdf->colWidths = [30, 55, 25, 25, 28, 25, 28, 30];
        
        $pdf->SetMargins(10, 10);
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', '', 9);

        $grandTotal = 0;
        foreach ($rooms as $r) {
            if ($pdf->GetY() > 180) {
                $pdf->AddPage('L');
            }
            
            $grandTotal += $r['total_bookings'];
            
            $pdf->Cell(30, 7, $r['room_id'], 1, 0, 'C');
            $pdf->Cell(55, 7, substr($r['room_name'], 0, 28), 1, 0, 'L');
            $pdf->Cell(25, 7, $r['capacity'] . ' pax', 1, 0, 'C');
            $pdf->Cell(25, 7, $r['total_bookings'], 1, 0, 'C');
            $pdf->Cell(28, 7, $r['approved_bookings'], 1, 0, 'C');
            $pdf->Cell(25, 7, $r['pending_bookings'], 1, 0, 'C');
            $pdf->Cell(28, 7, $r['cancelled_bookings'], 1, 0, 'C');
            $pdf->Cell(30, 7, $r['maintenance_slots'], 1, 1, 'C');
        }

        // Summary
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, "SUMMARY", 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, "Total Rooms: " . count($rooms), 0, 1);
        $pdf->Cell(0, 7, "Total Bookings (All Rooms): " . $grandTotal, 0, 1);
        
        // Most used room
        if (!empty($rooms)) {
            $pdf->Cell(0, 7, "Most Used Room: " . $rooms[0]['room_name'] . " (" . $rooms[0]['total_bookings'] . " bookings)", 0, 1);
        }

        $pdf->Output('D', 'Room_Usage_Report_' . $periodFileName . '_' . date('Ymd') . '.pdf');
        exit;
    }
    
    // ----- ROOM USAGE: EXCEL (CSV) FORMAT -----
    if ($format === 'excel') {
        $headers = ['Report Period', $periodLabel, $dateRangeDisplay, '', '', '', '', ''];
        $data = [];
        $data[] = ['Room ID', 'Room Name', 'Capacity', 'Total Bookings', 'Approved', 'Pending', 'Cancelled', 'Maintenance'];
        foreach ($rooms as $r) {
            $data[] = [
                $r['room_id'],
                $r['room_name'],
                $r['capacity'],
                $r['total_bookings'],
                $r['approved_bookings'],
                $r['pending_bookings'],
                $r['cancelled_bookings'],
                $r['maintenance_slots']
            ];
        }
        
        // Add recent bookings detail
        $data[] = [];
        $data[] = ['BOOKINGS WITHIN PERIOD'];
        $data[] = ['Room ID', 'Room Name', 'Date', 'Time Start', 'Time End', 'Purpose', 'Status', 'Booked By'];
        foreach ($recentBookings as $rb) {
            $data[] = [
                $rb['room_id'],
                $rb['room_name'],
                $rb['slot_date'],
                $rb['time_start'],
                $rb['time_end'],
                $rb['purpose'],
                ucfirst($rb['status']),
                $rb['Fullname'] ?? 'N/A'
            ];
        }
        
        exportToCSV('Room_Usage_Report_' . $periodFileName . '_' . date('Ymd') . '.csv', $headers, $data);
    }
    
    // ----- ROOM USAGE: POWERPOINT FORMAT -----
    if ($format === 'ppt') {
        $slides = [];
        $grandTotal = array_sum(array_column($rooms, 'total_bookings'));
        
        // Title slide with period info
        $slides[] = [
            'title' => 'Room Usage Report',
            'subtitle' => $periodLabel . ' | ' . $dateRangeDisplay . "\nGenerated " . date('F j, Y'),
            'title_slide' => true,
            'stats' => [
                'Total Rooms' => count($rooms),
                'Total Bookings' => $grandTotal
            ]
        ];
        
        // PIE CHART slide for room usage distribution (top rooms)
        $roomUsageData = [];
        $topRoomsForChart = array_slice($rooms, 0, 8); // Top 8 rooms for chart
        foreach ($topRoomsForChart as $r) {
            if ($r['total_bookings'] > 0) {
                $roomName = substr($r['room_name'], 0, 15);
                $roomUsageData[$roomName] = (int)$r['total_bookings'];
            }
        }
        
        if (!empty($roomUsageData)) {
            $slides[] = [
                'title' => 'Room Usage Distribution',
                'pie_chart' => $roomUsageData
            ];
        }
        
        // Room statistics slide
        $rows = [];
        foreach ($rooms as $r) {
            $rows[] = [
                $r['room_id'],
                substr($r['room_name'], 0, 22),
                $r['capacity'] . ' pax',
                $r['total_bookings'],
                $r['approved_bookings'],
                $r['pending_bookings']
            ];
        }
        $slides[] = [
            'title' => 'Room Usage Statistics',
            'table' => [
                'headers' => ['Room ID', 'Room Name', 'Capacity', 'Total', 'Approved', 'Pending'],
                'rows' => $rows
            ]
        ];
        
        // Top 5 most used rooms slide
        $topRooms = array_slice($rooms, 0, 5);
        $topStats = [];
        foreach ($topRooms as $r) {
            $topStats[$r['room_name']] = $r['total_bookings'];
        }
        $slides[] = [
            'title' => 'Top 5 Most Used Rooms',
            'stats' => $topStats
        ];
        
        exportToPPT('Room_Usage_Report_' . $periodFileName . '_' . date('Ymd') . '.ppt', 'Room Usage Report', $slides);
    }
}


/* =====================================================================
   REPORT 4: USER ACTIVITY REPORT
   Description: Shows booking activity per user
   ===================================================================== */
if ($report === 'user') {
    
    // Fetch user activity statistics - filtered by date
    $sql = "SELECT u.id, u.username, u.Fullname, u.Email, u.User_Type, u.Phone_Number,
                   COUNT(b.id) AS total_bookings,
                   SUM(CASE WHEN b.status = 'booked' THEN 1 ELSE 0 END) AS approved_bookings,
                   SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_bookings,
                   SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
                   SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_bookings,
                   MAX(b.created_at) AS last_booking
            FROM users u
            LEFT JOIN bookings b ON u.id = b.user_id AND DATE(b.created_at) >= ? AND DATE(b.created_at) <= ?
            GROUP BY u.id, u.username, u.Fullname, u.Email, u.User_Type, u.Phone_Number
            ORDER BY total_bookings DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    // ----- USER ACTIVITY: PDF FORMAT -----
    if ($format === 'pdf') {
        $pdf = new ReportPDF();
        $pdf->reportTitle = 'User Activity Report';
        $pdf->periodLabel = $periodLabel;
        $pdf->dateRange = $dateRangeDisplay;
        $pdf->headers = ['ID', 'Username', 'Full Name', 'Type', 'Total', 'Approved', 'Pending', 'Cancelled', 'Last Booking'];
        $pdf->colWidths = [12, 30, 50, 22, 20, 25, 22, 25, 40];
        
        $pdf->SetMargins(10, 10);
        $pdf->AddPage('L');
        $pdf->SetFont('Arial', '', 8);

        $totalUsers = 0;
        $activeUsers = 0;
        foreach ($users as $u) {
            if ($pdf->GetY() > 180) {
                $pdf->AddPage('L');
            }
            
            $totalUsers++;
            if ($u['total_bookings'] > 0) $activeUsers++;
            
            $pdf->Cell(12, 7, $u['id'], 1, 0, 'C');
            $pdf->Cell(30, 7, substr($u['username'], 0, 14), 1, 0, 'L');
            $pdf->Cell(50, 7, substr($u['Fullname'], 0, 25), 1, 0, 'L');
            $pdf->Cell(22, 7, $u['User_Type'], 1, 0, 'C');
            $pdf->Cell(20, 7, $u['total_bookings'], 1, 0, 'C');
            $pdf->Cell(25, 7, $u['approved_bookings'], 1, 0, 'C');
            $pdf->Cell(22, 7, $u['pending_bookings'], 1, 0, 'C');
            $pdf->Cell(25, 7, $u['cancelled_bookings'], 1, 0, 'C');
            $pdf->Cell(40, 7, $u['last_booking'] ? substr($u['last_booking'], 0, 16) : 'Never', 1, 1, 'C');
        }

        // Summary
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, "SUMMARY", 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(60, 7, "Total Users: " . $totalUsers, 0, 0);
        $pdf->Cell(60, 7, "Users with Bookings: " . $activeUsers, 0, 0);
        $pdf->Cell(60, 7, "Users without Bookings: " . ($totalUsers - $activeUsers), 0, 1);

        $pdf->Output('D', 'User_Activity_Report_' . $periodFileName . '_' . date('Ymd') . '.pdf');
        exit;
    }
    
    // ----- USER ACTIVITY: EXCEL (CSV) FORMAT -----
    if ($format === 'excel') {
        $headers = ['Report Period', $periodLabel, $dateRangeDisplay, '', '', '', '', '', '', '', '', ''];
        $data = [];
        $data[] = ['User ID', 'Username', 'Full Name', 'Email', 'User Type', 'Phone', 'Total Bookings', 'Approved', 'Pending', 'Cancelled', 'Rejected', 'Last Booking'];
        foreach ($users as $u) {
            $data[] = [
                $u['id'],
                $u['username'],
                $u['Fullname'],
                $u['Email'],
                $u['User_Type'],
                $u['Phone_Number'] ?? '',
                $u['total_bookings'],
                $u['approved_bookings'],
                $u['pending_bookings'],
                $u['cancelled_bookings'],
                $u['rejected_bookings'],
                $u['last_booking'] ?? 'Never'
            ];
        }
        
        exportToCSV('User_Activity_Report_' . $periodFileName . '_' . date('Ymd') . '.csv', $headers, $data);
    }
    
    // ----- USER ACTIVITY: POWERPOINT FORMAT -----
    if ($format === 'ppt') {
        $slides = [];
        $totalUsers = count($users);
        $activeUsers = count(array_filter($users, fn($u) => $u['total_bookings'] > 0));
        
        // Title slide with period info
        $slides[] = [
            'title' => 'User Activity Report',
            'subtitle' => $periodLabel . ' | ' . $dateRangeDisplay . "\nGenerated " . date('F j, Y'),
            'title_slide' => true,
            'stats' => [
                'Total Users' => $totalUsers,
                'Active Users' => $activeUsers,
                'Inactive Users' => $totalUsers - $activeUsers
            ]
        ];
        
        // User types breakdown
        $userTypes = [];
        foreach ($users as $u) {
            $type = $u['User_Type'] ?? 'Unknown';
            $userTypes[$type] = ($userTypes[$type] ?? 0) + 1;
        }
        
        // PIE CHART slide for user types distribution
        if (!empty($userTypes)) {
            $slides[] = [
                'title' => 'Users by Type Distribution',
                'pie_chart' => $userTypes
            ];
        }
        
        // User activity pie chart (active vs inactive)
        $activityData = [];
        if ($activeUsers > 0) $activityData['Active Users'] = $activeUsers;
        if (($totalUsers - $activeUsers) > 0) $activityData['Inactive Users'] = $totalUsers - $activeUsers;
        
        if (!empty($activityData)) {
            $slides[] = [
                'title' => 'User Activity Status',
                'pie_chart' => $activityData
            ];
        }
        
        // Data slides (8 records per slide)
        $chunks = array_chunk($users, 8);
        $slideNum = 1;
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $u) {
                $rows[] = [
                    $u['id'],
                    substr($u['username'], 0, 12),
                    substr($u['Fullname'], 0, 18),
                    $u['User_Type'],
                    $u['total_bookings'],
                    $u['approved_bookings']
                ];
            }
            $slides[] = [
                'title' => 'User Details (Page ' . $slideNum . '/' . count($chunks) . ')',
                'table' => [
                    'headers' => ['ID', 'Username', 'Full Name', 'Type', 'Total', 'Approved'],
                    'rows' => $rows
                ]
            ];
            $slideNum++;
        }
        
        // Top users slide
        $topUsers = array_slice($users, 0, 5);
        $topStats = [];
        foreach ($topUsers as $u) {
            $name = substr($u['Fullname'] ?: $u['username'], 0, 15);
            $topStats[$name] = $u['total_bookings'];
        }
        $slides[] = [
            'title' => 'Top 5 Most Active Users',
            'stats' => $topStats
        ];
        
        exportToPPT('User_Activity_Report_' . $periodFileName . '_' . date('Ymd') . '.ppt', 'User Activity Report', $slides);
    }
}

// If we reach here, something went wrong
exit("Report generation failed. Please try again.");
?>
