
<?php

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'services/PecService.php';

PecService::setLogin($apiUser, $apiKeypec);

// --- Получаем все нужные данные ---
// Вычисляем массу и объем в нужных единицах для одного места
// Масса в кг
$weight_kg = $total_mass_g / 1000 / $transport_places;

// Объем в м³ (корень кубический из объема на одно место, для габаритов)
$volume_per_place_cm3 = $total_volume_cm3 / $transport_places;
$cube_side_cm = pow($volume_per_place_cm3, 1/3);

// Объем в м³ (для передачи в API)
$volume_m3 = $volume_per_place_cm3 / 1e6; // см³ в м³

// Определяем плательщика
$payer = ($payer_delivery != "Покупатель") ? 1 : 2; // 1 - отправитель, 2 - покупатель

// Определяем форму получателя
switch ($shipment_to) {
    case "1598":
        $receiver_legalForm = 3; // физлицо
        break;
    default:
        $innLength = strlen($company_Inn);
        if ($innLength == 10) {
            $receiver_legalForm = 1; // ИП
        } elseif ($innLength > 10) {
            $receiver_legalForm = 2; // ООО
        } else {
            $receiver_legalForm = 3; // физлицо по умолчанию
        }
        break;
}

// Определяем id склада получателя
$receiver_WarehouseId = PecService::findZoneByAddress($address);

// --- Формируем данные для API ---
// Формируем данные для калькулятора стоимости
$calculatorData = [
    "senderWarehouseId" => $senderWarehouseId,
    "receiverWarehouseId" => $receiver_WarehouseId,
    "plannedDateTime" => $plannedDateTime,
    "isInsurance" => true,
    "isInsurancePrice" => $opportunity,
    "cargos" => [
        [
            "weight" => $weight_kg * $transport_places, // общая масса в кг
            "volume" => $volume_m3 * $transport_places, // общий объем в м³
        ]
    ],
];

// Формируем данные для регистрации отправки
$registerData = [
    "sender" => [
        "inn" => "7810774157",
        "legalForm" => 1,
        "title" => "ООО «ГК Автоматика»",
        "warehouseId" => $senderWarehouseId,
        "personPhones" => [
            ["phone" => "+79697959265"]
        ],
        "person" => "Кутырев Алексей"
    ],
    "cargos" => [
        [
            "common" => [
                "type" => 3, // сборный груз
                "weight" => $weight_kg, // масса одного места в кг
                "volume" => $volume_m3, // объем одного места в м³
                "positionsCount" => $transport_places,
                "description" => "Оборудование вентиляционное, без защитной упаковки",
            ],
            "receiver" => [
                "warehouseId" => $receiver_WarehouseId,
                "legalForm" => $receiver_legalForm,
                "inn" => ($receiver_legalForm != 3) ? $company_Inn : null,
                "title" => ($receiver_legalForm != 3) ? $company_Name : "$contact_last_name $contact_first_name $contact_patronymic",
                "individual" => [
                    "lastName" => ($receiver_legalForm == 3) ? $contact_last_name : null,
                    "firstName" => ($receiver_legalForm == 3) ? $contact_first_name : null,
                    "patronymic" => ($receiver_legalForm == 3) ? $contact_patronymic : null,
                ],
                "personPhones" => [
                    ["phone" => $contact_phone]
                ],
                "person" => "$contact_last_name $contact_first_name $contact_patronymic",
            ],
            "services" => [
                "hardPacking" => ["enabled" => false],
                "sealing" => ["enabled" => false],
                "strapping" => ["enabled" => false],
                "documentsReturning" => ["enabled" => false],
                "delivery" => ["enabled" => false],

                "transporting" => [
                    "payer" => [
                        "type" => $payer,
                    ]
                ],
                "pickUp" => [
                    "payer" => [
                        "type" => $payer,
                    ]
                ],
                "insurance" => [
                    "enabled" => true,
                    "cost" => $opportunity,
                    "payer" => [
                        "type" => $payer,
                    ]
                ],
            ],
        ]
    ],
];

// Удаляем null в receiver для регистрации отправки
$registerData['cargos'][0]['receiver'] = array_filter($registerData['cargos'][0]['receiver'], fn($v) => $v !== null);

// --- Отправляем запросы ---
// Получаем лог стоимости перевозки
$calculatorResponse = PecService::calculatePrice($calculatorData);

// Получаем лог регистрации отправки
$registerResponse = PecService::preregistrationSubmit($registerData);

// --- Логируем данные калькулятора и регистрации отправки ---
$logFile = __DIR__ . '/log_pec_address.json';
$logData = [
    'timestamp' => date('c'),
    'deal_id' => $deal_Id,
    'calculator_request' => $calculatorData,
    'calculator_response' => $calculatorResponse,
    'register_request' => $registerData,
    'register_response' => $registerResponse,
];

// Сохраняем лог
file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Сохраняем "Акт приема-передачи"
PecService::getPDF($registerResponse['cargos'][0]['cargoCode'], $deal_Id, __DIR__);

// --- Сохраняем штрихкоды в формате CODE-128 ---
PecService::getPDF($registerResponse['cargos'][0]['cargoCode'], $deal_Id, __DIR__, true);

// --- Сохраняем штрихкоды в формате CODE-128 в виде короткого штрихкода ---
foreach ($registerResponse['cargos'][0]['positions'] as $i => $barcode) {    
    PecService::createBarCodePng($i, $barcode['barcode'], $deal_Id, __DIR__);
}

// --- Выводим сообщение об успешном выполнении ---
echo "Стоимость и регистрация отправки выполнены успешно. Лог сохранен в $logFile\n";