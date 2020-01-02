<?php

declare(strict_types=1);

namespace Grzesie2k\PHPStan\GraphQL;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<String_>
 */
class GqlRule implements Rule
{
    /**
     * @var string
     */
    private $configFileName;
    /**
     * @var int
     */
    private $configMaxDeep;

    public function __construct(
        string $configFileName = '.graphqlconfig',
        int $configMaxDeep = 10
    ) {
        $this->configFileName = $configFileName;
        $this->configMaxDeep = $configMaxDeep;
    }

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof String_) {
            throw new ShouldNotHappenException();
        }

        if (!preg_match('/^\s*(?<type>mutation|query) (?<operationName>\w*)/m', $node->value, $matches)) {
            return [];
        }

        $dir = dirname($scope->getFile());
        $configPath = $this->findConfigFile($dir);
        if (null === $configPath) {
            return ['Cannot find GraphQL config file for query.'];
        }

        $schema = $this->getSchemaFromConfig($configPath);

        if (!$schema) {
            return ['Cannot load or parse GraphQL schema to validate query.'];
        }

        try {
            $query = Parser::parse(new Source($node->value, $matches['operationName']));
        } catch (SyntaxError $error) {
            return [$error->message];
        }
        $validationErrors = DocumentValidator::validate($schema, $query);
        $errors = [];
        foreach ($validationErrors as $error) {
            $errors = [$error->message];
        }

        return $errors;
    }

    private function getSchemaFromConfig(string $configPath): ?Schema
    {
        $configContent = @file_get_contents($configPath);

        if (!$configContent) {
            return null;
        }
        $config = @json_decode($configContent, false);

        if (!$config instanceof \stdClass) {
            return null;
        }

        if (!($config->schemaPath ?? null)) {
            return null;
        }
        $schemaPath = dirname($configPath).DIRECTORY_SEPARATOR.$config->schemaPath;
        $schemaContent = @file_get_contents($schemaPath);

        if (!$schemaContent) {
            return null;
        }

        try {
            return BuildSchema::build($schemaContent);
        } catch (SyntaxError $error) {
            return null;
        }
    }

    private function findConfigFile(string $dir, int $level = 0): ?string
    {
        $config = "{$dir}/{$this->configFileName}";

        if (file_exists($config)) {
            return $config;
        }

        $parent = dirname($dir);

        if ($parent === $dir || $this->configMaxDeep === ++$level) {
            return null;
        }

        return $this->findConfigFile($parent, $level);
    }
}
