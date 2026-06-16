<x-mail::message>
{!! nl2br(e($body)) !!}

<x-mail::button :url="config('app.url')">
Открыть кабинет
</x-mail::button>

«Отклик» — AI-администратор для вашего бизнеса.
</x-mail::message>
