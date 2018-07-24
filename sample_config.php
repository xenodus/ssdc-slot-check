<?php
/**********************************
DOM Crawler Configs
***********************************/
define("TARGET_BASE_URL", "https://www.ssdcl.com.sg/");
define("TARGET_BOOKING_URL", "https://www.ssdcl.com.sg/Student/Booking/AddBooking?bookingType=PL");
define("USER_ID", "");
define("USER_PWD", "");
define("START_DATES", ["TODAY", "140818"]); // TODAY by default. Checks two weeks (inclusive) for each date specified.
/**********************************/

/**********************************
Mailer Configs
***********************************/
define("MAILER_HOST", "");
define("MAILER_USERNAME", "");
define("MAILER_PWD", "");
define("MAILER_PORT", "465");
define("MAILER_ENCRYPTION", "ssl");

define("MAILER_FROM", "");
define("MAILER_TO", "");
date_default_timezone_set('Asia/Singapore');
/**********************************/

/**********************************
Slack Configs
***********************************/
define("SLACK_CHANNEL", "#ssdc-alerts");
define("SLACK_TOKEN", "");
define("SLACK_USERNAME", "");
/**********************************/