[Оглавление](/README.md) | [Назад](tools.md "Инструменты") | [Далее](cron.md "Cron скрипт")

# Реализация на PHP

## Amazon S3 PHP Class

Для работы с API Amazon S3 будем использовать простой класс, скачать [здесь](/tools/S3.php).

Чтобы абстрагировать работу с API до уровня работы с файлами, реализуем [класс File](/tools/File.php):

```
<?php

class File {

	protected static $instance;

	/**
	 * Use get instance instead
	 */
	protected function __construct()
	{
	}
	
	/**
	 * @return S3
	 */
	public static function getInstance()
	{
		if ( ! isset(self::$instance)) {
			self::initInstance();
		}

		return self::$instance;
	}

	protected static function initInstance()
	{
		self::$instance = new S3(AWS_S3_KEY, AWS_S3_SECRET);
	}

	protected static function isEnabled()
	{
		return is_defined('AWS_S3_ENABLED') && AWS_S3_ENABLED;
	}

	public static function download($file, $saveTo = null)
	{
		if ( ! self::isEnabled()) {
			return false;
		}

		$s3 = self::getInstance();

		if (is_null($saveTo)) {
			// recursively create directory structure if it doesn't exist
			$path = dirname($file);
			if ( ! is_dir($path)) {
				mkdir($path, 0777, true);
			}
			$saveTo = $file;
		}

		if ($s3->getObject(AWS_S3_BUCKET, $file, $saveTo)) {
			return $saveTo;
		}

		return false;
	}

	public static function upload($file) {
		if ( ! self::isEnabled()) {
			return false;
		}

		$s3 = self::getInstance();

		$filepath = DOCUMENT_ROOT . '/' . $file;

		if ($s3->putObjectFile($filepath, AWS_S3_BUCKET, $file, S3::ACL_PUBLIC_READ)) {
			return true;
		}

		return false;
	}

	public static function exists($file) {
		if ( ! self::isEnabled()) {
			return false;
		}

		$s3 = self::getInstance();

		return $s3->getObjectInfo(AWS_S3_BUCKET, $file, false);
	}

	public static function delete($file) {
		if ( ! self::isEnabled()) {
			return false;
		}

		$s3 = self::getInstance();

		return $s3->deleteObject(AWS_S3_BUCKET, $file);
	}

}

```

### Пример использования

```
<?php

define('AWS_S3_ENABLED', true);
define('AWS_S3_KEY', '');
define('AWS_S3_SECRET', '');
define('AWS_S3_BUCKET', 'example');
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

require_once 'S3.php';
require_once 'File.php';

$file = 'uploads/images/photo.jpg';

echo File::exists($file)
	? 'File ' . $file . ' exists in bucket ' . AWS_S3_BUCKET . PHP_EOL
	: 'File ' . $file . ' does not exist in bucket ' . AWS_S3_BUCKET . PHP_EOL;

echo File::download($file)
	? 'Downloaded ' . $file . ' from bucket ' . AWS_S3_BUCKET . PHP_EOL
	: 'Failed to download ' . $file . ' from bucket ' . AWS_S3_BUCKET . PHP_EOL;

echo File::upload($file)
	? 'Uploaded file ' . DOCUMENT_ROOT . '/' . $file . ' to bucket ' . AWS_S3_BUCKET . PHP_EOL
	: 'Failed to upload file ' . DOCUMENT_ROOT . '/' . $file . ' to bucket ' . AWS_S3_BUCKET . PHP_EOL;

echo File::remove($file)
	? 'Removed file ' . $file . ' from bucket ' . AWS_S3_BUCKET . PHP_EOL
	: 'Failed to remove file ' . $file . ' from bucket ' . AWS_S3_BUCKET . PHP_EOL;

```


## Адаптация имеющегося кода для работы с файлами

Надо изменить все участки кода, которые:

### проверяют существование файла

Старый код:

```
if (file_exists($file)) {
	//...
}
```

Новый код:

```
if (file_exists($file) && File::exists($file)) {
	//...
}
```
Как вариант можно добавить проверку на существование файла в сам метод File::exists.
Тогда можно будет просто изменить все вызовы file_exists на File::exists

### копируют и редактируют существующий файл

Старый код:

```
copy($file, $new_file);
// изменяем файл $new_file
```

Новый код:

```
// скачиваем файл с S3
if (File::exists($file)) {
	File::download($file, $new_file);
} elseif (file_exists($file)) {
	copy($file, $new_file);
}

// изменяем файл $new_file
```

**ОЧЕНЬ ВАЖНО!**

Везде, где в коде изменяются файлы без создания копии, например, если пользователь изменил миниатюру аватарки, необходимо сохранить измененный файл под **НОВЫМ ИМЕНЕМ**! Иначе, файл изменится на S3, но в кеше CloudFront останется **СТАРАЯ ВЕРСИЯ**, сбросить кеш конкретного файла на всех серверах CloudFront'а естественно возможно, но это не моментальная операция, имеет сильные ограничения и стоит денег за API вызов.

Естественно, что после сохранения измененного файла под новым именем, старый необходимо удалить. Скорее всего так же потребуется обновить запись в базе данных, указывающую на старый файл. Короче здесь работы больше всего, общий алгоритм будет выглядить так:

Старый код:

```
// открывает файл 
// изменяем файл
// сохраняем его
```

Новый код:

```
// генерируем новое имя $new_file
$new_file = md5(time());

// скачиваем оригинальный файл в $new_file
File::download($file, $new_file);

// локально изменяем и сохраняем $new_file

// удаляем старый файл
File::remove($file);
```

### удаляют файл

Старый код:

```
unlink($file);
```

Новый код:

```
if (file_exists($file)) {
	unlink($file);
}
if (File::exists($file)) {
	File::remove($file);
}
```

### создают и сохраняют файл

Никаких правок не требуется, все происходит локально, потому что заливка файлов на S3 и удаление локальной копии происходит [по крону с помощью s3put](cron.md).


[Оглавление](/README.md) | [Назад](tools.md "Инструменты") | [Далее](cron.md "Cron скрипт")