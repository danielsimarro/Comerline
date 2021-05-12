<?php

namespace Comerline\Syncg\Model;

class Order
{
    public function getOrderDetails($orderDetails)
    {
        $file = fopen('pub/order.txt', 'w');
        $orderChunks = array_chunk($orderDetails, 1, true); // We divide the array into numerated chunks to access them easily
        for ($i = 0; $i < count($orderChunks); $i++){
            foreach($orderChunks[$i] as $chunk){
                if (is_string($chunk) === true || is_numeric($chunk) === true) {
                    fwrite($file, $chunk . "\n"); // If it's a number or a string, we use it as it is
                } else {
                    if(is_array($chunk) && !(empty($chunk))){ // If not, it is the product, so we pick the SKU, name and ID
                        fwrite($file, $chunk[0]->getData('sku') . "\n");
                        fwrite($file, $chunk[0]->getData('name') . "\n");
                        fwrite($file, $chunk[0]->getData('id') . "\n");
                    }
                }
            }
        }
    }
}
