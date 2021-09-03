<?php

namespace Ilex\Base\Model\Query;

use \MongoDate;
use \Ilex\Core\Loader;
use \Ilex\Lib\Kit;
use \Ilex\Lib\UserException;
use \Ilex\Lib\MongoDB\MongoDBId;
use \Ilex\Lib\MongoDB\QueryWrapper;
use \Ilex\Base\Model\Entity\BaseEntity;

/**
 * Class BaseQuery
 * Base class of query models of Ilex.
 * @package Ilex\Base\Model\Query
 */
class BaseQuery
{

    private $queryWrapper = NULL;

    private $criterion = NULL;
    private $sortBy    = NULL;
    private $skip      = NULL;
    private $limit     = NULL;
    private $timeout   = NULL;

    final public function __construct($collection_name, $entity_path)
    {
        Kit::ensureString($collection_name, TRUE);
        Kit::ensureString($entity_path);
        if (FALSE === is_null($collection_name))
            $this->queryWrapper = QueryWrapper::getInstance($collection_name, $entity_path);
        $this->clear();
    }

    final public function clear()
    {
        $this->criterion = NULL;
        $this->sortBy    = NULL;
        $this->skip      = NULL;
        $this->limit     = NULL;
        return $this;
    }

    final private function ensureInitialized()
    {
        if (FALSE === isset($this->queryWrapper)
            OR FALSE === $this->queryWrapper instanceof QueryWrapper)
            throw new UserException('This query has not been initialized.');
        // if (TRUE === is_null($this->criterion))
            // throw new UserException('Criterion has not been initialized.');
    }


    //==============================================================================


    final public function all()
    {
        return $this->mergeCriterion([ ]);
    }

    final public function idIs($id)
    {
        $id = new MongoDBId($id);
        return $this->isEqualTo('_id', $id->toMongoId());
    }

    final public function idIsNot($id)
    {
        $id = new MongoDBId($id);
        return $this->isNotEqualTo('_id', $id->toMongoId());
    }

    // O(N) when $to_mongo_id is TRUE
    final public function idIn($id_list, $to_mongo_id = FALSE)
    {
        if (TRUE === $to_mongo_id) {
            $tmp = [];
            foreach ($id_list as $id) {
                $tmp[] = (new MongoDBId($id))->toMongoId();
            }
            $id_list = $tmp;
        }
        return $this->in('_id', $id_list);
    }

    final public function signatureIs($signature)
    {
        return $this->isEqualTo('Signature', $signature);
    }

    final public function hasDataField($field_name)
    {
        Kit::ensureString($field_name);
        return $this->hasField("Data.${field_name}");
    }

    final public function dataFieldIs($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        return $this->isEqualTo("Data.${field_name}", $field_value);
    }

    final public function dataFieldIsNot($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        return $this->isNotEqualTo("Data.${field_name}", $field_value);
    }

    final public function dataIs($field_value)
    {
        Kit::ensureArray($field_value);
        return $this->isEqualTo("Data", $field_value);
    }

    final public function nameIs($name)
    {
        Kit::ensureString($name);
        return $this->infoFieldIs('Name', $name);
    }

    public function noIs($no)
    {
        Kit::ensureString($no);
        return $this->infoFieldIs('No', $no);
    }

    final public function noIn($no_list)
    {
        Kit::ensureArray($no_list);
        return $this->infoFieldIn('No', $no_list);
    }

    final public function hasInfoField($field_name)
    {
        Kit::ensureString($field_name);
        return $this->hasField("Info.${field_name}");
    }

    final public function infoFieldIs($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        return $this->isEqualTo("Info.${field_name}", $field_value);
    }

    final public function infoFieldIsNot($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        return $this->isNotEqualTo("Info.${field_name}", $field_value);
    }

    final public function infoFieldIn($field_name, $field_value_list)
    {
        return $this->in("Info.${field_name}", $field_value_list);
    }

    final public function infoFieldNotIn($field_name, $field_value_list)
    {
        return $this->notIn("Info.${field_name}", $field_value_list);
    }

    final public function infoIs($field_value)
    {
        Kit::ensureArray($field_value);
        return $this->isEqualTo('Info', $field_value);
    }

