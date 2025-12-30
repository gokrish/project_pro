<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo '<pre>';
echo "SESSION ID: " . session_id() . PHP_EOL;
echo "SESSION FILE: " . session_save_path() . PHP_EOL;
echo "REQUEST URI: " . $_SERVER['REQUEST_URI'] . PHP_EOL;
echo "SCRIPT: " . $_SERVER['SCRIPT_FILENAME'] . PHP_EOL;
echo "SESSION DATA:\n";
print_r($_SESSION);
echo "\nHEADERS SENT: ";
var_dump(headers_sent($file, $line), $file, $line);
echo '</pre>';
exit;
