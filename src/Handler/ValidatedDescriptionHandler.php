<?php namespace GuzzleHttp\Command\Guzzle\Handler;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\SchemaValidator;

/**
 * Handler used to validate command input against a service description.
 *
 * @author Stefano Kowalke <info@arroba-it.de>
 */
class ValidatedDescriptionHandler
{
    /** @var SchemaValidator $validator */
    private $validator;

    /** @var DescriptionInterface $description */
    private $description;

    /**
     * ValidatedDescriptionHandler constructor.
     *
     * @param DescriptionInterface $description
     * @param SchemaValidator|null $schemaValidator
     */
    public function __construct(DescriptionInterface $description, SchemaValidator $schemaValidator = null)
    {
        $this->description = $description;
        $this->validator = $schemaValidator ?: new SchemaValidator();
    }

    /**
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (CommandInterface $command) use ($handler) {
            $errors = [];
            $operation = $this->description->getOperation($command->getName());

            foreach ($operation->getParams() as $name => $schema) {
                $value = $command[$name];

                if ($value) {
                    $value = $schema->filter($value);
                }

                if (! $this->validator->validate($schema, $value)) {
                    $errors = array_merge($errors, $this->validator->getErrors());
                } elseif ($value !== $command[$name]) {
                    // Update the config value if filters are not set or
                    // default value is set but command has no value
                    if (empty($schema->getFilters())) {
                        $command[$name] = $value;
                    } elseif (!isset($command[$name]) && $schema->getDefault()) {
                        $command[$name] = $schema->getDefault();
                    }
                }
            }

            if ($params = $operation->getAdditionalParameters()) {
                foreach ($command->toArray() as $name => $value) {
                    // It's only additional if it isn't defined in the schema
                    if (! $operation->hasParam($name)) {
                        // Always set the name so that error messages are useful
                        $params->setName($name);
                        if (! $this->validator->validate($params, $value)) {
                            $errors = array_merge($errors, $this->validator->getErrors());
                        } elseif ($value !== $command[$name] && empty($schema->getFilters())) {
                            $command[$name] = $value;
                        }
                    }
                }
            }

            if ($errors) {
                throw new CommandException('Validation errors: ' . implode("\n", $errors), $command);
            }

            return $handler($command);
        };
    }
}
