<?php
$thicknesses = [];

$txt = fopen("thicknesses.txt", "w");

if (($file = fopen("Productos.csv", "r")) !== false) {
    echo "Executing script...." . "\n";

    $count = 0;
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        } else {
            if ($data[79]) {
                $thicknesses[] = $data[79];
            }
        }
    }
}

$uniqueThicknesses = array_unique($thicknesses);
foreach ($uniqueThicknesses as $thickness) {
    fwrite($txt, $thickness . "\n");
}
