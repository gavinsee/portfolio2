<?php

//To include before calling file.

$to="krishna.seegoolam13@imperial.ac.uk";
$reply="krishna.seegoolam13@imperial.ac.uk";
$subject = "Welcome to Website";
$message ="Hi,

Your Welcome Message.


Thanks
www.website.com
";

//OK. Now file.


	require("../config/settings.php");
	require("smtp.php");
	require("sasl.php");

	$from=$eHallsWardenemail;                           /* Change this to your address like "me@mydomain.com"; */ 
	if($reply == ''){
        $reply = $from;
        }


	$sender_line=__LINE__;
	
	if(strlen($from)==0)
		die("Please set the messages sender address in line ".$sender_line." of the script ".basename(__FILE__)."\n");
	if(strlen($to)==0)
		die("Please set the messages recipient address in line ".$recipient_line." of the script ".basename(__FILE__)."\n");

	$smtp=new smtp_class;

	$smtp->host_name=$eHallsEmailServerOUT; //IP address       /* Change this variable to the address of the SMTP server to relay, like "smtp.myisp.com" */
	$smtp->host_port=$eHallsEmailServerOUTPort;                /* Change this variable to the port of the SMTP server to use, like 465 */
	$smtp->ssl=0;                       /* Change this variable if the SMTP server requires an secure connection using SSL */
	$smtp->start_tls=1;                 /* Change this variable if the SMTP server requires security by starting TLS during the connection */
	$smtp->localhost="localhost";       /* Your computer address */
	$smtp->direct_delivery=0;           /* Set to 1 to deliver directly to the recepient SMTP server */
	$smtp->timeout=2;                  /* Set to the number of seconds wait for a successful connection to the SMTP server */
	$smtp->data_timeout=4;              /* Set to the number seconds wait for sending or retrieving data from the SMTP server.
	                                       Set to 0 to use the same defined in the timeout variable */
	$smtp->debug=1;                     /* Set to 1 to output the communication with the SMTP server */
	$smtp->html_debug=1;                /* Set to 1 to format the debug output as HTML */
	$smtp->pop3_auth_host="";           /* Set to the POP3 authentication host if your SMTP server requires prior POP3 authentication */
	$smtp->user=$eHallsEmailUser;                     /* Set to the user name if the server requires authetication */
	$smtp->realm="";                    /* Set to the authetication realm, usually the authentication user e-mail domain */
	$smtp->password=$eHallsEmailPass;                 /* Set to the authetication password */
	$smtp->workstation="";              /* Workstation name for NTLM authentication */
	$smtp->authentication_mechanism=""; /* Specify a SASL authentication method like LOGIN, PLAIN, CRAM-MD5, NTLM, etc..
	                                       Leave it empty to make the class negotiate if necessary */



	if($smtp->SendMessage(
		$from,
		array(
			$to
		),
		array(
			"From: $from",
			"To: $to",
			"Reply-To: $reply",
			"Subject: $subject",
			"Date: ".strftime("%a, %d %b %Y %H:%M:%S %Z")
		),
		"$message"))
		{
		echo "Message sent to $to OK.\n"; 
		}
	else
		echo "Cound not send the message to $to.\nError: ".$smtp->error."\n";





?>
