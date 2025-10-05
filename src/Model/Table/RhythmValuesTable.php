<?php
declare(strict_types=1);

namespace Rhythm\Model\Table;

use Cake\Collection\CollectionInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Rhythm Values Table
 *
 * Handles string metric values for storage and display.
 */
class RhythmValuesTable extends Table
{
    /**
     * Initialize method.
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rhythm_values');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('timestamp')
            ->notEmptyString('timestamp')
            ->greaterThan('timestamp', 0, 'Timestamp must be a positive integer');

        $validator
            ->scalar('type')
            ->maxLength('type', 255)
            ->notEmptyString('type')
            ->requirePresence('type', 'create');

        $validator
            ->scalar('key')
            ->notEmptyString('key')
            ->requirePresence('key', 'create');

        $validator
            ->scalar('key_hash')
            ->maxLength('key_hash', 32)
            ->notEmptyString('key_hash');

        $validator
            ->scalar('value')
            ->notEmptyString('value')
            ->requirePresence('value', 'create');

        return $validator;
    }

    /**
     * Retrieve values for the given type.
     *
     * @param string $type The metric type
     * @param array<string>|null $keys Optional list of keys to filter by
     * @return \Cake\Collection\CollectionInterface
     */
    public function values(string $type, ?array $keys = null): CollectionInterface
    {
        $query = $this->find()
            ->select(['timestamp', 'key', 'value'])
            ->where(['type' => $type]);

        if ($keys !== null) {
            $query->whereInList('key', $keys);
        }

        return $query->all()->indexBy('key');
    }
}
