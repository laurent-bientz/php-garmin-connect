<?php

require "vendor/autoload.php";

use dawguk\GarminConnect;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->loadEnv(__DIR__.'/.env');

$file = 'data/records.json';
$fileRaces = 'data/races.json';
$needToUpdate = false;
$client = null;
$refresh = (array_key_exists('refresh', $_GET) && null !== ($refresh = $_GET['refresh'])) ? ('all' === $refresh) ? 'all' : 'current' : null;
$distances = [
    /*
    1000 => [
        'label' => '1k',
        'color' => [
            'alias' => 'light',
            'hex' => '#F8F9FA',
        ],
    ],
    */
    5000 => [
        'label' => '5k',
        'color' => [
            'alias' => 'info',
            'hex' => '#0DCAF0',
        ],
    ],
    10000 => [
        'label' => '10k',
        'color' => [
            'alias' => 'success',
            'hex' => '#198754',
        ],
    ],
    21100 => [
        'label' => 'Half',
        'color' => [
            'alias' => 'warning',
            'hex' => '#FFC107',
        ],
    ],
    42200 => [
        'label' => 'Marathon',
        'color' => [
            'alias' => 'danger',
            'hex' => '#DC3545',
        ],
    ],
    50000 => [
        'label' => '50k',
        'color' => [
            'alias' => 'secondary',
            'hex' => '#6C757D',
        ],
    ],
    100000 => [
        'label' => '100k',
        'color' => [
            'alias' => 'dark',
            'hex' => '#343A40',
        ],
    ],
];
$rankingMatch = [
    1 => '<span class="text-dark badge-circle medal-gold">1</span>',
    2 => '<span class="text-light badge-circle medal-silver">2</span>',
    3 => '<span class="text-light badge-circle medal-bronze">3</span>',
    4 => '<span class="badge-square">4</span>',
    5 => '<span class="badge-square">5</span>',
    6 => '<span class="badge-square">6</span>',
    7 => '<span class="badge-square">7</span>',
    8 => '<span class="badge-square">8</span>',
    9 => '<span class="badge-square">9</span>',
];

try {
    $data = \json_decode(@file_get_contents($file), true);
}
catch (Exception $e) {
    $data = [];
}
try {
    $races = \json_decode(@file_get_contents($fileRaces), true);
}
catch (Exception $e) {
    $races = [];
}

