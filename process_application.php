<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Stripe\Stripe;

// Configure AWS
$s3Client = new S3Client([
    'region'  => 'your-region', // e.g., 'us-west-2'
    'version' => 'latest',
    'credentials' => [
        'key'    => 'your-access-key',
        'secret' => 'your-secret-key',
    ],
]);

$sesClient = new SesClient([
    'region'  => 'your-region', // e.g., 'us-west-2'
    'version' => 'latest',
    'credentials' => [
        'key'    => 'your-access-key',
        'secret' => 'your-secret-key',
    ],
]);

// Configure Stripe
Stripe::setApiKey('secret-key'); // Replace with your Stripe secret key

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);
    $paymentMethodId = $input['paymentMethodId'];
    $totalFee = $input['totalFee'];

    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $totalFee * 100,
            'currency' => 'usd',
            'payment_method' => $paymentMethodId,
            'confirmation_method' => 'manual',
            'confirm' => true,
        ]);

        if ($paymentIntent->status == 'requires_action' || $paymentIntent->status == 'requires_source_action') {
            echo json_encode([
                'requiresAction' => true,
                'paymentIntentId' => $paymentIntent->id,
                'clientSecret' => $paymentIntent->client_secret,
            ]);
        } else if ($paymentIntent->status == 'succeeded') {
            // Gather application data
            $applicationData = [
                'full_names' => $input['full_name'],
                'emails' => $input['email'],
                'phones' => $input['phone'],
                'dobs' => $input['dob'],
                'ssn_itins' => $input['ssn_itin'],
                'drivers_licenses' => $input['drivers_license'],
                'current_addresses' => $input['current_address'],
                'current_rents' => $input['current_rent'],
                'current_landlords' => $input['current_landlord'],
                'landlord_phones' => $input['landlord_phone'],
                'reasons_for_leaving' => $input['reason_for_leaving'],
                'employeds' => $input['employed'],
                'employers' => $input['employer'],
                'employer_addresses' => $input['employer_address'],
                'positions' => $input['position'],
                'salaries' => $input['salary'],
                'employment_durations' => $input['employment_duration'],
                'supervisor_names' => $input['supervisor_name'],
                'supervisor_phones' => $input['supervisor_phone'],
                'eviction_histories' => $input['eviction_history'],
                'bankruptcy_histories' => $input['bankruptcy_history'],
                'criminal_histories' => $input['criminal_history'],
                'additional_infos' => $input['additional_info'],
                'signatures' => $input['signature'],
                'application_dates' => $input['application_date']
            ];

            // Convert the application data to JSON format
            $applicationJson = json_encode($applicationData);

            // Save the application data to S3
            $result = $s3Client->putObject([
                'Bucket' => 'your-s3-bucket-name', // Replace with your bucket name
                'Key' => 'applications/' . uniqid() . '.json',
                'Body' => $applicationJson,
                'ACL' => 'private'
            ]);

            // Send an email notification with SES
            $to = 'ad9amigos@gmail.com'; // Replace with your email address
            $subject = 'New Rental Application Received';
            $body = "A new rental application has been submitted. Please check the S3 bucket for details.";

            try {
                $sesClient->sendEmail([
                    'Destination' => [
                        'ToAddresses' => [$to],
                    ],
                    'Message' => [
                        'Body' => [
                            'Text' => [
                                'Charset' => 'UTF-8',
                                'Data' => $body,
                            ],
                        ],
                        'Subject' => [
                            'Charset' => 'UTF-8',
                            'Data' => $subject,
                        ],
                    ],
                    'Source' => 'no-reply@example.com', // Replace with your verified SES email address
                ]);

                echo json_encode(['success' => true]);
            } catch (AwsException $e) {
                echo json_encode(['error' => 'Email sending failed: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['error' => 'Payment failed']);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
