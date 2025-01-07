<?php

namespace rdx\PhpstanExtra\Ires;

use db_generic;
use Framework\Aro\ActiveRecordObject;
use Framework\Aro\ActiveRecordRelationship;
use Framework\Console\Commands\CompileModels\IncludesTablesAttribute;
use Framework\Console\Commands\CompileModels\SchemaFieldDefinition;
use Framework\Console\Commands\CompileModels\SchemaParser;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\Annotations\AnnotationPropertyReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\MixedType;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final class AroPropertyExtension implements PropertiesClassReflectionExtension {

	private const DEBUG_CLASS = '';
	private const DEBUG_PROPERTY = '';

	/** @var array<class-string<ActiveRecordObject>, ActiveRecordObject> */
	protected array $objects = [];

	/** @var array<class-string<ActiveRecordObject>, list<string>> */
	protected array $dbTables = [];

	protected db_generic $fakeDb;

	public function __construct(
		protected ?TypeStringResolver $typeStringResolver,
		protected SchemaParser $schemaParser,
	) {
		ActiveRecordObject::setDbObject($this->fakeDb = $this->createFakeDb());
	}

	public function hasProperty(ClassReflection $classReflection, string $propertyName) : bool {
		$className = $classReflection->getName();

		$debugClass = $className === self::DEBUG_CLASS;
		$debugProperty = $propertyName === self::DEBUG_PROPERTY;

		if (!$classReflection->isSubclassOf(ActiveRecordObject::class)) {
			if ($debugProperty) dump(__LINE__);
			return false;
		}

		if ($classReflection->isAbstract()) {
			if ($debugProperty) dump(__LINE__);
			return false;
		}

		$phpdoc = $classReflection->getResolvedPhpDoc();
		if ($phpdoc) {
			$propertyTags = $phpdoc->getPropertyTags();
			if (isset($propertyTags[$propertyName])) {
				if ($debugProperty) dump(__LINE__);
				return false;
			}
		}

		// try {
			$object = $this->getObject($className);
			if ($debugClass) dump($propertyName, $object);
		// }
		// catch (Throwable $ex) {
		// 	if ($debugProperty) dump($className, $propertyName, $ex);
		// 	return false;
		// }

		if (null !== $this->getObjectValue($object, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			return true;
		}

		if ($object->existsRelationship($propertyName) || $object->existsGetter($propertyName)) {
			if ($debugProperty) dump(__LINE__);
			return true;
		}

		if ($debugProperty) dump(__LINE__);
		return false;
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName) : PropertyReflection {
		$className = $classReflection->getName();

		$debugProperty = $propertyName === self::DEBUG_PROPERTY;

		$object = $this->getObject($className);

		$type = new MixedType();
		$writable = true;

		// ARO database/init() field
		if (null !== ($value = $this->getObjectValue($object, $propertyName))) {
			if ($debugProperty) dump(__LINE__);
			$type = $this->typeStringResolver->resolve(gettype($value));
		}
		// Database field
		elseif ($field = $this->getField($classReflection, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			$type = $this->typeStringResolver->resolve($field->getNullablePhpType());
		}
		// ARO relate_*
		elseif ($object->existsRelationship($propertyName)) {
			if ($debugProperty) dump(__LINE__);
			$relationMethod = 'relate_' . $propertyName;
			$reflMethod = new ReflectionMethod($className, $relationMethod); // @phpstan-ignore phpstanApi.runtimeReflection
			/** @var ActiveRecordRelationship $relationship */
			$relationship = $reflMethod->invoke($object);
			$type = $this->typeStringResolver->resolve($relationship->getReturnType());
		}
		// ARO get_*
		elseif ($object->existsGetter($propertyName)) {
			if ($debugProperty) dump(__LINE__);
			$methodRefl = $classReflection->getMethod('get_' . $propertyName, new OutOfClassScope());
			$type = $methodRefl->getVariants()[0]->getReturnType();
		}

		return new AnnotationPropertyReflection( // @phpstan-ignore phpstanApi.constructor
			$classReflection,
			readableType: $type,
			writableType: $type,
			readable: true,
			writable: $writable,
		);
	}

	/**
	 *
	 */
	protected function getField(ClassReflection $classReflection, string $propertyName) : ?SchemaFieldDefinition {
		$className = $classReflection->getName();
		$dbTables = $this->getDbTables($className);
// dump($classReflection->getName(), $propertyName, $dbTables);

		foreach ($dbTables as $tableName) {
			$field = $this->schemaParser->getColumn($tableName, $propertyName);
			if ($field) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	protected function getDbTables(string $className) : array {
		if (isset($this->dbTables[$className])) {
			return $this->dbTables[$className];
		}

		$reflClass = new ReflectionClass($className); // @phpstan-ignore phpstanApi.runtimeReflection
		$attributes = $reflClass->getAttributes(IncludesTablesAttribute::class);

		$dbTables = [$className::_table()];
		if ( !count($attributes) ) {
			return $this->dbTables[$className] = $dbTables;
		}

		$attribute = $attributes[0]->newInstance();
		$dbTables = [...$dbTables, ...$attribute->getTables()];

		return $this->dbTables[$className] = $dbTables;
	}

	protected function getObject(string $className) : ActiveRecordObject {
		if (isset($this->objects[$className])) {
			return $this->objects[$className];
		}

		$dbTables = $this->getDbTables($className);
		$values = [];
		foreach ($dbTables as $tableName) {
			$fields = $this->schemaParser->getTableColumns($tableName);
			$values += array_map(fn($field) => $field->makeValue(), $fields);
		}

		return $this->objects[$className] = new $className($values);
	}

	protected function getObjectValue(ActiveRecordObject $object, string $propertyName) : mixed {
		$vars = get_object_vars($object);
		return $vars[$propertyName] ?? null;
	}

	protected function createFakeDb() : db_generic {
		return new class extends db_generic {
			public function connected() : bool {
				return false;
			}
			public function close() : bool {
				return true;
			}
			public function begin() : void {
			}
			public function commit() : void {
			}
			public function rollback() : void {
			}
			public function escape(mixed $value) : string {
				return $value;
			}
			public function insert_id() : int {
				return 0;
			}
			public function affected_rows() : int {
				return 0;
			}
			public function query(string $query) {
				return true;
			}
			public function fetch(string $query, bool|array $first = false, array $args = []) : ?array {
				return null;
			}
			public function fetch_fields(string $query, array $args = []) : array {
				return [];
			}
			public function fetch_one(string $query, array $args = []) : mixed {
				return false;
			}
			public function fetch_by_field(string $query, string $field, array $args = []) : array {
				return [];
			}
			public function groupfetch_by_field( string $query, string $field, array $args = [] ) : array {
				return [];
			}
		};
	}

}
