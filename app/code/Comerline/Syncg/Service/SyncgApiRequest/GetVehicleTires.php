<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Exception;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Comerline\Syncg\Service\SyncgApiService;

class GetVehicleTires extends SyncgApiService
{
    protected $method = Request::HTTP_METHOD_POST;

    private DirectoryList $dir;
    private array $vehiclesTiresGroup;
    private string $prefixLog;

    const VEHICLE_TIRE_GROUP_FILE = 'vehicle_tire_group.csv';

    public function __construct(
        Config          $configHelper,
        DirectoryList   $dir,
        Json            $json,
        ClientFactory   $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    )
    {
        $this->prefixLog = uniqid() . ' | G4100 Vehicles Tires |';
        $this->dir = $dir;
        $this->vehiclesTiresGroup = [];
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    /**
     * @return array
     */
    public function getVehiclesTiresGroup(): array
    {
        return $this->vehiclesTiresGroup;
    }

    public function buildParams($page)
    {
        $this->endpoint = 'api/g4100/list';
        $this->params = [
            'allow_redirects' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->getTokenFromDatabase()}",
            ],
            'body' => json_encode([
                'endpoint' => 'tp_1/listar',
                'fields' => json_encode(["c_1", "c_2", "c_3", "c_4", "c_5", "c_6", "c_7", "c_8", "c_9"]),
                'filters' => json_encode([
                    "inicio" => $page * 100,
                    "filtro" => []
                ]),
                'order' => json_encode(["campo" => "id", "orden" => "ASC"])
            ]),
        ];
    }

    public function send()
    {
        $page = 0;
        $contItems = 1;
        $this->buildParams($page);
        $response = $this->execute();
        if (!$this->existCache()) { // No exists cache, call api
            while ($response && isset($response['listado']) && $response['listado']) {
                $list = $response['listado'];
                foreach ($list as $row) {
                    $vehicleTire = $this->mountItemVehicleTireApi($row);
                    $this->logger->info(new Phrase($this->prefixLog . ' Page ' . $page . ' ' . $contItems . ' | ' . $vehicleTire['marca'] . ' ' . $vehicleTire['modelo'] . ' ' . $vehicleTire['type']));
                    $this->groupVehiclesTires($vehicleTire);
                    $contItems++;
                }
                $page++;
                $this->buildParams($page);
                $response = $this->execute();
            }
            $vehicleTireGroupFile = $this->dir->getPath('media') . '/' . self::VEHICLE_TIRE_GROUP_FILE;
            $handle = fopen($vehicleTireGroupFile, 'w');
            foreach ($this->vehiclesTiresGroup as $group) {
                $group['anclaje_group'] = implode(',', $group['anclaje_group']);
                fputcsv($handle, $group, ';');
            }
            fclose($handle);
        } else { // Get cache
            $this->readCache();
        }
    }

    private function readCache() {
        $vehicleTireGroupFile = $this->dir->getPath('media') . '/' . self::VEHICLE_TIRE_GROUP_FILE;
        if (($handle = fopen($vehicleTireGroupFile, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                $item = $this->mountItemVehicleTireGroupCache($data);
                $key = base64_encode($item['type']);
                $this->vehiclesTiresGroup[$key] = $item;
            }
            fclose($handle);
        }
    }

    private function existCache()
    {
        $vehicleTireGroupFile = $this->dir->getPath('media') . '/' . self::VEHICLE_TIRE_GROUP_FILE;
        $exists = file_exists($vehicleTireGroupFile) && is_file($vehicleTireGroupFile);
        if (!$exists) {
            $this->logger->error(new Phrase($this->prefixLog . ' No cache file.'));
        }
        return $exists;
    }

    private function groupVehiclesTires($vehicleTire)
    {
        $measures = ['ancho', 'diametro', 'et'];
        $type = $vehicleTire['type'];
        $key = base64_encode($type);
        if (!array_key_exists($key, $this->vehiclesTiresGroup)) {
            $this->vehiclesTiresGroup[$key] = $vehicleTire;
            foreach ($measures as $measure) {
                $this->vehiclesTiresGroup[$key][$measure . '_min'] = 0;
                $this->vehiclesTiresGroup[$key][$measure . '_max'] = 0;
            }
        }
        // Add attributes
        foreach ($measures as $measure) {
            $valor = floatval($vehicleTire[$measure]);
            $min = $this->vehiclesTiresGroup[$key][$measure . '_min'];
            $max = $this->vehiclesTiresGroup[$key][$measure . '_max'];
            if (!$min || $valor < $min) {
                $this->vehiclesTiresGroup[$key][$measure . '_min'] = $valor;
            }
            if ($valor > $max) {
                $this->vehiclesTiresGroup[$key][$measure . '_max'] = $valor;
            }
        }
        $anclaje = $vehicleTire['anclaje'];
        if (!isset($this->vehiclesTiresGroup[$key]['anclaje_group']) || !in_array($anclaje, $this->vehiclesTiresGroup[$key]['anclaje_group'])) {
            $this->vehiclesTiresGroup[$key]['anclaje_group'][] = $anclaje;
        }
    }

    private function mountItemVehicleTireApi($row): array
    {
        $vehicleTire = [];
        $vehicleTire['marca'] = $row['c_1'];
        $vehicleTire['modelo'] = $row['c_2'];
        $anoDesde = $row['c_4'];
        $anoHasta = $row['c_5'];
        $vehicleTire['ano'] = $anoDesde;
        if ($anoHasta) {
            $vehicleTire['ano'] = $anoDesde . ' - ' . $anoHasta;
        }
        $vehicleTire['name'] = $vehicleTire['marca'] . ' ' . $vehicleTire['modelo'];
        $vehicleTire['type'] = $vehicleTire['name'] . ' ' . $vehicleTire['ano'] . ' (' . $row['c_3'] . ')';
        $vehicleTire['ancho'] = $row['c_6'];
        $vehicleTire['diametro'] = $row['c_7'];
        $vehicleTire['et'] = $row['c_8'];
        $vehicleTire['anclaje'] = $row['c_9'];
        return $vehicleTire;
    }

    private function mountItemVehicleTireGroupCache($row): array
    {
        $vehicleTire = [];
        $vehicleTire['marca'] = $row[0];
        $vehicleTire['modelo'] = $row[1];
        $vehicleTire['ano'] = $row[2];
        $vehicleTire['name'] = $row[3];
        $vehicleTire['type'] = $row[4];
        $vehicleTire['ancho'] = floatval($row[5]);
        $vehicleTire['diametro'] = floatval($row[6]);
        $vehicleTire['et'] = floatval($row[7]);
        $vehicleTire['anclaje'] = $row[8];
        $vehicleTire['ancho_min'] = floatval($row[9]);
        $vehicleTire['ancho_max'] = floatval($row[10]);
        $vehicleTire['diametro_min'] = floatval($row[11]);
        $vehicleTire['diametro_max'] = floatval($row[12]);
        $vehicleTire['et_min'] = floatval($row[13]);
        $vehicleTire['et_max'] = floatval($row[14]);
        $vehicleTire['anclaje_group'] = explode(',', $row[15]);
        return $vehicleTire;
    }

}
