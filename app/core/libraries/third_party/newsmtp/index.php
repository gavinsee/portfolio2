<?php
$to="krishna.seegoolam13@imperial.ac.uk";
$fn="Fisrt Name";
$ln="Last Name";
$name=$fn.' '.$ln;
$from="krishna.seegoolam13@imperial.ac.uk";
$subject = "Welcome to Website";
$message = "Dear $name, 


Your Welcome Message.


Thanks
www.website.com
";
include('smtpwork.php');

?>
