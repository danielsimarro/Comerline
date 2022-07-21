<?php

namespace Comerline\Syncg\Service\SyncgApiRequest;

use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Rest\Request;
use Comerline\Syncg\Helper\Config;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class GetVehicleTires extends SyncgApiService
{
    protected $method = Request::HTTP_METHOD_POST;

    private array $vehiclesTires;
    private array $vehiclesTiresGroup;
    private string $prefixLog;

    public function __construct(
        Config          $configHelper,
        Json            $json,
        ClientFactory   $clientFactory,
        ResponseFactory $responseFactory,
        LoggerInterface $logger
    )
    {
        $this->prefixLog = uniqid() . ' | G4100 Vehicles Tires |';
        parent::__construct($configHelper, $json, $responseFactory, $clientFactory, $logger);
    }

    /**
     * @return array
     */
    public function getVehiclesTires(): array
    {
        return $this->vehiclesTires;
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
        while ($response && isset($response['listado'])) {
            $list = $response['listado'];
            foreach ($list as $row) {
                $vehicleTire = $this->mountItemVehicleTire($row);
                $this->logger->info(new Phrase($this->prefixLog . ' Page ' . $page . ' ' . $contItems . ' | ' . $vehicleTire['marca'] . ' ' . $vehicleTire['modelo'] . ' ' . $vehicleTire['type']));
                $this->vehiclesTires[] = $vehicleTire;
                $contItems;
            }
            $page++;
            $this->buildParams($page);
            $response = $this->execute();
        }

        // Group vehicles tires
        $this->groupVehiclesTires();
    }

    private function groupVehiclesTires()
    {
        $measures = ['ancho', 'diametro', 'et', 'anclaje'];
        $this->vehiclesTiresGroup = [];
        foreach ($this->vehiclesTires as $vehicleTire) {
            if (!array_key_exists($vehicleTire['marca'], $this->vehiclesTiresGroup)) {
                $this->vehiclesTiresGroup[$vehicleTire['marca']] = [];
            }
            if (!array_key_exists($vehicleTire['name'], $this->vehiclesTiresGroup[$vehicleTire['marca']])) {
                $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']] = [];
            }
            if (!array_key_exists($vehicleTire['type'], $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']])) {
                $initMeasure = [];
                foreach ($measures as $measure) {
                    $initMeasure[$measure . '_min'] = 0;
                    $initMeasure[$measure . '_max'] = 0;
                }
                $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']][$vehicleTire['type']] = $initMeasure;
            }
            // Add attributes
            foreach ($measures as $measure) {
                $valor = $vehicleTire[$measure];
                $min = $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']][$vehicleTire['type']][$measure . '_min'];
                $max = $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']][$vehicleTire['type']][$measure . '_max'];
                if (!$min || $valor < $min) {
                    $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']][$vehicleTire['type']][$measure . '_min'] = $valor;
                }
                if ($valor > $max) {
                    $this->vehiclesTiresGroup[$vehicleTire['marca']][$vehicleTire['name']][$vehicleTire['type']][$measure . '_max'] = $valor;
                }
            }
        }
    }

    private function mountItemVehicleTire($row): array
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
        $vehicleTire['diametro'] = $row['c_6'];
        $vehicleTire['et'] = $row['c_7'];
        $vehicleTire['anclaje'] = $row['c_8'];
        return $vehicleTire;
    }

}
