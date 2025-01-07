<?php

namespace rdx\PhpstanExtra\Php;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\File\RelativePathHelper;

final class ByFileErrorFormatter implements ErrorFormatter {

	public function __construct(
		private RelativePathHelper $relativePathHelper,
	) {}

	public function formatErrors(AnalysisResult $analysisResult, Output $output) : int {
		$fileErrors = [];
		foreach ($analysisResult->getFileSpecificErrors() as $error) {
			$file = $this->relativePathHelper->getRelativePath($error->getFile());
			$fileErrors[$file] ??= 0;
			$fileErrors[$file]++;
		}

		// $output->writeLineFormatted('');

		foreach ($fileErrors as $file => $errors) {
			$output->writeLineFormatted(sprintf("% 4d  %s", $errors, $file));
		}

		$output->writeLineFormatted('');
		$output->writeLineFormatted(sprintf("Total errors: %d, in %d files", array_sum($fileErrors), count($fileErrors)));
		$output->writeLineFormatted('');

		return 0;
	}

}
