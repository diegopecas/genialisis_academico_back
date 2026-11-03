<?php
class WaTemplates
{
    // Config de la línea (BD maestra). Las plantillas son de la cuenta de
    // negocio (WABA) de la línea; con una sola línea conectada puede no llegar.
    private static function getConfig($idLinea = null)
    {
        return WaLineas::configActiva($idLinea);
    }

    private static function callGraphAPI($method, $url, $payload = null, $accessToken = '')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    /**
     * Listar TODOS los templates del WABA.
     */
    public static function getAll()
    {
        try {
            $config = self::getConfig(Flight::request()->query['id_linea'] ?? null);
            $wabaId = $config['wa_business_account_id'];
            $apiVersion = $config['api_version'];
            $token = $config['access_token'];

            $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates?limit=100";
            $result = self::callGraphAPI('GET', $url, null, $token);

            if ($result['http_code'] == 200) {
                $templates = $result['response']['data'] ?? [];

                $formatted = array_map(function ($t) {
                    $bodyComponent = null;
                    foreach (($t['components'] ?? []) as $comp) {
                        if ($comp['type'] === 'BODY') {
                            $bodyComponent = $comp;
                            break;
                        }
                    }

                    return [
                        'id' => $t['id'] ?? null,
                        'name' => $t['name'],
                        'status' => $t['status'],
                        'category' => $t['category'],
                        'language' => $t['language'],
                        'components' => $t['components'] ?? [],
                        'body_text' => $bodyComponent['text'] ?? '',
                    ];
                }, $templates);

                usort($formatted, function ($a, $b) {
                    $order = ['APPROVED' => 0, 'PENDING' => 1, 'REJECTED' => 2];
                    $oa = $order[$a['status']] ?? 3;
                    $ob = $order[$b['status']] ?? 3;
                    return $oa - $ob ?: strcmp($a['name'], $b['name']);
                });

                Flight::json($formatted);
            } else {
                Flight::json([
                    'error' => 'Error al obtener templates de Meta',
                    'detalle' => $result['response']
                ], 500);
            }
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar solo templates APROBADOS (para selector en chat).
     */
    public static function getAprobados()
    {
        try {
            $config = self::getConfig(Flight::request()->query['id_linea'] ?? null);
            $wabaId = $config['wa_business_account_id'];
            $apiVersion = $config['api_version'];
            $token = $config['access_token'];

            $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates?status=APPROVED&limit=100";
            $result = self::callGraphAPI('GET', $url, null, $token);

            if ($result['http_code'] == 200) {
                $templates = $result['response']['data'] ?? [];

                $formatted = array_map(function ($t) {
                    return [
                        'name' => $t['name'],
                        'category' => $t['category'],
                        'language' => $t['language'],
                        'components' => $t['components'] ?? [],
                    ];
                }, $templates);

                Flight::json(['templates' => $formatted]);
            } else {
                Flight::json([
                    'error' => 'Error al obtener templates aprobados',
                    'detalle' => $result['response']
                ], 500);
            }
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un template nuevo en Meta.
     * Espera: name, category (UTILITY|MARKETING|AUTHENTICATION), language (es), body_text, variables_example (array)
     */
    public static function create()
    {
        try {
            $data = self::getData();
            $config = self::getConfig($data['id_linea'] ?? null);
            $wabaId = $config['wa_business_account_id'];
            $apiVersion = $config['api_version'];
            $token = $config['access_token'];

            $name = $data['name'] ?? null;
            $category = $data['category'] ?? 'UTILITY';
            $language = $data['language'] ?? 'es';
            $bodyText = $data['body_text'] ?? null;
            $variablesExample = $data['variables_example'] ?? [];
            $headerText = $data['header_text'] ?? null;
            $footerText = $data['footer_text'] ?? null;

            if (!$name || !$bodyText) {
                Flight::json(['error' => 'name y body_text son requeridos'], 400);
                return;
            }

            $name = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $name));

            $components = [];

            if ($headerText) {
                $components[] = [
                    'type' => 'HEADER',
                    'format' => 'TEXT',
                    'text' => $headerText
                ];
            }

            $bodyComponent = [
                'type' => 'BODY',
                'text' => $bodyText
            ];

            if (!empty($variablesExample)) {
                $bodyComponent['example'] = [
                    'body_text' => [$variablesExample]
                ];
            }
            $components[] = $bodyComponent;

            if ($footerText) {
                $components[] = [
                    'type' => 'FOOTER',
                    'text' => $footerText
                ];
            }

            $payload = [
                'name' => $name,
                'category' => strtoupper($category),
                'language' => $language,
                'components' => $components
            ];

            $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates";
            $result = self::callGraphAPI('POST', $url, $payload, $token);

            if ($result['http_code'] == 200 && isset($result['response']['id'])) {
                Flight::json([
                    'success' => true,
                    'id' => $result['response']['id'],
                    'status' => $result['response']['status'] ?? 'PENDING',
                    'name' => $name
                ]);
            } else {
                Flight::json([
                    'success' => false,
                    'error' => 'Error al crear template en Meta',
                    'detalle' => $result['response']
                ], 500);
            }
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un template por nombre.
     */
    public static function delete($name)
    {
        try {
            $config = self::getConfig(Flight::request()->query['id_linea'] ?? null);
            $wabaId = $config['wa_business_account_id'];
            $apiVersion = $config['api_version'];
            $token = $config['access_token'];

            $name = preg_replace('/[^a-z0-9_]/i', '', $name);

            $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates?name={$name}";
            $result = self::callGraphAPI('DELETE', $url, null, $token);

            if ($result['http_code'] == 200 && ($result['response']['success'] ?? false)) {
                Flight::json(['success' => true]);
            } else {
                Flight::json([
                    'success' => false,
                    'error' => 'Error al eliminar template',
                    'detalle' => $result['response']
                ], 500);
            }
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function getData()
    {
        $data = [];
        if (!empty($_POST)) {
            $data = $_POST;
        } elseif (Flight::request()->data) {
            foreach (Flight::request()->data as $key => $value) {
                $data[$key] = $value;
            }
        }
        $jsonBody = json_decode(Flight::request()->getBody(), true);
        if ($jsonBody) {
            $data = array_merge($data, $jsonBody);
        }
        return $data;
    }
}