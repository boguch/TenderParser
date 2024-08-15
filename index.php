<?php
require_once ('parser.php');

// Конфигурация соединения с базой данных
$host = 'MySQL-8.2'; // Укажите ваш хост
$dbname = 'TenderRusala'; // Укажите имя вашей базы данных
$username = 'root'; // Укажите ваше имя пользователя
$password = ''; // Укажите ваш пароль

// Создаем подключение
$conn = new mysqli($host, $username, $password, $dbname);

// Проверяем подключение
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Параметры пагинации
$limit = 100; // Количество записей на странице
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; 
$offset = ($page - 1) * $limit;

// Получение общего количества записей
$totalResult = $conn->query("SELECT COUNT(*) AS count FROM tender");
$totalRows = $totalResult->fetch_assoc()['count'];
$totalPages = ceil($totalRows / $limit);

// Получение данных из базы данных для текущей страницы
$result = $conn->query("
    SELECT id,TenderNumber, OrganizerName, TenderViewUrl, StartDate, DocumentNames, DocumentLinks 
    FROM tender 
    ORDER BY id
    LIMIT $limit OFFSET $offset
");
if ($result) {
    // Массив для хранения обработанных данных
    $processedRows = [];

    // Обрабатываем каждую строку результата
    while ($row = $result->fetch_assoc()) {
        // Удаляем 'amp;' из DocumentLinks
        $row['DocumentLinks'] = str_replace('amp;', '', $row['DocumentLinks']);
        // Добавляем обработанную строку в массив
        $processedRows[] = $row;
    }

    // Теперь $processedRows содержит данные с очищенным столбцом DocumentLinks
    // Вы можете продолжать свою логику обработки данных

    echo "Данные успешно загружены и обработаны.";
} else {
    echo "Ошибка при выполнении запроса: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TenderParser</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Остальные стили... */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            background-color: #fff;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a {
            margin: 0 3px; /* Уменьшенный отступ */
            text-decoration: none;
            padding: 5px 10px; /* Уменьшенный размер кнопок */
            border: 1px solid #007BFF;
            border-radius: 3px; /* Меньшее закругление углов */
            color: #007BFF;
            font-size: 14px; /* Уменьшенный размер шрифта */
            transition: background-color 0.3s, color 0.3s;
        }
        .pagination a.active {
            background-color: #007BFF;
            color: white;
        }
        .pagination a:hover {
            background-color: #0056b3;
            color: white;
        }
        .card {
            margin: 10px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>

<h1>Данные из базы данных</h1>

<table>
    <thead>
        <tr>
            <th>id</th>
            <th>Tender Number</th>
            <th>Organizer Name</th>
            <th>Tender View URL</th>
            <th>Start Date</th>
            <th>Document Names</th>
            <th>Document Links</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($processedRows as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['TenderNumber']); ?></td>
            <td><?php echo htmlspecialchars($row['OrganizerName']); ?></td>
            <td>
                <?php
                $TenderLinks = $row['TenderViewUrl'];
                echo '<a href="' . htmlspecialchars($TenderLinks) . '" target="_blank">' . htmlspecialchars($TenderLinks) . '</a><br>';
                ?>
            </td>
            <td><?php echo htmlspecialchars($row['StartDate']); ?></td>
            <td>
                <?php
                $documentNames = json_decode($row['DocumentNames'], true);
                if (is_array($documentNames)) {
                    echo htmlspecialchars(implode(', ', $documentNames));
                } else {
                    echo 'Нет данных';
                }
                ?>
            </td>
            <td>
                <?php
                $documentLinks = json_decode($row['DocumentLinks'], true);
                if (is_array($documentLinks)) {
                    foreach ($documentLinks as $link) {
                        echo '<a href="' . htmlspecialchars($link) . '" target="_blank">' . htmlspecialchars($link) . '</a><br>';
                    }
                } else {
                    echo 'Нет данных';
                }
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
</table>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="<?php echo ($i === $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>

<?php
$conn->close(); // Закрываем соединение
?>
</body>
</html>