if (empty($data) || null !== $refresh) {
    try {
        $client = new GarminConnect([
            'username' => $_ENV['GARMIN_USERNAME'],
            'password' => $_ENV['GARMIN_PASSWORD'],
        ]);
    } catch (Exception $exception) {
        #dd($exception);
    }
    foreach (range($_ENV['GARMIN_SINCE'], $currentYear = (int)date('Y')) as $year) {
        foreach ($distances as $distance => $label) {
            if ('all' !== $refresh && isset($data[$distance][$year]) && $year !== $currentYear) {
                continue;
            }

            $response = null;
            if (null !== $client) {
                try {
                    $response = $client->getActivityList(0, 1, 'running', [
                        'minDistance' => \round($distance / 1.02),
                        'maxDistance' => \round($distance * 1.02),
                        'startDate' => sprintf('%d-01-01', $year),
                        'endDate' => ($currentYear === $year) ? date('Y-m-d') : sprintf('%d-12-31', $year),
                        'sortBy' => 'elapsedDuration',
                        'sortOrder' => 'asc',
                    ]);
                } catch (Exception $exception) {
                    $response = null;
                }
            }

            if (!empty($response) && !empty($race = array_shift($response))) {
                $duration = (null !== $race->movingDuration) ? $race->movingDuration : $race->elapsedDuration;
                if (!isset($data[$distance][$year]['duration']) || $data[$distance][$year]['duration'] !== $duration) {
                    $needToUpdate = true;
                }
                $data[$distance][$year] = [
                    'duration' => $duration,
                    'data' => [
                        'id' => $race->activityId,
                        'date' => $race->startTimeLocal,
                        'distance' => $race->distance,
                        'time' => 0 < ($hours = (int)floor($duration / 3600)) ? sprintf("%02d\h%02d'%02d", $hours, (int)floor(($duration / 60) % 60), (int)($duration % 60)) : sprintf("%02d'%02d", (int)floor(($duration / 60) % 60), (int)($duration % 60)),
                        'pace' => (int)gmdate("i", $duration / $race->distance * 1000) . '\'' . gmdate("s", $duration / $race->distance * 1000),
                        'speed' => round(($race->distance / 1000) / ($duration / 3600), 1),
                        'cals' => null !== $race->calories ? (int)\round($race->calories) : null,
                        'hr' => null !== $race->averageHR ? (int)\round($race->averageHR) : null,
                        'cadence' => null !== $race->averageRunningCadenceInStepsPerMinute ? (int)\round($race->averageRunningCadenceInStepsPerMinute) : null,
                    ],
                ];
            } else {
                $data[$distance][$year] = ['duration' => 0, 'data' => []];
            }
        }
    }
}
if ($needToUpdate) {
    ksort($data);
    file_put_contents($file, \json_encode($data));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Running Personal Records</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KyZXEAg3QhqLMpG8r+8fhAXLRk2vvoC2f3B09zVXn8CA5QIVfZOJ3BCsw2P0p/We" crossorigin="anonymous">
    <style>
        .badge-square {
            display: inline-block;
            padding: 0 7px;
            background-color: #539bf0;
            color: #fff;
            font-weight: bold;
        }
        .badge-circle {
            display: inline-block;
            padding: 0 7px;
            border-radius: 50%;
            font-weight: bold;
        }
        .medal-gold {
            background-color: #e8a30c;
        }
        .medal-silver {
            background-color: #808080;
        }
        .medal-bronze {
            background-color: #ae5c2d;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>Running</h1>
        <hr />

        <h2 id="pb">🏆<a href="#pb" class="link-dark text-decoration-none"> Personal Best</a></h2>
        <div class="row">
            <?php foreach($data as $distance => $years): ?>
                <?php
                    $times = $years;
                    usort($times, function ($a, $b) {
                        if (empty($b['duration'])) {
                            return -1;
                        }
                        return (!empty($a['duration']) && $a['duration'] < $b['duration']) ? -1 : 1;
                    });
                ?>
                <div class="col-sm">
                    <div class="card text-white bg-<?= $distances[$distance]['color']['alias'] ?> mb-3">
                        <div class="card-header"><?= $distances[$distance]['label'] ?><?= 0 < \count($times) && !empty($times[0]['duration']) ? ' / ' . (new \DateTime($times[0]['data']['date']))->format('Y') : '' ?></div>
                        <div class="card-body">
                            <h1 class="card-title text-center"><?= 0 < \count($times) && !empty($times[0]['duration']) ? $times[0]['data']['time'] : '-' ?></h1>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 id="evolution"><a href="#evolution" class="link-dark text-decoration-none">📉 Evolution</a></h2>
        <div class="row">
            <canvas id="myChart"></canvas>
        </div>

        <h2 id="history"><a href="#history" class="link-dark text-decoration-none">📅 History</a></h2>
        <div class="row">
            <?php foreach($data as $distance => $years): ?>
                <?php $distanceEffortStarted = false; ?>
                <?php $distanceSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $distances[$distance]['label']))); ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped caption-top">
                        <caption id="history-<?= $distanceSlug ?>"><a href="#history-<?= $distanceSlug ?>" class="link-dark text-decoration-none"><?= $distances[$distance]['label'] ?></a></caption>
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" scope="col">Year</th>
                                <th class="text-center" scope="col">Time</th>
                                <th class="text-center" scope="col">Pace</th>
                                <th class="text-center" scope="col">Speed</th>
                                <th class="text-center" scope="col">Cadence</th>
                                <th class="text-center" scope="col">HR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($years as $year => $metrics): ?>
                                <?php
                                    if (!empty($metrics['data'])) {
                                        $distanceEffortStarted = true;
                                    }
                                    if (!$distanceEffortStarted) {
                                        continue;
                                    }
                                ?>
                                <tr>
                                    <td class="text-center"><?= $year ?></td>
                                    <th class="text-center" scope="row"><?= (!empty($metrics['duration'])) ? $metrics['data']['time'] : '-' ?></th>
                                    <td class="text-center"><?= (!empty($metrics['duration'])) ? $metrics['data']['pace'] : '-' ?></td>
                                    <td class="text-center"><?= (!empty($metrics['duration'])) ? number_format($metrics['data']['speed'], 1) . ' km/h' : '-' ?></td>
                                    <td class="text-center"><?= (!empty($metrics['duration']) && isset($metrics['data']['cadence'])) ? $metrics['data']['cadence'] . ' spm' : '-' ?></td>
                                    <td class="text-center"><?= (!empty($metrics['duration']) && isset($metrics['data']['hr'])) ? $metrics['data']['hr'] . ' bpm' : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($races)): ?>
            <h2 id="races"><a href="#races" class="link-dark text-decoration-none">🏁 Races</a></h2>
            <div class="row">
                <?php foreach($races as $year => $racesOfYear): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped caption-top">
                            <caption id="races-<?= $year ?>">
                                <a href="#races-<?= $year ?>" class="link-dark text-decoration-none"><?= $year ?></a>
                                <?='<br />------<br />' . \count($racesOfYear) . ' races.<br />'?>
                                <?php if (!empty($performancesScratch = implode(' - ', array_map(function($count, $label) { return '<strong>' . $count . 'x</strong> ' . $label;}, $performances = array_filter([
                                    '🥇' => \count(array_filter($racesOfYear, function ($race) { return 1 === $race['scratch'];})),
                                    '🥈' => \count(array_filter($racesOfYear, function ($race) { return 2 === $race['scratch'];})),
                                    '🥉' => \count(array_filter($racesOfYear, function ($race) { return 3 === $race['scratch'];})),
                                    'Top 🔟' => \count(array_filter($racesOfYear, function ($race) { return 11 > $race['scratch'];})),
                                ], function ($number) { return 0 < $number;}), array_keys($performances))))): ?>
                                    <br /><span style="display:inline-block; min-width:70px;"><u>Scratch:</u></span> <?= $performancesScratch ?>
                                <?php endif; ?>
                                <?php if (!empty($performancesCategory = implode(' - ', array_map(function($count, $label) { return '<strong>' . $count . 'x</strong> ' . $label;}, $performances = array_filter([
                                    '🥇' => \count(array_filter($racesOfYear, function ($race) { return 1 === $race['category'];})),
                                    '🥈' => \count(array_filter($racesOfYear, function ($race) { return 2 === $race['category'];})),
                                    '🥉' => \count(array_filter($racesOfYear, function ($race) { return 3 === $race['category'];})),
                                    'Top 🔟' => \count(array_filter($racesOfYear, function ($race) { return 11 > $race['category'];})),
                                ], function ($number) { return 0 < $number;}), array_keys($performances))))): ?>
                                    <br /><span style="display:inline-block; min-width:70px;"><u>Category:</u></span> <?= $performancesCategory ?>
                                <?php endif; ?>
                            </caption>
                            <thead class="table-light">
                                <tr>
                                    <th class="text-left" scope="col" style="white-space: nowrap;">Scratch</th>
                                    <th class="text-center" scope="col">Category</th>
                                    <th class="text-center" scope="col">Race</th>
                                    <th class="text-center" scope="col">Date</th>
                                    <th class="text-center" scope="col">Type</th>
                                    <th class="text-center" scope="col">Distance</th>
                                    <th class="text-center" scope="col">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($racesOfYear as $race): ?>
                                <tr>
                                    <td class="text-left"><?= (($rankingMatch[$race['scratch']] ?? number_format($race['scratch'], 0, '.', ' '))) . ' <small class="text-muted">/ ' . number_format($race['registrants'], 0, '.', ' ') . '</small>' ?></td>
                                    <td class="text-center"><?= $rankingMatch[$race['category']] ?? number_format($race['category'], 0, '.', ' ') ?></td>
                                    <th class="text-center"><a href="https://www.strava.com/activities/<?= $race['strava'] ?>" target="_blank"><?= $race['race'] ?></a> <?= $race['pacer'] ? '<span alt="Pacer" title="Pacer">🅿️</span>' : '' ?></th>
                                    <td class="text-center"><?= $race['date'] ?></td>
                                    <td class="text-center"><?= $race['type'] ?></td>
                                    <td class="text-center"><?= $race['distance'] ?></td>
                                    <td class="text-center"><?= $race['time'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.5.0/dist/chart.min.js"></script>
<script type="text/javascript">
    <?php if (!empty($data)): ?>
    <?php
            $clone = $data;
            $datasets = [];
            foreach($data as $distance => $years) {
                $datasets[] = [
                    'label' => $distances[$distance]['label'],
                    'data' => array_values(array_map(function ($year) {
                        return (empty($year['duration'])) ? null : ($year['duration'] / 60);
                    }, $years)),
                    'backgroundColor' => $distances[$distance]['color']['hex'],
                    'borderColor' => $distances[$distance]['color']['hex'],
                    'fill' => true
                ];
            }
        ?>
        const ctx = document.getElementById('myChart').getContext('2d');
        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= \json_encode(array_keys(array_shift($clone))) ?>,
                datasets: <?= \json_encode($datasets) ?>
            },
            options: {
                responsive: true,
                spanGaps: true,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        position: 'average',
                        callbacks: {
                            label: function (tooltipItem, data) {
                                const dateStr = new Date(parseInt(tooltipItem.formattedValue.replace(',', '.') * 60) * 1000).toISOString().substr(11, 8);
                                const duration = ('00' !== (hours = dateStr.substr(0, 2)) ? hours + 'h' : '') + dateStr.substr(3, 2) + '\'' + dateStr.substr(6, 2);
                                return tooltipItem.dataset.label + ': ' + duration;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                hover: {
                    mode: 'index',
                    intersec: false
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Years'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Minutes'
                        }
                    }
                }
            }
        });
    <?php endif; ?>
</script>
</html>
