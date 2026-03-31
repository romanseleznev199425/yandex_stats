<?php
// index.php - Статистика опроса из Яндекс.Форм
$token = 'y0__xCFr9YzGNrZPyDL1v72FvDwxUuWa405FeXZgqF0XhKRKIYD';        // OAuth-токен
$formId = '69c69f586d2d7305234b56ae';        // ID формы

header('Content-Type: text/html; charset=utf-8');

// Функция для получения ответов из API Яндекс.Форм
function getFormAnswers($token, $formId, $nextId = null) {
    $url = "https://api.forms.yandex.net/v1/surveys/{$formId}/answers";
    if ($nextId) {
        $url .= "?id={$nextId}";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $token]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    } else {
        echo "Ошибка при запросе к API: HTTP {$httpCode}<br>";
        echo htmlspecialchars($response);
        return null;
    }
}

// Получаем все ответы
$allAnswers = [];
$nextId = null;

do {
    $data = getFormAnswers($token, $formId, $nextId);

    if (!$data || !isset($data['answers'])) {
        break;
    }

    // Добавляем ответы в общий массив
    $allAnswers = array_merge($allAnswers, $data['answers']);

    // Проверяем наличие следующей страницы
    if (isset($data['next']) && isset($data['next']['next_url'])) {
        // Извлекаем id из next_url
        $nextUrl = $data['next']['next_url'];
        $query = parse_url($nextUrl, PHP_URL_QUERY); // Получаем часть после ?
        parse_str($query, $queryParams); // Разбираем параметры
        $nextId = $queryParams['id'] ?? null;
    } else {
        $nextId = null;
    }

} while ($nextId);

// Собираем статистику по вопросам
$questionsStats = [];
$totalAnswers = count($allAnswers);

// Инициализируем структуру для каждого вопроса
if ($totalAnswers > 0) {
    foreach ($allAnswers[0]['data'] as $qIndex => $questionData) {
        $questionsStats[$qIndex] = [];
    }
}

// Считаем ответы
foreach ($allAnswers as $answer) {
    foreach ($answer['data'] as $qIndex => $question) {
        if (isset($question['value']) && is_array($question['value'])) {
            $value = implode(', ', $question['value']);
            if (!isset($questionsStats[$qIndex][$value])) {
                $questionsStats[$qIndex][$value] = 0;
            }
            $questionsStats[$qIndex][$value]++;
        }
    }
}

// Получаем тексты вопросов из API (один раз)
$columns = [];
if ($totalAnswers > 0) {
    $firstData = getFormAnswers($token, $formId);
    if ($firstData && isset($firstData['columns'])) {
        $columns = $firstData['columns'];
    }
}
?>

<style>
    /* Основные стили для контейнера статистики */
.statistics-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Заголовок статистики */
.statistics-header {
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.total-answers {
    margin: 0;
    font-size: 24px;
    color: white;
    text-align: center;
}

.total-answers strong {
    font-weight: 600;
}

/* Карточка вопроса */
.statistics-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 30px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.statistics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

/* Заголовок вопроса */
.question-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.question-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    line-height: 1.4;
    flex: 1;
}

.question-number {
    font-size: 14px;
    color: #999;
    font-weight: 500;
    background: #f5f5f5;
    padding: 4px 12px;
    border-radius: 20px;
    margin-left: 15px;
}

/* Контейнер для ответов */
.answers-statistics {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Элемент статистики (один ответ) */
.statistic-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Информация об ответе (текст и цифры) */
.statistic-info {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 10px;
}

.answer-label {
    font-size: 15px;
    color: #555;
    font-weight: 500;
    word-break: break-word;
    flex: 1;
}

.statistic-numbers {
    font-size: 13px;
    color: #666;
    font-weight: 500;
    white-space: nowrap;
}

/* Контейнер для прогресс-бара */
.progress-bar-container {
    background-color: #f0f0f0;
    border-radius: 8px;
    height: 8px;
    overflow: hidden;
    position: relative;
}

/* Сам прогресс-бар */
.progress-bar {
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    height: 100%;
    border-radius: 8px;
    transition: width 0.6s ease;
    position: relative;
}

/* Анимация для загрузки прогресс-баров */
@keyframes slideIn {
    from {
        width: 0;
    }
    to {
        width: var(--target-width);
    }
}

.progress-bar {
    animation: slideIn 0.8s ease-out;
}

/* Стили для пустых ответов или нулевых значений */
.statistic-item:has(.progress-bar[style*="width: 0%"]) .progress-bar {
    background: #e0e0e0;
}

/* Адаптивность для мобильных устройств */
@media (max-width: 768px) {
    .statistics-container {
        padding: 15px;
    }
    
    .statistics-card {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .question-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .question-number {
        align-self: flex-start;
        margin-left: 0;
    }
    
    .question-title {
        font-size: 16px;
    }
    
    .statistic-info {
        flex-direction: column;
        gap: 5px;
    }
    
    .statistic-numbers {
        white-space: normal;
        font-size: 12px;
    }
    
    .answer-label {
        font-size: 14px;
    }
    
    .statistics-header {
        padding: 15px;
    }
    
    .total-answers {
        font-size: 20px;
    }
}

/* Стили для печати */
@media print {
    .statistics-container {
        padding: 0;
    }
    
    .statistics-card {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    .progress-bar {
        background: #ccc;
        print-color-adjust: exact;
    }
    
    .statistics-header {
        background: #f5f5f5;
        print-color-adjust: exact;
    }
    
    .total-answers {
        color: #333;
    }
}

/* Стили для темной темы (опционально) */
@media (prefers-color-scheme: dark) {
    .statistics-card {
        background: #1e1e1e;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    .question-title {
        color: #e0e0e0;
    }
    
    .answer-label {
        color: #ccc;
    }
    
    .statistic-numbers {
        color: #aaa;
    }
    
    .question-number {
        background: #333;
        color: #ccc;
    }
    
    .progress-bar-container {
        background-color: #333;
    }
    
    .question-header {
        border-bottom-color: #333;
    }
}

/* Опциональные стили для выделения максимального значения */
.statistic-item:first-child .progress-bar {
    background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
}

/* Стили для значений с высоким процентом */
.progress-bar[style*="width: 100%"] {
    background: linear-gradient(90deg, #4caf50 0%, #45a049 100%);
}

.progress-bar[style*="width: 0%"] {
    background: #e0e0e0;
}
</style>



<div class='statistics-container'>
    <div class='statistics-header'>
        <p class='total-answers'><strong>Всего ответов: <?php echo $totalAnswers ?></strong></p>
    </div>
    <?php foreach ($questionsStats as $qIndex => $answers) {
        $questionText = htmlspecialchars($columns[$qIndex]['text'] ?? "Вопрос {$qIndex}"); ?>
        <div class='statistics-card'>
            <div class='question-header'>
                <h3 class='question-title'><?php echo $questionText ?></h3>
                <span class='question-number'>Вопрос <?php echo $qIndex + 1 ?></span>
            </div>
            
            <div class='answers-statistics'>
                <?php foreach ($answers as $answerText => $count): ?>
                    <?php $percentage = round(($count / $totalAnswers) * 100, 1); ?>
                    <?php $answerText = htmlspecialchars($answerText); ?>
                    <div class='statistic-item'>
                        <div class='statistic-info'>
                            <span class='answer-label'><?php echo $answerText ?></span>
                            <span class='statistic-numbers'><?php echo $count ?> чел. (<?php echo $percentage ?>%)</span>
                        </div>
                        <div class='progress-bar-container'>
                            <div class='progress-bar' style='width: <?php echo $percentage ?>%;'></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php } ?>
</div>