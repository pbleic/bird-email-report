<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/.phpenv");
$dotenv->load();

set_include_path(
  get_include_path() . PATH_SEPARATOR . __DIR__ . '/vendor/' . PATH_SEPARATOR . dirname(__DIR__) . "/data"
);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$file_name = exec("Rscript ../r/api_analysis.R");

$csvFile = '../data/new_species.csv';
$data = array_map('str_getcsv', file($csvFile));
array_shift($data); // Remove header row

$weekNumber = date('W');
$chartPath = '../images/species_chart.png';

$html = '<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; max-width: 900px; margin: 0 auto; }
    .title { font-size: 20px; font-weight: bold; margin-bottom: 15px; }
    .chart-container { margin-bottom: 20px; }
    .species-grid { width: 100%; border-collapse: separate; border-spacing: 20px 40px; }
    .species-cell { width: 33.33%; padding: 10px; vertical-align: top; text-align: center; }
    .species-image { width: 250px; height: 250px; object-fit: cover; border-radius: 5px; display: block; margin: 0 auto 15px; }
    .species-info { width: 250px; margin: 0 auto; text-align: center; }
    .species-name { font-size: 16px; font-weight: bold; margin-top: 10px; }
    .scientific-name { font-style: italic; font-size: 14px; color: #555; margin-top: 5px; }
    .count { font-size: 14px; color: #333; margin-top: 5px; }
  </style>
</head>
<body>
  <div class="chart-container">
    <img src="cid:species_chart" alt="Species Counts Chart" style="max-width: 800px; width: 100%;">
  </div>
  
  <div class="title">New Species Seen This Week</div>

  <table class="species-grid">';

$totalItems = count($data);
$itemsPerRow = 3;
$rows = ceil($totalItems / $itemsPerRow);

for ($i = 0; $i < $rows; $i++) {
    $html .= '<tr>';
    for ($j = 0; $j < $itemsPerRow; $j++) {
        $index = ($i * $itemsPerRow) + $j;
        if ($index < $totalItems) {
            $row = $data[$index];
            list($species_id, $common_name, $scientific_name, $counts, $image_url) = $row;
            $html .= '<td class="species-cell">
                        <img src="' . htmlspecialchars($image_url) . '" 
                             alt="' . htmlspecialchars($common_name) . '" 
                             class="species-image">
                        <div class="species-info">
                            <div class="species-name">' . htmlspecialchars($common_name) . '</div>
                            <div class="scientific-name">' . htmlspecialchars($scientific_name) . '</div>
                            <div class="count">Counts = ' . intval($counts) . '</div>
                        </div>
                     </td>';
        } else {
            $html .= '<td class="species-cell"></td>';
        }
    }
    $html .= '</tr>';
}

$html .= '</table></body></html>';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.example.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your_email@example.com';
    $mail->Password   = $_ENV['EMAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('your_email@example.com', 'Your Name');
    $mail->addAddress('recipient@example.com', 'Recipient Name');

    $bccRecipients = [
        'bcc1@example.com',
        'bcc2@example.com',
        'bcc3@example.com'
    ];
    
    foreach ($bccRecipients as $bccEmail) {
        $mail->addBCC($bccEmail);
    }

    $mail->Subject = 'Bird Sightings Week ' . $weekNumber;  
    $mail->isHTML(true);
    
    $mail->addEmbeddedImage($chartPath, 'species_chart');
    $mail->Body = $html;

    $mail->send();
    echo "Email sent successfully!\n";
} catch (Exception $e) {
    echo "Email could not be sent. Error: {$mail->ErrorInfo}\n";
}
?>
