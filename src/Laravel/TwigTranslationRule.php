<?php

namespace rdx\PhpstanExtra\Laravel;

use Generator;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @implements Rule<CollectedDataNode>
 */
class TwigTranslationRule implements Rule {

	public function __construct(
		private TranslationValidator $validator,
		private int $strictness,
	) {}

	public function getNodeType() : string {
		return CollectedDataNode::class;
	}

	/**
	 * @param CollectedDataNode $node
	 */
	public function processNode(Node $node, Scope $scope) : array {
		if ($this->strictness == 0) {
			return [];
		}

		$viewsPath = resource_path('views');
		if (!is_dir($viewsPath)) {
			return [];
		}

		$errors = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));

		/** @var SplFileInfo $file */
		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'twig') {
				continue;
			}

			$filePath = $file->getPathname();
// echo "$filePath\n";
			$twigCode = file_get_contents($filePath);

			if ($twigCode === false) {
				continue;
			}

			foreach ($this->getCalls($twigCode) as $match) {
				if ($match['type'] == 'expr') {
					$value = $match['expr'];
				}
				else {
					if ($this->validator->isValidKey($match['key'])) continue;
					$value = $match['key'];
				}

				$line = substr_count(substr($twigCode, 0, $match['offset']), "\n") + 1;

				$errors[] = RuleErrorBuilder::message($this->validator->getMessage($match['type'], $value))
					->file($filePath)
					->line($line)
					->identifier('rudie.TwigTranslationRule')
					->build();
			}

// return $errors;

		}

		return $errors;
	}

	/**
	 * @return Generator<(array{type: 'key', key: string, offset: int}|array{type: 'expr', expr: string, offset: int})>
	 */
	protected function getCalls(string $twigCode) : Generator {
		if (!preg_match_all('#\b(?:trans|trans_choice)\(([^{\n]+)#', $twigCode, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
			return;
		}

		foreach ($matches as $match) {
			$expr = $match[1][0];
			$offset = $match[1][1];
// echo "  $expr\n";
			if ($expr[0] == "'") {
				$key = explode("'", substr($expr, 1))[0];
				yield [
					'type' => 'key',
					'key' => $key,
					'offset' => $offset,
				];
				continue;
			}

			if (str_contains($expr, ' ? ')) {
				$expr = explode(' ? ', $expr, 2)[1];
// echo "    $expr\n";
			}

			if (!preg_match_all("#'([a-z0-9\._]+)'#i", $expr, $matches2)) {
				// echo "  UNKNOWN KEY IN EXPRESSION: $expr\n";
				if ($this->strictness > 1) {
					yield [
						'type' => 'expr',
						'expr' => $expr,
						'offset' => $offset,
					];
				}
				continue;
			}

			foreach ($matches2[1] as $key) {
				yield [
					'type' => 'key',
					'key' => $key,
					'offset' => $offset,
				];
			}
		}

		return;
	}

}
