<?php
if(!empty($_POST['name']) and !empty($_POST['offer']) and !empty($_POST['email']) and !empty($_POST['message'])){
    $name = trim(strip_tags($_POST['name']));
    $offer = trim(strip_tags($_POST['offer']));
    $email = trim(strip_tags($_POST['email']));
    $message = trim(strip_tags($_POST['message']));

	$to      = 'rainstr7@gmail.com';

	$headers = 'From: '. $email . "\r\n" .
    'Reply-To: '. $email . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

    $status=mail($to, $offer, $message, $headers);
    if ($status == TRUE){
        header('Location:/contactTrue.html');
    } else{
        header('Location:/contactError.html');    
    }
}

?>
