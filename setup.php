#!/usr/bin/env php
<?php

//if (file_exists("./.env")) {
//    die(".env file exists.  Delete this file before running setup.php");
//}

$a = '0123456789abcdef';
$secret = '';
for ($i = 0; $i < 32; $i++) {
    $secret .= $a[rand(0, 15)];
}

$dbuser=prompt("Database User");
$dbpass=prompt("Database Password", null, true);
$dbhost=prompt("Database Host", "localhost");
$dbport=prompt("Database Port", "3306");
$dbbase=prompt('Database Name', 'clickthulu');
echo "\n";
$smtptype=prompt("SMTP Service", "gmail");
$smtpuser=urlencode(prompt("SMTP User"));
$smtppass=urlencode(prompt("SMTP Password", null, true));
echo "\n";
$emailfrom=prompt("Email address");
$emailname=prompt("From name");
echo "\n";
echo "Writing .env file";

$out = "# This is an auto-generated file.  
DATABASE_URL=\"mysql://{$dbuser}:{$dbpass}@{$dbhost}:{$dbport}/{$dbbase}?serverVersion=10.11.2-MariaDB&charset=utf8mb4\"

APP_ENV=prod
APP_SECRET={$secret}

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

MAILER_DSN={$smtptype}+smtp://{$smtpuser}:{$smtppass}@default
MAILER_FROMADDR={$emailfrom}
MAILERFROMNAME={$emailname}
";

file_put_contents("./ENV.TEST", $out);



@exec("composer install", $return, $retval);

if ($retval !==0 ) {
    die("Composer did not run properly.  Please run composer install");
}

echo "Installing database";

@exec("./bin/console doctrine:migrations:migrate", $return, $retval);

if ($retval !==0 ) {
    die("Database did not install correctly.");
}

echo "Creating initial user account.";



function prompt($message = 'prompt: ', $default = null, $hidden = false) {
    if (PHP_SAPI !== 'cli') {
        return false;
    }
    $display = $message;
    if (!empty($default)) {
        $display .= " [{$default}]";
    }
    $display .= ": ";
    echo $display;
    $ret =
        $hidden
            ? exec(
            PHP_OS === 'WINNT' || PHP_OS === 'WIN32'
                ? __DIR__ . '\prompt_win.bat'
                : 'read -s PW; echo $PW'
        )
            : rtrim(fgets(STDIN), PHP_EOL)
    ;
    if ($hidden) {
        echo PHP_EOL;
    }
    if (empty($ret) && !empty($default)) {
        $ret = $default;
    }
    return $ret;
}