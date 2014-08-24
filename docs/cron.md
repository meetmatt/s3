[Оглавление](/README.md) | [Назад](php.md "Реализация на PHP")

# Cron скрипт

## Перенос файлов

Итак, у нас есть ключи для API, настроен прокси, из командной строки работает s3put и есть тонна файлов.

Первым шагом надо вручную натравить s3put на папку с нашими файлами.

Сначала запускаем без флага `delete`, ждем пока зальется три-четыре файла, проверяем, что они доступны по ссылке CloudFront'а, и только тогда запускаем скрипт с флагом --delete.

Вообще перед этим я бы на всякий случай сделал актуальный бэкап, но если яйца стальные, то можно сразу в бой - скрипт не станет удалять файл, если заливка обломалась.

Чтобы ускорить процесс, можно параллельно запустить несколько s3put. Они конечно будут конфликтовать друг с другом, потому что не оптимизированы для этого, но ничего страшного не произойдет. Опять же рекомендую сначала потренироваться "на кошках", перед тем как писать fork-bomb, который уложет диски и канал.

Советую замерить скорость работы скрипта на тестовой папке например в 100 Мб, в *nix для этого есть удобная команда time. Дальше общий объем данных делим на 100 и умножаем на время, получаем примерную общую продолжительность работы скрипта в однопоточном режиме. Дальше повторяем тест с двумя процессами, тремя и т.д.

## Добавление скрипта в крон

После того, как все существующие файлы перенесены на S3, надо добавить ту же самую команду, которая использовалась для перрвичного переноса файлов, в крон.

Пример скрипта:

```
<?php

exec("/usr/local/bin/s3put"
	 . " -a XXXXXXXXXXXXXXXX"
	 . " -s YYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY"
	 . " -b example"
	 . " --region='eu-west-1'"
	 . " --delete"
	 . " --grant='public-read'"
	 . " --prefix='/var/www/site/public/'"
	 . " /var/www/site/public/uploads/images"
	 . " > /var/www/site/cron/log/s3.`date '+%Y-%m-%d'`.log"
	 . " 2>&1", $output, $retval);

if ($retval > 0) {
	file_put_contents('/var/www/site/cron/log/s3.' . date('Y-m-d') . '.error.log', $output);
}
```

Перед использованием меняем ключи (параметры -s и -a), бакет (-b), и пути.

Чтобы добавить задачу в крон, выполняем `crontab -e`.
Добавляем строчку `* * * * * php /var/www/site/cron/script/s3.php` и сохраняем файл.

У крона есть ограничение на минимальный период - 1 минута, иногда надо меньше, допустим 10 секунд. Обычно чем чаще вызывается скрипт, тем меньше у него работы. Допустим мы хотим, чтобы скрипт вызывался 3 раза в минуту, т.е. раз в 20 секунд, тогда добавим три вызова скрипта в крон с разницей в 20 секунд:

```
* * * * * php /var/www/site/cron/script/s3.php
* * * * * sleep 20; php /var/www/site/cron/script/s3.php
* * * * * sleep 30; php /var/www/site/cron/script/s3.php
```

Команда sleep просто приостанавливает работу на N секунд.

## ВСЕ!

Теперь файлы автоматически заливаются и храняться на S3 и доступны по ссылке на CloudFront.

Чтобы вместо некрасивого поддомена вида ansjkq70989ndxdjn.cloudfront.com использовать свой типа cdn.example.com, надо в консоли управления CloudFront добавить свой клевый домен в дистрибуцию (об этом упоминалось в первой главе) и добавить своему домену алиас указывающий на CloudFront.


[Оглавление](/README.md) | [Назад](php.md "Реализация на PHP") 