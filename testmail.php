<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'testssdtest/PHPMailer/src/Exception.php';
require 'testssdtest/PHPMailer/src/PHPMailer.php';
require 'testssdtest/PHPMailer/src/SMTP.php';

$mail = new PHPMailer;
$mail->isSMTP(); 
$mail->SMTPDebug = 2; // 0 = off (for production use) - 1 = client messages - 2 = client and server messages
$mail->Host = "smtp.sendgrid.net"; // use $mail->Host = gethostbyname('smtp.gmail.com'); // if your network does not support SMTP over IPv6
$mail->Port = 465; // TLS only
$mail->SMTPSecure = 'ssl'; // ssl is depracated
$mail->SMTPAuth = true;
$mail->Username = 'apikey';
$mail->Password = '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';
$mail->setFrom('jim@bjj.coach', 'jim');
$mail->addAddress('partha974811@gmail.com', 'Partha');
$mail->Subject = 'PHPMailer GMail SMTP test';
$mail->msgHTML("test body"); //$mail->msgHTML(file_get_contents('contents.html'), __DIR__); //Read an HTML message body from an external file, convert referenced images to embedded,
$mail->AltBody = 'HTML messaging not supported';
// $mail->addAttachment('images/phpmailer_mini.png'); //Attach an image file

if(!$mail->send()){
    echo "Mailer Error: " . $mail->ErrorInfo;
}else{
    echo "Message sent!";
}

?>
