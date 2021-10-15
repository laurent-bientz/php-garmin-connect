<?php

set_time_limit(0);

require "vendor/autoload.php";
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->loadEnv(__DIR__.'/.env');
$file = 'data/sync.json';
$directory = 'data/sync';
$lastDate = null;
$nbExported = 0;

try {
    $client = new \dawguk\GarminConnect([
        'username' => $_ENV['GARMIN_USERNAME'],
        'password' => $_ENV['GARMIN_PASSWORD'],
    ]);
} catch (Exception $exception) {
    #dd($exception);
}

try {
    $data = \json_decode(file_get_contents($file), true);
}
catch (Exception $e) {
    $data = [];
}
if (empty($data) || (array_key_exists('lastDate', $data) && ($lastDate = new \DateTime($data['lastDate'])) < new \DateTime())) {
    foreach (range(null !== $lastDate ? $lastDate->format('Y') : $_ENV['GARMIN_SINCE'], $currentYear = (int)date('Y')) as $index => $year) {
        if (!file_exists($currentDirectory = ($directory . DIRECTORY_SEPARATOR . $year))) {
            mkdir($currentDirectory);
        }

        $response = null;
        if (null !== $client) {
            try {
                $response = $client->getActivityList(0, 1000, null, [
                    'startDate' => null !== $lastDate ? $lastDate->format('Y-m-d') : sprintf('%d-01-01', $year),
                    'endDate' => ($currentYear === $year) ? date('Y-m-d') : sprintf('%d-12-31', $year),
                    'sortBy' => 'startLocal',
                    'sortOrder' => 'asc',
                ]);
            } catch (Exception $exception) {
                $response = null;
            }
        }

        if (!empty($response)) {
            foreach($response as $activity) {
                $fit = null;
                if (null !== $client) {
                    try {
                        $fit = $client->getDataFile(\dawguk\GarminConnect::DATA_TYPE_FIT, $activity->activityId);
                    } catch (Exception $exception) {
                        $fit = null;
                    }
                    file_put_contents($file, \json_encode([
                        'lastId' => $activity->activityId,
                        'lastDate' => $activity->startTimeLocal,
                    ]));
                }
                if (!empty($fit)) {
                    file_put_contents($currentDirectory . DIRECTORY_SEPARATOR . $activity->activityId . '.zip', $fit);
                    $nbExported++;
                }
            }
        }
    }
}

echo sprintf("%d files exported", $nbExported);
die();