    final public function hasMultiReferenceTo(BaseEntity $entity, $reference_name = NULL)
    {
        Kit::ensureString($reference_name, TRUE);
        if (TRUE === is_null($reference_name))
            $reference_name = $entity->getEntityName();
        return $this->isEqualTo("Reference.${reference_name}IdList", $entity->getId()->toMongoId());
    }
    
    final public function hasOneReferenceTo(BaseEntity $entity, $reference_name = NULL)
    {
        Kit::ensureString($reference_name, TRUE);
        if (TRUE === is_null($reference_name))
            $reference_name = $entity->getEntityName();
        return $this->isEqualTo("Reference.${reference_name}Id", $entity->getId()->toMongoId());
    }

    final public function typeIs($type)
    {
        Kit::ensureString($type);
        return $this->metaFieldIs('Type', $type);
    }

    final public function typeIn($type_list)
    {
        return $this->in('Meta.Type', $type_list);
    }

    final public function stateIs($state)
    {
        Kit::ensureType($state, [ Kit::TYPE_INT, Kit::TYPE_STRING ]);
        return $this->metaFieldIs('State', $state);
    }

    final public function stateIsNot($state)
    {
        Kit::ensureType($state, [ Kit::TYPE_INT, Kit::TYPE_STRING ]);
        return $this->metaFieldIsNot('State', $state);
    }

    final public function stateIn($state_list)
    {
        return $this->in('Meta.State', $state_list);
    }

    final public function isCreatedBefore(MongoDate $date)
    {
        return $this->isLessThanOrEqualTo('Meta.CreationTime', $date);
    }

    final public function isCreatedAfter(MongoDate $date)
    {
        return $this->isGreaterThan('Meta.CreationTime', $date);
    }

    // final public function isModifiedBefore($timestamp)
    // {
    //     // @TODO: $timestamp
    //     return $this->isLessThan('Meta.ModificationTime', $timestamp);
    // }

    // final public function isModifiedAfter($timestamp)
    // {
    //     // @TODO: $timestamp
    //     return $this->isGreaterThan('Meta.ModificationTime', $timestamp);
    // }

    final public function isNotDeleted()
    {
        return $this->metaFieldIsNot('IsDeleted', TRUE);
    }

    final public function isNotRemoved()
    {
        return $this->metaFieldIsNot('IsRemoved', TRUE);
    }
    
    final public function isRemoved()
    {
        return $this->metaFieldIs('IsRemoved', TRUE);
    }

    final public function hasMetaField($field_name)
    {
        Kit::ensureString($field_name);
        return $this->hasField("Meta.${field_name}");
    }

    final public function metaFieldIs($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        return $this->isEqualTo("Meta.${field_name}", $field_value);
    }

    final public function metaFieldIsNot($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        return $this->isNotEqualTo("Meta.${field_name}", $field_value);
    }

    final public function timeFieldInToday($time_field_name)
    {
        Kit::ensureString($time_field_name);
        return $this
            ->isGreaterThanOrEqualTo($time_field_name, Kit::todayStartTime())
            ->isLessThan($time_field_name, Kit::todayEndTime());
    }

    //==============================================================================
    
