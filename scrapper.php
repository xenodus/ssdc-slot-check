<?php
//die();
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

$all_results = [];

foreach( START_DATES as $date ) {
	
	$results = [];
	
	if( $date == 'TODAY' )
		$date = date('d M Y');
	else
		$date = date_format(date_create_from_format('dmy', $date), 'd M Y');
	
	doCheck( $date, $results );
	// Checking week after
	$week_after_date = date('d M Y', strtotime("$date +7 day"));
	doCheck( $week_after_date, $results );
	
	$all_results = array_merge($all_results, $results);
}

// Process results
if( $all_results ) {
	sendNotification($all_results);
	slack($all_results);
}

// Output results
header('Content-Type: application/json');
echo json_encode($all_results, JSON_PRETTY_PRINT);

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

/**
 * Send a Message to a Slack Channel.
 *
 * In order to get the API Token visit: https://api.slack.com/custom-integrations/legacy-tokens
 * The token will look something like this `xoxo-2100000415-0000000000-0000000000-ab1ab1`.
 * 
 * @param string $message The message to post into a channel.
 * @param string $channel The name of the channel prefixed with #, example #foobar
 * @return boolean
 */
function slack($results)
{
	$message = "\n*OPEN PRACTICAL SLOTS AT SSDC*\n\n";
	foreach( $results as $date => $slots ) {
		$m = "*".$date."* >>> ";
		$m2 = implode(", ", $slots);
		$m .= $m2."\n";
		$message .= $m;
	}
	
	$attachments = [
		[
			  "fallback" => "Book your class at https://www.ssdcl.com.sg/Student/Booking/AddBooking?bookingType=PL",
			  "actions" => [
				[
				  "type" => "button",
				  "text" => "Book lessons :car:",
				  "url" => "https://www.ssdcl.com.sg/Student/Booking/AddBooking?bookingType=PL"
				]
			  ]
		]
	];	
	
	$ch = curl_init("https://slack.com/api/chat.postMessage");
	$data = http_build_query([
		"token" => SLACK_TOKEN,
		"channel" => SLACK_CHANNEL, //"#mychannel",
		"text" => $message, //"Hello, Foo-Bar channel message.",
		"username" => SLACK_USERNAME,
		"attachments" => json_encode($attachments)
	]);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$result = curl_exec($ch);
	curl_close($ch);

	return $result;
}