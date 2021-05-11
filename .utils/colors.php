<?php
$colors = [];

$txt = fopen("colors.txt", "w");

if (($file = fopen("Productos.csv", "r")) !== false) {
    echo "Executing script...." . "\n";

    $count = 0;
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        } else {
            if ($data[75]) {
                $colors[] = $data[75];
            }
        }
    }
}

$uniqueColors = array_unique($colors);
foreach ($uniqueColors as $color) {
    fwrite($txt, $color . "\n");
}
