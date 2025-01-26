<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeTraverser;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

// Alternative: https://github.com/Slamdunk/phpstan-extensions/blob/v1.2.0/lib/UnusedVariableRule.php

/**
 * @implements Rule<FunctionLike>
 */
final class UnusedVariablesRule implements Rule {

	private const IGNORE_VARS = [
		'_GET',
		'_POST',
		'_REQUEST',
		'_SERVER',
		'_SESSION',
	];

	public function __construct(
		protected bool $enabled,
		protected bool $debug = false,
	) {}

	public function getNodeType() : string {
		return FunctionLike::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		// ArrowFunction is not its own scope
		if ($node instanceof ArrowFunction) {
			return [];
		}

		if ($this->debug) {
			printf("\tLine % 4d  %s\n", $node->getStartLine(), get_class($node));
// if (!in_array($node->getStartLine(), [191])) return [];
		}

		$ignoreVarNames = [];
		if ($node instanceof Closure) {
			foreach ($node->uses as $var) {
				if ($var->byRef && is_string($var->var->name)) {
					$ignoreVarNames[] = $var->var->name;
				}
			}
		}
		foreach ($node->getParams() as $var) {
			if ($var->byRef && is_string($var->var->name)) {
				$ignoreVarNames[] = $var->var->name;
			}
		}

		$traverser = new NodeTraverser();
		$traverser->addVisitor($visitor = new UnusedVariablesNodeVisitor($this->debug));
		$traverser->traverse($node->getStmts() ?? []);

		$ignoreVarNames = array_merge($ignoreVarNames, $visitor->getIgnoreVarNames());

		if ($this->debug) {
			echo "\t\t" . implode("\n\t\t", $visitor->getEncounterDebugs()), "\n";
		}

		$unusedVariables = [];
		foreach ($visitor->getEncounters() as [$type, $varName, $line]) {
			if (in_array($varName, $ignoreVarNames)) {
				continue;
			}

			if ($type == UnusedVariablesNodeVisitor::DEFINE) {
				$unusedVariables[$varName] ??= $line;
			}

			if ($type == UnusedVariablesNodeVisitor::USE) {
				unset($unusedVariables[$varName]);
			}

			if ($type == UnusedVariablesNodeVisitor::USE_ALL) {
				$unusedVariables = [];
			}
		}

		$messages = [];
		foreach ($unusedVariables as $varName => $line) {
			if (in_array($varName, self::IGNORE_VARS)) continue;

			$messages[] = RuleErrorBuilder::message(sprintf('Variable $%s seems to be unused.', $varName))
				->line($line)
				->identifier('rudie.UnusedVariablesRule')
				->build();
		}

		return $messages;
	}

}
