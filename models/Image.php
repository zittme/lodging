<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 업소·객실 사진 — 코어 file 모듈에 위임한다.
 *
 * upload_target_srl 에 property_srl 또는 room_type_srl 을, upload_target_type 에
 * 'lodging' 을 써서 다른 모듈의 첨부와 구분한다. 이미지는 직접 접근 경로로 노출된다.
 */
class Image
{
	public const TARGET_TYPE = 'lodging';

	/**
	 * 대상의 사진 목록. 각 항목에 url 을 붙여 돌려준다.
	 *
	 * @param int $target_srl property_srl 또는 room_type_srl
	 * @return array
	 */
	public static function getList(int $target_srl): array
	{
		if ($target_srl <= 0)
		{
			return [];
		}

		$files = \FileModel::getFiles($target_srl, [], 'file_srl', true, self::TARGET_TYPE);
		$list = [];
		foreach (is_array($files) ? $files : [] as $file)
		{
			$file->url = self::urlOf($file);
			if ($file->url !== '')
			{
				$list[] = $file;
			}
		}

		return $list;
	}

	/**
	 * 대표 사진 주소. 없으면 빈 문자열.
	 *
	 * @param int $target_srl
	 * @return string
	 */
	public static function firstUrl(int $target_srl): string
	{
		$list = self::getList($target_srl);
		return count($list) ? $list[0]->url : '';
	}

	/**
	 * 업로드된 파일들을 저장한다. 이미지 확장자만 받는다.
	 *
	 * @param string $field $_FILES 키
	 * @param int $module_srl
	 * @param int $target_srl
	 * @return int 저장 수
	 */
	public static function saveUploaded(string $field, int $module_srl, int $target_srl): int
	{
		$uploaded = $_FILES[$field] ?? null;
		if (!is_array($uploaded) || !isset($uploaded['tmp_name']))
		{
			return 0;
		}

		$names = is_array($uploaded['name']) ? $uploaded['name'] : [$uploaded['name']];
		$tmps = is_array($uploaded['tmp_name']) ? $uploaded['tmp_name'] : [$uploaded['tmp_name']];
		$errors = is_array($uploaded['error']) ? $uploaded['error'] : [$uploaded['error']];

		$oFileController = \FileController::getInstance();
		$saved = 0;
		$file_srls = [];

		foreach ($names as $index => $name)
		{
			$tmp_name = $tmps[$index] ?? '';
			if ($name === '' || $tmp_name === '' || ($errors[$index] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK)
			{
				continue;
			}
			if (!is_uploaded_file($tmp_name))
			{
				continue;
			}
			$extension = strtolower((string)array_last(explode('.', $name)));
			if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
			{
				continue;
			}

			$output = $oFileController->insertFile([
				'name' => $name,
				'tmp_name' => $tmp_name,
			], $module_srl, $target_srl, 0, true);

			if ($output->toBool())
			{
				$saved++;
				$file_srls[] = (int)$output->get('file_srl');
			}
		}

		if ($saved > 0)
		{
			$oFileController->updateTargetType($file_srls, self::TARGET_TYPE);
			$oFileController->setFilesValid($target_srl, self::TARGET_TYPE);
		}

		return $saved;
	}

	/**
	 * 사진 한 장을 지운다. 이 모듈의 사진인지 대조한 뒤 지운다.
	 *
	 * @param int $file_srl
	 * @param int $target_srl
	 * @return bool
	 */
	public static function delete(int $file_srl, int $target_srl): bool
	{
		foreach (self::getList($target_srl) as $file)
		{
			if ((int)$file->file_srl === $file_srl)
			{
				\FileController::getInstance()->deleteFile($file_srl);
				return true;
			}
		}

		return false;
	}

	/**
	 * 이미지의 웹 주소.
	 *
	 * @param object $file
	 * @return string
	 */
	protected static function urlOf(object $file): string
	{
		if (($file->direct_download ?? '') === 'Y' && !empty($file->uploaded_filename))
		{
			return \RX_BASEURL . ltrim((string)$file->uploaded_filename, './');
		}

		return (string)($file->download_url ?? '');
	}
}
