<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class CrocodilTestController extends Controller
{
    private $username = '200031';
    private $password = 'STA231';
    private $baseUrl = 'http://emarket.crocodil.com/WebServices/wsCrocodilOrder.asmx';

    public function index(): Response
    {
        return Inertia::render('CrocodilTest/Index');
    }

    public function getDeliveries(Request $request)
    {
        try {
            // Construction du SOAP XML pour getDeliveries
            $soapXml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <getDeliveries xmlns="http://tempuri.org/">
      <username>' . $this->username . '</username>
      <password>' . $this->password . '</password>
    </getDeliveries>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://tempuri.org/getDeliveries'
            ])->send('POST', $this->baseUrl, [
                'body' => $soapXml
            ]);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'data' => $response->body()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportDeliveries()
    {
        try {
            // Appeler le webservice
            $soapXml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <getDeliveries xmlns="http://tempuri.org/">
      <username>' . $this->username . '</username>
      <password>' . $this->password . '</password>
    </getDeliveries>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://tempuri.org/getDeliveries'
            ])->send('POST', $this->baseUrl, [
                'body' => $soapXml
            ]);

            // Parser le XML SOAP
            $soapResponse = $response->body();
            
            // Extraire le contenu entre <getDeliveriesResult> et </getDeliveriesResult>
            preg_match('/<getDeliveriesResult>(.*?)<\/getDeliveriesResult>/s', $soapResponse, $matches);
            
            if (empty($matches[1])) {
                throw new \Exception('Pas de résultat trouvé dans la réponse SOAP');
            }
            
            // Décoder les entités HTML (&lt; devient <, &gt; devient >, etc.)
            $decodedXml = html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            
            // Parser le XML des livraisons
            $deliveriesXml = new \SimpleXMLElement($decodedXml);
            
            // Créer le CSV
            $filename = 'crocodil_deliveries_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'r+');
            
            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");
            
            // En-têtes
            fputcsv($handle, [
                'Date commande',
                'N° commande',
                'Date livraison',
                'N° livraison',
                'Référence produit',
                'Description',
                'CNK',
                'Prix brut',
                'Prix net',
                'Prix vente',
                'TVA',
                'Quantité',
                'N° lot',
                'Date péremption',
                'Code VHB'
            ], ';');
            
            // Données
            foreach ($deliveriesXml->delivery as $delivery) {
                fputcsv($handle, [
                    (string)$delivery->orderdate,
                    (string)$delivery->ordernumber,
                    (string)$delivery->deliverydate,
                    (string)$delivery->deliverynumber,
                    (string)$delivery->productnr,
                    (string)$delivery->description,
                    (string)$delivery->cnknumber,
                    (string)$delivery->grossprice,
                    (string)$delivery->netprice,
                    (string)$delivery->sellpricepharmacists,
                    (string)$delivery->vat,
                    (string)$delivery->amount,
                    (string)$delivery->lotnumber,
                    (string)$delivery->expirydate,
                    (string)$delivery->vhbcode
                ], ';');
            }
            
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllDeliveries(Request $request)
    {
        try {
            $soapXml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <getAllDeliveries xmlns="http://tempuri.org/">
      <username>' . $this->username . '</username>
      <password>' . $this->password . '</password>
    </getAllDeliveries>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://tempuri.org/getAllDeliveries'
            ])->send('POST', $this->baseUrl, [
                'body' => $soapXml
            ]);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'data' => $response->body()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPriceList(Request $request)
    {
        try {
            $soapXml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <getPriceList xmlns="http://tempuri.org/">
      <username>' . $this->username . '</username>
      <password>' . $this->password . '</password>
    </getPriceList>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'http://tempuri.org/getPriceList'
            ])->send('POST', $this->baseUrl, [
                'body' => $soapXml
            ]);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'data' => $response->body()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
