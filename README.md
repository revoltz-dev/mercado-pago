# Mercado Pago Integration (PIX & Boleto) — No SDK

A lightweight PHP integration with the Mercado Pago API to generate payments via PIX and Brazilian bank slips (Boleto), plus payment status checks — without using the official SDK.

### Requirements

- PHP 7.4 or later.
- A Mercado Pago access token (`access_token`). (https://www.youtube.com/watch?v=gwqPNEW0xVY)
- A notification URL to receive payment status updates.

### How to use

Create an instance of the `MercadoPago` class with your access token and notification URL.

 ```php
   $access_token = 'YOUR_ACCESS_TOKEN';
   $notification_url = 'http://yoursite.com/notification.php';
   $mp = new MercadoPago($access_token, $notification_url);
```

Set the order details:

 ```php
$order_data = [
    'ref' => 12345,                       // Unique order reference
    'amount' => 50.00,                    // Payment amount
    'description' => 'Test Payment',      // Payment description
    'payer_email' => 'mat.mcd7@gmail.com',// Payer e-mail
    'payer_first_name' => 'Mateus',       // Payer first name (optional)
    'payer_last_name' => 'Junior',        // Payer last name (optional)
    'payer_cpf' => '12345678909'          // Payer CPF (required for Boleto)
];
```

Generate a PIX payment:

 ```php
$result_pix = $mp->generatePayment($order_data, 'pix');

if (isset($result_pix['error'])) {
    echo "Error generating PIX payment: " . $result_pix['error'];
} else {
    echo "QR Code (base64): <img src='data:image/jpeg;base64, " . $result_pix['qr_code_base64'] . "' width='200' />";
    echo "<br>Or copy the PIX code: " . $result_pix['qr_code'];
    echo "<br><a href='" . $result_pix['payment_url'] . "'>Click here to pay</a>";
}
```

Generate a Boleto:

 ```php
$result_boleto = $mp->generatePayment($order_data, 'bolbradesco');

if (isset($result_boleto['error'])) {
    echo "Error generating Boleto: " . $result_boleto['error'];
} else {
    echo "Boleto generated successfully!";
    echo "<br><a href='" . $result_boleto['boleto_url'] . "'>Click here to view the Boleto</a>";
    echo "<br>Barcode: " . $result_boleto['boleto_barcode'];
}
```

Check payment status:

 ```php
$payment_id = '123456789'; // Replace with the payment ID received in the notification POST
$payment_info = $mp->getPayment($payment_id);

if (isset($payment_info['error'])) {
    echo "Error checking payment: " . $payment_info['error'];
} else {
    // Run your order fulfillment logic here
    $status = $payment_info['status'];
    $ref = $payment_info['external_reference'];

    if ($status === 'approved') {
        echo "Payment approved.";
    }
}
```
