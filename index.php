<?php

require "vendor/autoload.php";
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->loadEnv(__DIR__.'/.env');
$file = 'data/records.json';
$needToUpdate = false;

try {
    $client = new \dawguk\GarminConnect([
        'username' => $_ENV['GARMIN_USERNAME'],
        'password' => $_ENV['GARMIN_PASSWORD'],
    ]);
} catch (Exception $exception) {
    $client = null;
}

$distances = [
    //1000 => '1k',
    5000 => '5k',
    10000 => '10k',
    21100 => 'Half',
    42200 => 'Marathon',
];
try {
    $data = \json_decode(file_get_contents($file), true);
}
catch (Exception $e) {
    $data = [];
}
foreach(range($_ENV['GARMIN_SINCE'], $currentYear = (int)date('Y')) as $year) {
    foreach($distances as $distance => $label) {
        if (isset($data[$distance][$year]) && $year !== $currentYear) {
            continue;
        }

        try {
            $response = $client->getActivityList(0, 1, 'running', [
                'minDistance' => \round($distance / 1.02),
                'maxDistance' => \round($distance * 1.02),
                'startDate' => sprintf('%d-01-01', $year),
                'endDate' => sprintf('%d-12-31', $year),
                'sortBy' => 'movingDuration',
                'sortOrder' => 'asc',
            ]);
        } catch (Exception $exception) {
            $response = null;
        }

        if (!empty($response) && !empty($race = array_shift($response))) {
            $duration = $race->movingDuration;
            if (!isset($data[$distance][$year]['duration']) || (isset($data[$distance][$year]['duration']) && $data[$distance][$year]['duration'] !== $duration)) {
                $needToUpdate = true;
            }
            $data[$distance][$year] = [
                'duration' => $duration,
                'data' => [
                    'id' => $race->activityId,
                    'date' => $race->startTimeLocal,
                    'distance' => $race->distance,
                    'time' => gmdate("H\"i's", $race->movingDuration),
                    'pace' => gmdate("i's", $duration / $race->distance * 1000),
                    'speed' => round(($race->distance / 1000) / ($duration / 3600), 1),
                    'cals' => null !== $race->calories ? (int)\round($race->calories) : null,
                    'hr' => null !== $race->averageHR ? (int)\round($race->averageHR) : null,
                    'cadence' => null !== $race->averageRunningCadenceInStepsPerMinute ? (int)\round($race->averageRunningCadenceInStepsPerMinute) : null,
                ],
            ];
        }
        else {
            $data[$distance][$year] = ['duration' => 0, 'data' => []];
        }
    }
}

if ($needToUpdate) {
    ksort($data);
    file_put_contents($file, \json_encode($data));
}

# TODO: display charts & historical table
