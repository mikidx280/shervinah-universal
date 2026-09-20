<?php
// Save a filled copy as private-payment-config.php ONE directory above public_html.
// Never place credentials in GitHub or public_html. PHP 8.1+ with cURL required.
return [
    'enabled' => false,
    'terminal_number' => 0,
    'api_name' => '',
    // ApiPassword is not required by Cardcom LowProfile Create/GetLpResult.
    // Add ISO codes here immediately when Israel Post suspends a destination.
    'suspended_countries' => [],
];
