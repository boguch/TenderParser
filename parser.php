<?php
// URL для запроса
$url = 'https://tender.rusal.ru/Tenders/Load'; 
$params = [
    'sortAsc' => 'true', // Порядок сортировки
    'sortColumn' => 'RequestReceivingBeginDate', // Столбец для сортировки
    'ClassifiersFieldData.SiteSectionType' => 'bef4c544-ba45-49b9-8e91-85d9483ff2f6'
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST', 
        'content' => http_build_query($params),
    ],
]

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === FALSE) {
    die('Error');
}

// Обработка ответа
$data = json_decode($response, true);
$combinedArray = [];
$baseUrl = 'https://tender.rusal.ru';

// Объединение
foreach ($data['Rows'] as $row) {
    $combinedItem = [];
    $combinedItem['TenderNumber'] = $row['TenderNumber'] ?? null;
    $combinedItem['OrganizerName'] = $row['OrganizerName'] ?? null;
    $combinedItem['TenderViewUrl'] = isset($row['TenderViewUrl']) ? $baseUrl . $row['TenderViewUrl'] : null;
    $combinedArray[] = $combinedItem;
}

// Настройки базы данных
$host = 'MySQL-8.2'; // или ваш хост, если это не локальный сервер
$username = 'root'; // пользователь для MySQL
$password = ''; // пароль для пользователя
$database = 'TenderRusala'; // имя базы данных

// Подключение к MySQL
$mysqli = new mysqli($host, $username, $password, $database);
$conn = new mysqli($host, $username, $password, $database);



// Запрос для получения количества записей в таблице 'tender'
$quantity = $conn->query("SELECT COUNT(*) AS count FROM tender");
$row = $quantity->fetch_assoc();
$count = $row['count']; // Сохранение количества записей в переменную $count



$recordsToProcess = count($combinedArray); 
for ($i = $count; $i < $recordsToProcess; $i++) {
    if (!empty($combinedArray[$i]['TenderViewUrl'])) {
        $url = $combinedArray[$i]['TenderViewUrl'];

        // Инициализация cURL для запроса
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Пустое тело запроса
        $postFields = '';

        // Установка заголовков
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-content-requested-for: Tab",
            "x-csrf-token: 1Agm54KyLEX1dSOkBASK3PtlaA4nEdJR43jnPet1chpmELZutAqalZ7vDMoz_GwSvMglceWofhDUjMyhqTH9YJfENQyZCJGeGiWehIwM8hc1",
            "x-requested-with: XMLHttpRequest",
            "content-length: " . strlen($postFields)
        ]);

        // Установка тела запроса
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        // Выполнение запроса
        $response = curl_exec($ch);

        // Проверка на наличие ошибок
        if ($response === FALSE) {
            echo "cURL Error for record $i: " . curl_error($ch) . "\n";
            continue; // Переход к следующей записи
        }

        // Обработка ответа
        preg_match_all('/<div class="doc-filelist-wrapper">(.*?)<\/div>/s', $response, $div_matches);
        $docLinks = [];
        $docNames = [];

        foreach ($div_matches[0] as $html) {
            preg_match_all('/href="([^"]+)"/', $html, $matches);
            foreach ($matches[1] as $docUrl) {
                $full_url = $baseUrl . $docUrl;
                $docLinks[] = $full_url;
            }

            preg_match_all('/title="([^"]+)"/', $html, $matches);
            foreach ($matches[1] as $name) {
                $docNames[] = $name;
            }
        }

        // Получение даты
        preg_match('/(\d{2}\.\d{2}\.\d{4})/', $response, $dateMatches);
        $startDate = !empty($dateMatches) ? $dateMatches[0] : null;

        // Сохранение результатов в текущую запись массива
        $combinedArray[$i]['DocumentLinks'] = $docLinks;
        $combinedArray[$i]['DocumentNames'] = $docNames;
        $combinedArray[$i]['StartDate'] = $startDate;

        // Закрытие cURL
        curl_close($ch);
    }
}




// Проверка подключения
if ($mysqli->connect_error) {
    die('Ошибка подключения: ' . $mysqli->connect_error);
}

// Подготовка SQL-запроса
$stmt = $mysqli->prepare("CALL insert_tender(?, ?, ?, ?, ?, ?)");

// Проверка на наличие ошибок при подготовке
if (!$stmt) {
    die('Ошибка подготовки: ' . $mysqli->error);
}


$recordsToProcess = count($combinedArray); 
for ($i = $count; $i < $recordsToProcess; $i++) {
    // Проверка на существование ключей перед использованием
    $tenderNumber = isset($combinedArray[$i]['TenderNumber']) ? $combinedArray[$i]['TenderNumber'] : null;
    $organizerName = isset($combinedArray[$i]['OrganizerName']) ? $combinedArray[$i]['OrganizerName'] : null;
    $tenderViewUrl = isset($combinedArray[$i]['TenderViewUrl']) ? $combinedArray[$i]['TenderViewUrl'] : null;
    
    $documentLinks = isset($combinedArray[$i]['DocumentLinks']) ? json_encode($combinedArray[$i]['DocumentLinks']) : null; // Преобразуем массив в JSON
    $documentNames = isset($combinedArray[$i]['DocumentNames']) ? json_encode($combinedArray[$i]['DocumentNames']) : null; // Преобразуем массив в JSON
    $startDate = isset($combinedArray[$i]['StartDate']) ? $combinedArray[$i]['StartDate'] : null;

    // Проверка на null для StartDate, если это обязательное поле
    if ($startDate === null) {
        echo "Ошибка: StartDate не найден для тендера: $tenderNumber\n";
        continue; // Пропускаем эту запись, если StartDate отсутствует
    }

    // Привязка параметров
    $stmt->bind_param("ssssss", $tenderNumber, $organizerName, $tenderViewUrl,$startDate, $documentNames, $documentLinks);

    // Выполнение запроса
    if (!$stmt->execute()) {
        echo 'Ошибка выполнения: ' . $stmt->error . "\n";
    } else {
        echo "Запись добавлена для тендера: $tenderNumber\n"; // Успешное добавление
    }
}

// Закрытие подготовленного запроса и соединения
$stmt->close();
$mysqli->close();
$conn->close(); 
?>
