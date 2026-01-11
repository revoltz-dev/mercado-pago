<?php

class MercadoPago
{
    private $access_token;
    private $notification_url;

    public function __construct($access_token, $notification_url)
    {
        $this->access_token = $access_token;
        $this->notification_url = $notification_url;
    }

    /**
     * Gera uma chave de idempotência única (UUID v4)
     * Obrigatório desde Janeiro/2024
     *
     * @return string UUID único
     */
    private function generate_idempotency_key()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Gera um pagamento (PIX ou boleto) via API do Mercado Pago.
     *
     * @param array $order_data Dados do pedido (amount, description, payer_email, etc.).
     * @param string $payment_method Método de pagamento ('pix' ou 'bolbradesco' para boleto).
     * @return array Retorna os dados do pagamento ou uma mensagem de erro.
     */
    public function generatePayment($order_data, $payment_method = 'pix')
    {
        $api = "https://api.mercadopago.com/v1/payments";
        $data = [
            "external_reference" => $order_data['ref'],
            "transaction_amount" => $order_data['amount'],
            "description" => $order_data['description'],
            "notification_url" => $this->notification_url,
            "payment_method_id" => $payment_method,
            "payer" => [
                "email" => $order_data['payer_email'],
                "first_name" => $order_data['payer_first_name'] ?? '',
                "last_name" => $order_data['payer_last_name'] ?? '',
                "identification" => [
                    "type" => "CPF",
                    "number" => $order_data['payer_cpf']
                ]
            ]
        ];

        $ch = curl_init($api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->access_token}",
            "X-Idempotency-Key: " . $this->generate_idempotency_key()
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return ["error" => curl_error($ch)];
        }

        curl_close($ch);

        $payment_info = json_decode($response, true);

        if (isset($payment_info['error'])) {
            return ["error" => $payment_info['error']];
        }

        if (isset($payment_info['message'])) {
            return ["error" => $payment_info['message']];
        }

        if ($payment_method === 'pix') {
            return [
                'payment_id' => $payment_info['id'] ?? null,
                'qr_code' => $payment_info['point_of_interaction']['transaction_data']['qr_code'],
                'qr_code_base64' => $payment_info['point_of_interaction']['transaction_data']['qr_code_base64'],
                'payment_url' => $payment_info['point_of_interaction']['transaction_data']['ticket_url']
            ];
        } elseif ($payment_method === 'bolbradesco') {
            return [
                'payment_id' => $payment_info['id'] ?? null,
                'boleto_url' => $payment_info['transaction_details']['external_resource_url'],
                'boleto_barcode' => $payment_info['transaction_details']['barcode']['content']
            ];
        }

        return ["error" => "Método de pagamento não suportado"];
    }

    /**
     * Recupera informações sobre um pagamento
     *
     * @param string $payment_id ID do pagamento.
     * @return array Retorna os detalhes do pagamento ou uma mensagem de erro.
     */
    public function getPayment($payment_id)
    {
        $url = "https://api.mercadopago.com/v1/payments/{$payment_id}";
        $headers = [
            "Authorization: Bearer {$this->access_token}"
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_message = curl_error($ch);
            curl_close($ch);
            return ["error" => "Erro na requisição cURL: {$error_message}"];
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 200) {
            $payment_info = json_decode($response, true);
            return $payment_info;
        } else {
            return ["error" => "Erro na API: HTTP {$http_code}", "response" => $response];
        }
    }
}