    final public function hasField($field_name)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => [ '$exists' => TRUE ],
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function isEqualTo($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => $field_value,
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function isNotEqualTo($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => [ '$ne' => $field_value ],
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function isGreaterThan($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => [ '$gt' => $field_value ],
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function isGreaterThanOrEqualTo($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => [ '$gte' => $field_value ],
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function isLessThan($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => [ '$lt' => $field_value ],
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function isLessThanOrEqualTo($field_name, $field_value)
    {
        Kit::ensureString($field_name);
        $criterion = [
            $field_name => [ '$lte' => $field_value ],
        ];
        return $this->mergeCriterion($criterion);
    }

    // If $field_value_list is empty, returns no result.
    final public function in($field_name, $field_value_list)
    {
        Kit::ensureString($field_name);
        Kit::ensureArray($field_value_list); // @CAUTION
        $criterion = [
            $field_name => [ '$in' => $field_value_list ],
        ];
        return $this->mergeCriterion($criterion);
    }

    // If $field_value_list is empty, returns no result.
    final public function notIn($field_name, $field_value_list)
    {
        Kit::ensureString($field_name);
        Kit::ensureArray($field_value_list); // @CAUTION
        $criterion = [
            $field_name => [ '$nin' => $field_value_list ],
        ];
        return $this->mergeCriterion($criterion);
    }

    final public function addOr($criterion)
    {
        Kit::ensureArray($criterion);
        return $this->mergeCriterion([ '$or' => $criterion ]);
    }

    final public function getCriterion()
    {
        return [
            'criterion' => $this->criterion,
            'sortBy'    => $this->sortBy,
            'skip'      => $this->skip,
            'limit'     => $this->limit,
        ];

    }

    final public function mergeCriterion($criterion)
    {
        Kit::ensureDict($criterion);
        if (TRUE === is_null($this->criterion))
            $this->criterion = [ ];
        $this->criterion = array_merge_recursive($this->criterion, $criterion); // @CAUTION
        return $this;
    }

    //==============================================================================

    final public function sortByName($direction = 1)
    {
        return $this->sortBy('Info.Name', $direction);
    }

    final public function sortByCreationTime($direction = -1)
    {
        return $this->sortBy('Meta.CreationTime', $direction);
    }

    final public function sortBy($field_name, $direction)
    {
        Kit::ensureIn($direction, [ -1, 1 ]);
        return $this->mergeSortBy([ $field_name => $direction ]);
    }

    final private function mergeSortBy($sort_by)
    {
        Kit::ensureDict($sort_by);
        if (TRUE === is_null($this->sortBy))
            $this->sortBy = [ ];
        $this->sortBy = array_merge_recursive($this->sortBy, $sort_by); // @CAUTION
        return $this;
    }

    final public function skip($skip = NULL)
    {
        Kit::ensureNonNegativeInt($skip, TRUE);
        if (TRUE === is_null($skip)) return $this;
        $this->skip = $skip;
        return $this;
    }

    final public function limit($limit = NULL)
    {
        Kit::ensureInt($limit, TRUE);
        if (TRUE === is_null($limit)) return $this;
        $this->limit = $limit;
        return $this;
    }

    final public function timeout($timeout = NULL)
    {
        Kit::ensureInt($timeout, TRUE);
        if (TRUE === is_null($timeout)) return $this;
        $this->timeout = $timeout;
        return $this;
    }

    //==============================================================================


    final public function checkExistEntities()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->checkExistEntities($this->criterion);
        // $this->clear();
        return $result;
    }

    final public function ensureExistEntities()
    {
        $this->ensureInitialized();
        $this->queryWrapper->ensureExistEntities($this->criterion);
        // $this->clear();
        return $this;
    }

    final public function checkExistsOnlyOneEntity()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->checkExistsOnlyOneEntity($this->criterion);
        // $this->clear();
        return $result;
    }

    final public function ensureExistsOnlyOneEntity()
    {
        $this->ensureInitialized();
        $this->queryWrapper->ensureExistsOnlyOneEntity($this->criterion);
        // $this->clear();
        return $this;
    }
     
    final public function countEntities()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->countEntities(
            $this->criterion,
            $this->skip,
            $this->limit
        );
        // $this->clear();
        return $result;
    }

    final public function getMultiEntities()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->getMultiEntities(
            $this->criterion,
            $this->sortBy,
            $this->skip,
            $this->limit,
            $this->timeout
        );
        // $this->clear();
        return $result;
    }

    final public function getTheOnlyOneEntity()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->getTheOnlyOneEntity($this->criterion);
        // $this->clear();
        return $result;
    }

    final public function getOneEntity()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->getOneEntity(
            $this->criterion,
            $this->sortBy,
            $this->skip,
            $this->limit
        );
        // $this->clear();
        return $result;
    }

    final public function removeMultiEntities()
    {
        $this->ensureInitialized();
        $result = $this->queryWrapper->removeMultiEntities(
            $this->criterion,
            $this->timeout
        );
        // $this->clear();
        return $result;
    }
}