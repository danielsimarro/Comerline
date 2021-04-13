<?php
if (($file = fopen("Productos.csv", "r")) !== FALSE) {
    echo "Executing script...."."\n";

    $new = fopen('prueba.csv', 'w');

    $header = array('sku,store_view_code,product_type,categories,name,description,weight,product_online,price,special_price,url_key,meta_keywords,meta_description,additional_attributes,qty,out_of_stock_qty,is_in_stock,related_skus,configurable_variations,configurable_variation_labels');
    fputs($new, implode(',',$header)."\n"); // Añadimos todas las columnas al nuevo archivo

    $count = 0; // Counter what we will use to skip the first line always
    while(($data = fgetcsv($file, 5000, ",")) !== FALSE){
//        for($line = 0; $line < count($data); $line++){
            $count++;
            if ($count == 1) {
                continue; // If the counter equals 1 that means it's the first line, so we skip it
            }
            $sku = $data[1];
            $attributeSetCode = $data[0];
            $productType = 'simple';
            $categories = 'Default Category/' . $data[0];
            $name = '"'. $data[30] . '"';
            $description = '"'. $data[33]. '"';
            $weight = $data[25];
            $productOnline = $data[3];
            $price = $data[7];
            $specialPrice = $data[6];
            $urlKey = $data[32];
            $metaKeywords = '"' . $data[35] . '"';
            $metaDescription = '"' . $data[34] . '"';
            if($data[0] === 'Llantas'){
//                $additionalAttributes = 'size='. $data[65] . '|color=' . $data[66] . '|mounting=' . $data[67] . '|diameter=' . $data[68];
                $additionalAttributes = 'Placeholder';
            } elseif ($data[0] === 'Neumaticos') {
                $additionalAttributes = 'Placeholder 2';
            } elseif ($data[0] === 'Kit muelles') {
                $additionalAttributes = 'Placeholder 3';
            } elseif ($data[0] === 'Suspensiones') {
                $additionalAttributes = 'Placeholder 4';
            } elseif ($data[0] === 'Separadores') {
                $additionalAttributes = 'Placeholder 5';
            }
            $qty = $data[21];
            $outOfStockQty = $data[22];
            $isInStock = $data[40];
            $product = $sku . ',' . $attributeSetCode. ',' . $productType . ',' . $categories . ',' . $name . ',' . $description . ',' . $weight .
                ',' . $productOnline . ',' . $price . ',' . $specialPrice . ',' . $urlKey . ',' . $metaKeywords . ',' . $metaDescription .
                ',' . $additionalAttributes . ',' . $qty . ',' . $outOfStockQty . ',' . $isInStock . "\n";
            fputs($new, $product);
//        }
    }
    echo "Finished execution."."\n";
}
?>
