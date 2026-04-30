<?php

namespace rdx\PhpstanExtra\Php;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

final class ByErrorErrorFormatter implements ErrorFormatter {

	public function formatErrors(AnalysisResult $analysisResult, Output $output) : int {
		$errorErrors = [];
		foreach ($analysisResult->getFileSpecificErrors() as $error) {
			$error = $error->getIdentifier();
			$errorErrors[$error] ??= 0;
			$errorErrors[$error]++;
		}

		// $output->writeLineFormatted('');

		foreach ($errorErrors as $error => $errors) {
			$output->writeLineFormatted(sprintf("% 4d  %s", $errors, $error));
		}

		$output->writeLineFormatted('');
		$output->writeLineFormatted(sprintf("Total errors: %d, with %d identifiers", array_sum($errorErrors), count($errorErrors)));
		$output->writeLineFormatted('');

		return 0;
	}

}
