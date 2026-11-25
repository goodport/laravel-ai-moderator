<?php

namespace Goodport\AiModerator\Tests\Unit\Schema;

use Goodport\AiModerator\Schema\SchemaBuilder;
use Goodport\AiModerator\Tests\Fixtures\CustomCheck;
use Goodport\AiModerator\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

final class DetailsSchemaTest extends TestCase
{
    /**
     * A custom details schema is added to the check envelope.
     */
    public function test_details_are_added_to_schema(): void
    {
        $check = (new CustomCheck)->definition();
        $schema = new JsonSchemaTypeFactory;

        $properties = (new SchemaBuilder)->build($check, $schema);
        $details = $properties['details']->toArray();

        $this->assertSame('object', $details['type']);
        $this->assertSame('number', $details['properties']['score']['type']);
        $this->assertSame(['score'], $details['required']);
    }
}
