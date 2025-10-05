<?php
declare(strict_types=1);

/**
 * Test database schema for Rhythm plugin tests.
 *
 * This format resembles the existing fixture schema
 * and is converted to SQL via the Schema generation
 * features of the Database package.
 */
return [
    [
        'table' => 'rhythm_entries',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'timestamp' => [
                'type' => 'integer',
                'null' => false,
            ],
            'type' => [
                'type' => 'string',
                'length' => 255,
                'null' => false,
            ],
            'key' => [
                'type' => 'text',
                'null' => false,
            ],
            'key_hash' => [
                'type' => 'string',
                'length' => 32,
                'null' => false,
            ],
            'value' => [
                'type' => 'biginteger',
                'null' => true,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => true,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    [
        'table' => 'rhythm_values',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'timestamp' => [
                'type' => 'integer',
                'null' => false,
            ],
            'type' => [
                'type' => 'string',
                'length' => 255,
                'null' => false,
            ],
            'key' => [
                'type' => 'text',
                'null' => false,
            ],
            'key_hash' => [
                'type' => 'string',
                'length' => 32,
                'null' => false,
            ],
            'value' => [
                'type' => 'text',
                'null' => false,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => true,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    [
        'table' => 'rhythm_aggregates',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'bucket' => [
                'type' => 'integer',
                'null' => false,
            ],
            'period' => [
                'type' => 'integer',
                'null' => false,
            ],
            'type' => [
                'type' => 'string',
                'length' => 255,
                'null' => false,
            ],
            'key' => [
                'type' => 'text',
                'null' => false,
            ],
            'key_hash' => [
                'type' => 'string',
                'length' => 32,
                'null' => false,
            ],
            'aggregate' => [
                'type' => 'string',
                'length' => 50,
                'null' => false,
            ],
            'value' => [
                'type' => 'decimal',
                'length' => 20,
                'precision' => 4,
                'null' => false,
            ],
            'count' => [
                'type' => 'integer',
                'null' => true,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => true,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
            'unique_aggregate' => [
                'type' => 'unique',
                'columns' => [
                    'bucket',
                    'period',
                    'type',
                    'aggregate',
                    'key_hash',
                ],
            ],
        ],
    ],
    [
        'table' => 'test_table',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'name' => [
                'type' => 'string',
                'length' => 100,
                'null' => false,
            ],
            'description' => [
                'type' => 'text',
                'null' => true,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => true,
            ],
            'modified' => [
                'type' => 'datetime',
                'null' => true,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
];
