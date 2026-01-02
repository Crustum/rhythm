<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Rhythm Entries Table
 *
 * Handles storage and retrieval of raw metric entries.
 */
class RhythmEntriesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rhythm_entries');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('RhythmAggregates', [
            'foreignKey' => 'key_hash',
            'bindingKey' => 'key_hash',
            'joinType' => 'INNER',
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
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('timestamp')
            ->notEmptyString('timestamp');

        $validator
            ->scalar('type')
            ->maxLength('type', 255)
            ->notEmptyString('type');

        $validator
            ->scalar('key_hash')
            ->maxLength('key_hash', 32)
            ->notEmptyString('key_hash');

        $validator
            ->scalar('key')
            ->maxLength('key', 10000)
            ->notEmptyString('key');

        $validator
            ->numeric('value')
            ->allowEmptyString('value');

        return $validator;
    }

    /**
     * Find entries by type and key.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Rhythm\Model\Entity\MetricEntry> $query Query object
     * @param array<string, mixed> $options Options array
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Rhythm\Model\Entity\MetricEntry>
     */
    public function findByTypeAndKey(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'type' => $options['type'],
            'key' => $options['key'],
        ]);
    }

    /**
     * Find entries within a time range.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query   object
     * @param array $options Options array
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByTimeRange(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'timestamp >=' => $options['start'],
            'timestamp <=' => $options['end'],
        ]);
    }
}
