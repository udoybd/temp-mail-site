<?php
return [
    'date' => '2026-10-12',
    'title' => 'Temp mail for Facebook',
    'description' => 'Get the Facebook confirmation code on a disposable inbox for test or page-admin accounts.',
    'body' => <<<'HTML'
<p>Facebook asks you to confirm your email with a code. A temporary inbox receives it in seconds.</p>
<ol><li>Copy an address from the <a href="/#inbox">inbox</a>.</li><li>Create the Facebook account with that email.</li><li>Copy the code shown next to the Facebook email and enter it.</li></ol>
<p>Facebook may ask for a phone number or extra checks for new accounts. That is outside what an email service can help with.</p>
HTML,
];
