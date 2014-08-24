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