<?php

namespace rdx\PhpstanExtra\Ires;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\File\RelativePathHelper;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<CollectedDataNode>
 */
final class ClassMethodUsageRule implements Rule {

	use CaresAboutClassMethods;

	public function __construct(
		protected RelativePathHelper $pathHelper,
		protected bool $enabled,
		protected string $ackCommand,
	) {}

	public function getNodeType() : string {
		return CollectedDataNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!$this->enabled) {
			return [];
		}

		$collectedMethods = $node->get(ClassMethodDefinitionsCollector::class);
		$methods = [];
		foreach ($collectedMethods as $filepath => $fileMethods) {
			foreach ($fileMethods as $methodName) {
				if ($methodName && $this->careAboutMethod($methodName)) {
					$file = $this->pathHelper->getRelativePath($filepath);
					$methods[$methodName] ??= [];
					$methods[$methodName][] = $file;
				}
			}
		}
		ksort($methods, SORT_STRING);

		$calls = $node->get(ClassMethodCallsCollector::class);
		$calls = array_unique(array_merge(...array_values($calls)));
		$calls = array_filter($calls);

		$t = microtime(true);
		$unusuedMethods = array_diff_key($methods, array_flip($calls));
		$numAcked = count($unusuedMethods);
		$unusuedMethods = array_filter($unusuedMethods, $this->noLiteralFoundEither(...), ARRAY_FILTER_USE_KEY);
		$t = microtime(true) - $t;
		if (!count($unusuedMethods)) {
			return [];
		}

		$unusuedMethodErrors = array_map(function(string $methodName, int $i, array $files) {
			return RuleErrorBuilder::message(sprintf('%s  x%d', $methodName, count($files)))
				->identifier('rudie.ClassMethodUsageRule')
				->line($i + 1)
				->build();
		}, array_keys($unusuedMethods), array_keys(array_keys($unusuedMethods)), $unusuedMethods);
		$unusuedMethodErrors[] = RuleErrorBuilder::message(sprintf('^ ack x%d took % 2.1f sec', $numAcked, $t))
			->nonIgnorable()
			->line(count($unusuedMethods) + 1)
			->build();

		return $unusuedMethodErrors;
	}

	private function noLiteralFoundEither(string $functionName) : bool {
		$results = trim(shell_exec(sprintf($this->ackCommand, $functionName)));

		$occurrences = explode("\n", $results);
// dump($occurrences);
		$occurrences = array_filter($occurrences, function(string $occurrence) use ($functionName) {
			return !str_contains($occurrence, "function $functionName(");
		});
// dump($occurrences);

		return count($occurrences) == 0;
	}

}
