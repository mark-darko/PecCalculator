<?php

use Picqer\Barcode\BarcodeGeneratorPNG;

class PecService {
    private static $apiUser, $apiKey;

    /**
     * Автормзация в API
     * @param string $login
     * @param string $apiKey
     */
    public static function setLogin(string $login, string $apiKey) {
        self::$apiUser = $login;
        self::$apiKey = $apiKey;
    }

    /**
     * Поиск склада по адресу
     * @param string $address
     * @return string|Exception
     */
    public static function findZoneByAddress(string $address): string|Exception {

        $url = "https://kabinet.pecom.ru/api/v1//branches/findzonebyaddress/";
        return self::apiRequest($url, ["address" => $address])['mainWarehouseId'];

    }

    /**
     * Получение лога стоимости доставки
     * @param array $data
     * @return array|Exception
     */
    public static function calculatePrice(array $data): array|Exception {

        $url = "https://kabinet.pecom.ru/api/v1/calculator/calculateprice/";
        return self::apiRequest($url, $data);
        
    }

    /**
     * Получение лога регистрации отправки
     * @param array $data
     * @return array|Exception
     */
    public static function preregistrationSubmit(array $data): array|Exception {
        $url = "https://kabinet.pecom.ru/api/v1/preregistration/submit/";
        return self::apiRequest($url, $data);
    }

    /**
     * Создание баркода
     * @param int $placeNumber
     * @param string $barcodeText
     * @param int $deal_Id
     * @throws \Exception
     * @return void
     */
    public static function createBarCodePng(int $placeNumber, string $barcodeText, int $deal_Id) {        
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
            
            // Сохраняем финальное изображение
            imagepng($finalImage, dirname(__DIR__) . "/pec_files/barcode_place_{$placeNumber}_deal_{$deal_Id}.png");
            
            // Освобождаем память
            imagedestroy($barcodeImage);
            imagedestroy($finalImage);
            
            echo "Штрих-код для места {$placeNumber} сохранен: {$barcodeText}\n";
            
        } catch (Exception $e) {
            echo "Ошибка при генерации штрих-кода для места {$placeNumber}: " . $e->getMessage() . "\n";
        }

    }

    /**
     * Отправка запроса в API
     * @param string $url
     * @param array $data
     * @throws \Exception
     * @return array|Exception
     */
    private static function apiRequest(string $url, array $data): array|Exception {

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