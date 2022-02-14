<?php

namespace Comerline\ConfigCar\Helper;

use Exception;
use Magento\Framework\Phrase;
use Magento\Framework\Filesystem\DirectoryList;


class ConfigCarHelper
{

    private $csvData;
    private $filteredCsvData;
    private $dir;

    public function __construct(
        DirectoryList $dir
    )
    {
        $this->dir = $dir;
    }

    private function readCsv($csv): array
    {
        try {
            $file = @file($csv);
            if (!$file) {
                throw new Exception('File does not exists.');
            }
        } catch (Exception $e) {
            die();
        }
        $rows = array_map(function ($row) {
            return str_getcsv($row, ';');
        }, $file);
        $header = array_shift($rows);
        $data = [];
        foreach ($rows as $row) {
            $data[] = array_combine($header, $row); // We save every row of the CSV into an array
        }
        return $data;
    }

    public function mountOptionText($option): string
    {
        if (strpos($option, ',')) {
            $explodedOption = explode(',', $option);
            $optionText = $explodedOption[0] . '.' . $explodedOption[1] . '0';
        } else {
            $optionText = $option . '.00';
        }
        return $optionText;
    }

    public function getCsvData(): array
    {
        if (!$this->csvData) {
            $csvFile = $this->dir->getPath('media') . '/mapeo_llantas_modelos.csv';
            $this->csvData = $this->readCsv($csvFile);
        }
        return $this->csvData;
    }

    public function getFilteredCsvData($brand, $model, $year)
    {
        if (!$this->filteredCsvData) {
            $csvData = $this->getCsvData();
            foreach ($csvData as $csv) {
                if ($csv['marca'] === $brand && $csv['modelo'] === $model && ($year === $csv['ano_desde'] . ' - ' . $csv['ano_hasta'])
                    || ($year === $csv['ano_desde'])) {
                    $this->filteredCsvData[] = $csv;
                }
            }
        }
        return $this->filteredCsvData;
    }
}
