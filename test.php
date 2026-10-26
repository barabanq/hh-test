<?php

declare(strict_types=1);

class OrderProcessor
{
    private array $orders = [];
    private array $errors = [];
    private array $validStatuses = ['paid', 'pending', 'canceled'];

    public function loadFromJson(string $jsonString): void
    {
        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Критическая ошибка: Невозможно декодировать JSON. " . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new RuntimeException("Критическая ошибка: Ожидался массив объектов.");
        }

        foreach ($decoded as $index => $item) {
            if ($this->validateOrder($item, $index)) {
                $this->orders[] = $item;
            }
        }
    }

    private function validateOrder(mixed $item, int|string $index): bool
    {
        if (!is_array($item)) {
            $this->errors[] = "Элемент [$index]: не является объектом.";
            return false;
        }

        $requiredFields = ['id', 'customer', 'amount', 'status'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $item)) {
                $this->errors[] = "Заказ [$index]: отсутствует обязательное поле '{$field}'.";
                return false;
            }
        }

        if (!is_numeric($item['amount']) || $item['amount'] < 0) {
            $this->errors[] = "Заказ #{$item['id']}: некорректная сумма.";
            return false;
        }

        if (!in_array($item['status'], $this->validStatuses, true)) {
            $this->errors[] = "Заказ #{$item['id']}: неизвестный статус '{$item['status']}'.";
            return false;
        }

        return true;
    }

    public function getPaidOrders(): array
    {
        return array_filter($this->orders, fn($order) => $order['status'] === 'paid');
    }

    public function getTotalPaidAmount(): float|int
    {
        return array_reduce($this->getPaidOrders(), fn($sum, $order) => $sum + $order['amount'], 0);
    }

    public function getMostExpensivePaidOrder(): ?array
    {
        $paidOrders = $this->getPaidOrders();
        if (empty($paidOrders)) {
            return null;
        }

        usort($paidOrders, fn($a, $b) => $b['amount'] <=> $a['amount']);
        return $paidOrders[0];
    }

    public function getStatusCounts(): array
    {
        $counts = [];
        foreach ($this->orders as $order) {
            $status = $order['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return $counts;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

// ==========================================
// ИСПОЛЬЗОВАНИЕ
// ==========================================

$jsonInput = '[
{
"id": 101,
"customer": "Иван",
"amount": 1500,
"status": "paid"
},
{
"id": 102,
"customer": "Мария",
"amount": 2500,
"status": "pending"
},
{
"id": 103,
"customer": "Пётр",
"amount": 3000,
"status": "paid"
},
{
"id": 104,
"customer": "Анна",
"amount": 0,
"status": "paid"
}
]';

try {
    $processor = new OrderProcessor();
    $processor->loadFromJson($jsonInput);

    $paidCount = count($processor->getPaidOrders());
    $totalPaid = $processor->getTotalPaidAmount();
    $topOrder = $processor->getMostExpensivePaidOrder();
    
    echo "Количество оплаченных заказов: {$paidCount}\n";
    echo "Общая сумма оплаченных заказов: {$totalPaid}\n";
    
    if ($topOrder) {
        echo "Самый дорогой оплаченный заказ: № {$topOrder['id']} на сумму {$topOrder['amount']}\n";
    }

    echo "\n--- Вариант Б: Группировка по статусам ---\n";
    foreach ($processor->getStatusCounts() as $status => $count) {
        echo "- {$status}: {$count} шт.\n";
    }

    $errors = $processor->getErrors();
    if (!empty($errors)) {
        echo "\n--- Ошибки валидации ---\n";
        foreach ($errors as $error) {
            echo "{$error}\n";
        }
    }

} catch (Exception $e) {
    echo "Ошибка выполнения: " . $e->getMessage() . "\n";
};