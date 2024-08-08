<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Регистрируем наш фильтр с именем 'format_phone'
            new TwigFilter('format_phone', [$this, 'formatPhoneNumber']),
        ];
    }

    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Убедитесь, что номер телефона содержит только цифры
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Пример маски для российского номера телефона: +7 (999) 999-99-99
        if (strlen($phoneNumber) == 11) {
            return '+7 (' . substr($phoneNumber, 1, 3) . ') ' . substr($phoneNumber, 4, 3) . '-' . substr($phoneNumber, 7, 2) . '-' . substr($phoneNumber, 9, 2);
        }

        // Если номер телефона не соответствует маске, возвращаем как есть
        return $phoneNumber;
    }
}