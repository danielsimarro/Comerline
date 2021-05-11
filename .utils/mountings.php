<?php
$mountings = [];

$txt = fopen("mountings.txt", "w");

if (($file = fopen("Productos.csv", "r")) !== false) {
    echo "Executing script...." . "\n";

    $count = 0;
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        } else {
            if ($data[76]) {
                $mountings[] = $data[76];
            }
            if ($data[80]) {
                $mountings[] = $data[80];
            }
        }
    }
}

$uniqueMountings = array_unique($mountings);
foreach ($uniqueMountings as $mounting) {
    fwrite($txt, $mounting . "\n");
}
