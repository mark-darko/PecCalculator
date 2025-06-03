<?php

use Picqer\Barcode\BarcodeGeneratorPNG;

class PecService {

    private static $apiUser, $apiKey;

    /**
     * Автормзация в API
     * @param string $login
     * @param string $apiKey
     */
    public static function setLogin(string $login, string $apiKey)
    {
        self::$apiUser = $login;
        self::$apiKey = $apiKey;
    }

    /**
     * Поиск склада по адресу
     * @param string $address
     * @return string|Exception
     */
    public static function findZoneByAddress(string $address): string|Exception
    {
        $url = "https://kabinet.pecom.ru/api/v1//branches/findzonebyaddress/";
        return self::apiRequest($url, ["address" => $address])['mainWarehouseId'];
    }

    /**
     * Получение лога стоимости доставки
     * @param array $data
     * @return array|Exception
     */
    public static function calculatePrice(array $data): array|Exception
    {
        $url = "https://kabinet.pecom.ru/api/v1/calculator/calculateprice/";
        return self::apiRequest($url, $data);   
    }

    /**
     * Получение лога регистрации отправки
     * @param array $data
     * @return array|Exception
     */
    public static function preregistrationSubmit(array $data): array|Exception
    {
        $url = "https://kabinet.pecom.ru/api/v1/preregistration/submit/";
        return self::apiRequest($url, $data);
    }

    /**
     * Создание баркода
     * @param int $placeNumber
     * @param string $barcodeText
     * @param int $deal_Id
     * @throws \Exception
     */
    public static function createBarCodePng(int $placeNumber, string $barcodeText, int $deal_Id, string $saveDir)
    {        
        try {
            $generator = new BarcodeGeneratorPNG();

            // Генерируем штрих-код в формате CODE-128
            $barcodeData = $generator->getBarcode($barcodeText, $generator::TYPE_CODE_128, 3, 60);
            
            // Создаем изображение из данных штрих-кода
            $barcodeImage = imagecreatefromstring($barcodeData);
            $barcodeWidth = imagesx($barcodeImage);
            $barcodeHeight = imagesy($barcodeImage);
            
            // Создаем новое изображение с белым фоном и местом для текста
            $finalWidth = $barcodeWidth + 40; // добавляем отступы по бокам
            $finalHeight = $barcodeHeight + 60; // добавляем место для текста снизу
            
            $finalImage = imagecreate($finalWidth, $finalHeight);
            
            // Устанавливаем цвета
            $white = imagecolorallocate($finalImage, 255, 255, 255);
            $black = imagecolorallocate($finalImage, 0, 0, 0);
            
            // Заливаем фон белым цветом
            imagefill($finalImage, 0, 0, $white);
            
            // Копируем штрих-код на финальное изображение с отступами
            imagecopy($finalImage, $barcodeImage, 20, 20, 0, 0, $barcodeWidth, $barcodeHeight);
            
            // Добавляем текст под штрих-кодом
            $fontSize = 3; // размер шрифта (1-5)
            $textWidth = imagefontwidth($fontSize) * strlen($barcodeText);
            $textX = (int)(($finalWidth - $textWidth) / 2); // центрируем текст и приводим к int
            $textY = $barcodeHeight + 30; // позиция под штрих-кодом
            
            imagestring($finalImage, $fontSize, $textX, $textY, $barcodeText, $black);
            
            // Создаем директорию, если она не существует
            if (!is_dir($saveDir . '/pec_files/barcodes_only')) {
                mkdir($saveDir . '/pec_files/barcodes_only', 0777, true);
            }

            // Сохраняем финальное изображение
            imagepng($finalImage, $saveDir . "/pec_files/barcodes_only/barcode_place_{$placeNumber}_deal_{$deal_Id}.png");
            
            // Освобождаем память
            imagedestroy($barcodeImage);
            imagedestroy($finalImage);            
        } catch (Exception $e) {
            echo "Ошибка при генерации штрих-кода для места {$placeNumber}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Получение PDF
     * @param int $cargoIndex
     * @param int $dealId
     * @param string $saveDir
     * @param bool $barcode
     * @throws \Exception
     */
    public static function getPDF(int $cargoIndex, int $dealId, string $saveDir, bool $barcode = false)
    {
        $url = "https://kabinet.pecom.ru/api/v1/order/print/";
        $response = self::apiRequest($url, ['cargoIndex' => $cargoIndex, 'type' => $barcode ? 'simple' : 'big']);
        
        // Декодируем base64 строку в бинарные данные PDF
        $pdfData = base64_decode($response);
        
        // Генерируем имя файла
        $filename = $barcode 
            ? $saveDir . "/pec_files/barcode_place_{$cargoIndex}_deal_{$dealId}.pdf"
            : $saveDir . "/pec_files/act_deal_{$dealId}.pdf";
        
        // Создаем директорию, если она не существует
        if (!is_dir($saveDir . '/pec_files')) {
            mkdir($saveDir . '/pec_files', 0777, true);
        }

        // Сохраняем PDF файл
        $result = file_put_contents($filename, $pdfData);
        
        if ($result === false) {
            throw new Exception("Не удалось сохранить PDF файл: {$filename}");
        }
    }

    /**
     * Отправка запроса в API
     * @param string $url
     * @param array $data
     * @throws \Exception
     * @return array|string|Exception
     */
    private static function apiRequest(string $url, array $data): array|string|Exception
    {
        try {
            $ch = curl_init($url);
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json;charset=utf-8',
            ]);
            curl_setopt($ch, CURLOPT_USERPWD, self::$apiUser . ":" . self::$apiKey);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                throw new Exception('Curl error: ' . curl_error($ch));
            }
            curl_close($ch);

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception("API returned HTTP code $httpCode, response: $response");
            }

            $decoded = json_decode($response, true);
            if ($decoded === null) {
                throw new Exception("Invalid JSON response from API: $response");
            }

            return $decoded;
        } catch (Exception $e) {
            die("Произошла ошибка при отправке на $url: " . $e->getMessage());
        }
    }
}