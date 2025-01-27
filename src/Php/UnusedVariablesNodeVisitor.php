<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class UnusedVariablesNodeVisitor extends NodeVisitorAbstract {

	public const DEFINE = 'define';
	public const USE = 'use';
	public const USE_ALL = 'use_all';

	private int $depth = 0;
	private int $loopDepth = 0;
	/** @var list<Expr> */
	private array $skipVars = [];
	private int $line;
	/** @var list<array{string, string, int}> */
	private array $encounters = [];
	/** @var list<string> */
	private array $ignoreVarNames = [];

	public function __construct(
		private bool $debug,
	) {}

	public function enterNode(Node $node) { // @phpstan-ignore return.unusedType
		$this->depth++;
		$this->line = $node->getStartLine();

		// echo "\t\t - ", get_class($node), " (",  $this->line, ") - \n";

		if (in_array($node, $this->skipVars)) {
			return null;
		}

		// Don't go into FunctionLike because they are their own scope
		if ($node instanceof FunctionLike) {
			// Except ArrowFunction, that doesn't have its own scope
			if ($node instanceof ArrowFunction) {
				return null;
			}

			if ($node instanceof Closure) {
				foreach ($node->uses as $useVar) {
					if (is_string($useVar->var->name)) {
						$varName = $useVar->var->name;
						$this->addEncounter(self::USE, $varName);
					}
				}
			}

			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if ($node instanceof FuncCall && $node->name instanceof Name) {
// dump($node->name, $node->name->toLowerString());
			if ($node->name->toLowerString() === 'compact') {
				$varNames = $this->getCompactVarNames($node);
				foreach ($varNames as $varName) {
					$this->addEncounter(self::USE, $varName);
				}
			}

			if ($node->name->toLowerString() === 'get_defined_vars') {
				$this->addEncounter(self::USE_ALL, 'all');
			}
			return null;
		}

		if ($node instanceof StaticVar) {
			if ($varName = $this->getVarName($node->var)) {
				$this->ignoreVarNames[] = $varName;
			}
			return null;
		}

		if ($node instanceof Global_) {
			foreach ($node->vars as $var) {
				if ($varName = $this->getVarName($var)) {
					$this->ignoreVarNames[] = $varName;
				}
			}
			return null;
		}

		if ($node instanceof For_ || $node instanceof While_) {
			$this->loopDepth++;
			return null;
		}

		if ($node instanceof Foreach_) {
			// Because of loopDepth, add the source var explicitly
			$varName = $this->getVarName($node->expr);
			$this->addEncounter(self::USE, $varName);

			$this->loopDepth++;

			$this->skipVars[] = $node->keyVar;
			$this->skipVars[] = $node->valueVar;
			return null;
		}

		if ($node instanceof Assign) {
			$this->skipVars[] = $node->var;
			return null;
		}

		if ($node instanceof Variable) {
			$varName = $this->getVarName($node);
			$this->addEncounter(self::USE, $varName);
		}

		return null;
	}

	public function leaveNode(Node $node) {
		$this->depth--;
		$this->line = $node->getStartLine();

		if ($node instanceof Assign) {
			$this->skipVars[] = $node->var;
			$varName = $this->getVarName($node->var);
			$this->addEncounter(self::DEFINE, $varName);
			return null;
		}

		if ($node instanceof For_ || $node instanceof While_) {
			$this->loopDepth--;
			return null;
		}

		if ($node instanceof Foreach_) {
			$this->loopDepth--;
		}

		return null;
	}

	/**
	 * @return list<array{string, string, int}>
	 */
	public function getEncounters() : array {
		return $this->encounters;
	}

	/**
	 * @return list<string>
	 */
	public function getIgnoreVarNames() : array {
		return $this->ignoreVarNames;
	}

	/**
	 * @return list<string>
	 */
	public function getEncounterDebugs() : array {
		return array_map(function(array $encounter) {
			[$type, $varName, $line] = $encounter;
			return sprintf('%s $%s (%d)', $type, $varName, $line);
		}, $this->encounters);
	}

	private function addEncounter(string $type, ?string $varName) : void {
		if (!$varName) return;

		// echo "      $type \$$varName (",  $this->line, ")\n";

		if ($this->loopDepth > 0 && $type == self::DEFINE) return;

		$this->encounters[] = [$type, $varName, $this->line];
	}

	/**
	 * @return list<string>
	 */
	private function getCompactVarNames(FuncCall $node) : array {
		$varNames = [];
		foreach ($node->args as $arg) {
			if ($arg instanceof Arg && $arg->value instanceof String_) {
				$varNames[] = $arg->value->value;
			}
		}
		return $varNames;
	}

	private function getVarName(Expr $node) : ?string {
		if ($node instanceof Variable) {
			if (is_string($node->name)) {
				return $node->name;
			}

			if ($this->debug) dump(__LINE__, $node);
			return null;
		}

		if ($node instanceof PropertyFetch) {
			return null;
		}

		if ($node instanceof StaticPropertyFetch) {
			return null;
		}

		if ($node instanceof ArrayDimFetch) {
			// Doesn't understand object ArrayAccess is okay, because doesn't know $node->var type.
			// return $this->getVarName($node->var);
			return null;
		}

		if ($this->debug) dump(__LINE__, $node);

		return null;
	}

}
