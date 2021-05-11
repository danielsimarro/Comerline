<?php
$sizes = [];

$txt = fopen("sizes.txt", "w");

if (($file = fopen("Productos.csv", "r")) !== false) {
    echo "Executing script...." . "\n";

    $count = 0;
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        } else {
            if ($data[74]) {
                $sizes[] = $data[74];
            }
            if ($data[78]) {
                $sizes[] = $data[78];
            }
        }
    }
}

$uniqueSizes = array_unique($sizes);
foreach ($uniqueSizes as $size) {
    fwrite($txt, $size . "\n");
}
