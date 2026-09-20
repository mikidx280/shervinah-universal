<?php
// Save as private-mail-config.php one directory ABOVE public_html. Never commit filled values.
// Create a sending mailbox and configure SPF/DKIM through its provider first.
return [
    'enabled'=>false,
    'host'=>'smtp.hostinger.com',
    'port'=>465,
    'encryption'=>'smtps', // tls (STARTTLS) or smtps (usually port 465)
    'username'=>'orders@shervinahuniversal.com',
    'password'=>'',
    'from'=>'orders@shervinahuniversal.com', // The authenticated, verified sending mailbox.
];
