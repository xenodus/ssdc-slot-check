<?php
/**********************************
PHP Scrapper to automate checking of Singapore Safety Driving Centre's website for available practical lesson slots within the next two weeks that are cancelled or sold by others (try sell).
-----------------------------------
Author - Alvin Yeoh
Contact - contact@alvinyeoh.com
***********************************/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Goutte\Client;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

$results = [];

// Checking current or defined week
$date = defined('START_DATE') ? date_format(date_create_from_format('dmy', START_DATE), 'd M Y') : date('d M Y');
doCheck( $date, $results );
// Checking week after
$week_after_date = date('d M Y', strtotime("$date +7 day"));
doCheck( $week_after_date, $results );

// Process result
if( $results ) {
	sendNotification($results);
}

// Output results
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);

function doCheck($date, &$results) {
	$client = new Client();
	$crawler = $client->request('GET', TARGET_BASE_URL);

	// Get login form
	$form = $crawler->selectButton('Login')->form();
	// Do login request
	$crawler = $client->submit($form, array('UserName' => USER_ID, 'Password' => USER_PWD));
	// Navigate to booking page
	$crawler = $client->request('GET', TARGET_BOOKING_URL);
	// Click on "check for availability" button
	$form = $crawler->selectButton('checkEligibility')->form();
	// Submit date to check availability
	$crawler = $client->submit($form, array('SelectedDate' => $date));
	// Process DOM
	// Anchor with class slotBooking == open slot
	$crawler->filter('a[class="slotBooking"]')->each(function ($node) use (&$results) {
		// date is on second element of table row and in "d M Y l" format e.g. 12 Aug 2018 Sunday
		$date = trim( $node->parents()->siblings()->eq(1)->text() );
		$date = preg_replace('/\s+/', ' ', $date);
		// time format e.g. 8:15 AM
		$time = trim( $node->parents()->first()->text() );
		// Results
		$results[$date][] = $time;
	});

	return $results;
}

function sendNotification($results) {

	$mail = new PHPMailer(true);								// Passing `true` enables exceptions
	try {
	    //Server settings
	    //$mail->SMTPDebug = 2;										// Enable verbose debug output
	    $mail->isSMTP();												// Set mailer to use SMTP
	    $mail->Host = MAILER_HOST;							// Specify main and backup SMTP servers
	    $mail->SMTPAuth = true;									// Enable SMTP authentication
	    $mail->Username = MAILER_USERNAME;			// SMTP username
	    $mail->Password = MAILER_PWD;						// SMTP password
	    $mail->SMTPSecure = MAILER_ENCRYPTION;	// Enable TLS encryption, `ssl` also accepted
	    $mail->Port = MAILER_PORT;							// TCP port to connect to

	    //Recipients
	    $mail->setFrom(MAILER_FROM);
	    $mail->addAddress(MAILER_TO);
	    $mail->addReplyTo(MAILER_FROM);

	    //Content
	    $mail->isHTML(true);                                  // Set email format to HTML
	    $mail->Subject = 'Available Practical Lesson Slots At SSDC: '.date('d M Y g:i A', time());

	    $message = '
	    <p>Hello,</p>
	    <p>These are the available practical slots discovered as of '.date('d M Y g:i A', time()).'.</p>
	    <p>
	    	<table style="border: 1px solid #000; border-spacing: 0;">
	    		<tr style="background: #000; color: #fff;">
	    			<th style="text-align: left; padding: 5px;">Date</th>
	    			<th style="text-align: right; padding: 5px;">Slots</th>
	    		</tr>
	    ';

	    $i = 0;
	    foreach( $results as $date => $slots ) {
	    	$message .= '
	    	<tr style="background: #'.($i%2>0?'dddddd':'ffffff').';">
	    		<td style="text-align: left; padding: 5px;">'.$date.'</td>
	    		<td style="text-align: right; padding: 5px;">';

    		foreach($slots as $slot) {
    			$message .= $slot.'<br/>';
    		}

    		$message .= '
    			</td>
    		</tr>';
    		$i++;
	    }

	    $message .='
	    	</table>
	    <p>
	    <p>Cheers!</p>
	    ';

	    $mail->Body = $message;
	    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

	    $mail->send();
	} catch (Exception $e) {
	    // echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
	}
}